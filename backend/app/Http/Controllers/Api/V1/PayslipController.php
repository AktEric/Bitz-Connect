<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\Payslip;
use App\Models\User;
use App\Support\ImageSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Recebimentos: holerites, comprovantes e bônus anexados pelo RH. Arquivos ficam em disco privado. */
class PayslipController extends WorkspaceBaseController
{
    private function format(Payslip $p, bool $forAdmin): array
    {
        return [
            'uuid'            => $p->uuid,
            'kind'            => $p->kind,
            'title'           => $p->title,
            'reference_month' => $p->reference_month?->format('Y-m'),
            'amount_cents'    => $p->amount_cents,
            'original_name'   => $p->original_name,
            'mime'            => $p->mime,
            'size'            => $p->size,
            'viewed'          => $p->first_viewed_at !== null,
            'signed_at'       => $p->signed_at?->toISOString(),
            'signature_kind'  => $p->signature_kind,
            'first_viewed_at' => $forAdmin ? $p->first_viewed_at?->toISOString() : null,
            'owner'           => $this->access->person($p->owner),
            'created_at'      => $p->created_at?->toISOString(),
        ];
    }

    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $isAdmin = $this->access->canHandlePayslips($m);
        $q = Payslip::with('owner')->where('hotel_id', $hotel->id);

        if ($isAdmin && $request->filled('user')) {
            $target = $this->access->memberByUuid($hotel, (string) $request->query('user'));
            if (! $target) {
                return $this->notFound('Pessoa não encontrada.');
            }
            $q->where('user_id', $target->user_id);
        } elseif ($isAdmin && $request->boolean('all')) {
            // RH vê tudo
        } else {
            $q->where('user_id', $request->user()->id);
        }

        $page = $q->orderByDesc('reference_month')->orderByDesc('id')->paginate(24);

        return $this->success('Recebimentos carregados com sucesso.', [
            'items'     => $page->getCollection()->map(fn ($p) => $this->format($p, $isAdmin))->values(),
            'page'      => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m || ! $this->access->canHandlePayslips($m)) {
            return $this->forbidden('Somente o RH/financeiro pode anexar recebimentos.');
        }

        $data = $request->validate([
            'user_uuid'       => ['required', 'uuid'],
            'kind'            => ['required', Rule::in(['payslip', 'receipt', 'bonus', 'other'])],
            'title'           => ['required', 'string', 'max:160'],
            'reference_month' => ['nullable', 'date_format:Y-m'],
            'amount'          => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'file'            => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $target = $this->access->memberByUuid($hotel, $data['user_uuid']);
        if (! $target) {
            return $this->error('Pessoa não faz parte desta empresa.', ['user_uuid' => ['Pessoa inválida.']], 422);
        }

        $file = $request->file('file');
        $path = $file->storeAs("payslips/{$hotel->id}", Str::uuid() . '.' . $file->getClientOriginalExtension(), 'local');

        $p = Payslip::create([
            'uuid'            => (string) Str::uuid(),
            'hotel_id'        => $hotel->id,
            'user_id'         => $target->user_id,
            'uploaded_by'     => $request->user()->id,
            'kind'            => $data['kind'],
            'title'           => $data['title'],
            'reference_month' => isset($data['reference_month']) ? $data['reference_month'] . '-01' : null,
            'amount_cents'    => isset($data['amount']) ? (int) round($data['amount'] * 100) : null,
            'file_path'       => $path,
            'original_name'   => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime'            => $file->getMimeType() ?: 'application/octet-stream',
            'size'            => $file->getSize(),
        ]);
        $p->load('owner');
        AuditLog::record($request, $hotel, 'payslip.created', $target->user, ['kind' => $p->kind, 'title' => $p->title]);

        $this->notifications->workspaceNotice(
            $target->user, $request->user(), 'workspace_payslip',
            'Novo documento em Recebimentos', $p->title, $this->link($hotel, 'recebimentos'),
        );

        return $this->created('Recebimento anexado com sucesso.', $this->format($p, true));
    }

    private function findAllowed(Request $request, Hotel $hotel, string $uuid): ?Payslip
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return null;
        }
        $p = Payslip::with('owner')->where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();

        return $p && ($p->user_id === $request->user()->id || $this->access->canHandlePayslips($m)) ? $p : null;
    }

    public function download(Request $request, Hotel $hotel, string $uuid): StreamedResponse|JsonResponse
    {
        $p = $this->findAllowed($request, $hotel, $uuid);
        if (! $p || ! Storage::disk('local')->exists($p->file_path)) {
            return $this->notFound('Documento não encontrado.');
        }

        if ($p->user_id === $request->user()->id && ! $p->first_viewed_at) {
            $p->forceFill(['first_viewed_at' => now()])->save();
        }

        if ($p->user_id !== $request->user()->id) {
            AuditLog::record($request, $hotel, 'payslip.downloaded', $p->owner, ['title' => $p->title]);
        }

        return Storage::disk('local')->download($p->file_path, $p->original_name, [
            'Content-Type'           => $p->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    private function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower(Str::ascii($name))));
    }

    /** PNG vindo do canvas ("data:image/png;base64,..."): valida formato, tamanho e dimensões. */
    private function decodeSignatureImage(?string $dataUrl): ?string
    {
        if (! $dataUrl || ! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return null;
        }
        $bytes = base64_decode($m[1], true);
        if ($bytes === false || strlen($bytes) > 300_000 || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return null;
        }
        $info = @getimagesizefromstring($bytes);

        return $info && $info[2] === IMAGETYPE_PNG && $info[0] >= 40 && $info[0] <= 1600 && $info[1] >= 20 && $info[1] <= 900
            ? ImageSanitizer::clean($bytes) : null;
    }

    /** POST /payslips/{uuid}/sign  { kind: drawn|typed, name?, image?, accept } — só o dono do documento, uma vez. */
    public function sign(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $p = $this->findAllowed($request, $hotel, $uuid);
        if (! $p || $p->user_id !== $request->user()->id || ! Storage::disk('local')->exists($p->file_path)) {
            return $this->notFound('Documento não encontrado.');
        }
        if ($p->signed_at) {
            return $this->error('Este documento já foi assinado.', [], 409);
        }
        $data = $request->validate([
            'kind'   => ['required', Rule::in(['drawn', 'typed'])],
            'name'   => ['required_if:kind,typed', 'nullable', 'string', 'max:160'],
            'image'  => ['required_if:kind,drawn', 'nullable', 'string', 'max:450000'],
            'accept' => ['accepted'],
        ], ['accept.accepted' => 'Confirme que recebeu o documento para assinar.']);

        $user = $request->user();
        $imageBytes = null;
        if ($data['kind'] === 'typed') {
            if ($this->normalizeName($data['name']) !== $this->normalizeName($user->name)) {
                return $this->error('Digite seu nome completo, como está cadastrado.', ['name' => ['O nome não confere com o seu cadastro.']], 422);
            }
            $signer = $user->name;
        } else {
            $imageBytes = $this->decodeSignatureImage($data['image'] ?? null);
            if (! $imageBytes) {
                return $this->error('Assinatura inválida. Desenhe novamente.', ['image' => ['Imagem de assinatura inválida.']], 422);
            }
            $signer = $user->name;
        }

        $at = now();
        $path = null;
        if ($imageBytes !== null) {
            $path = "payslips/{$hotel->id}/signatures/{$p->uuid}.png";
            Storage::disk('local')->put($path, $imageBytes);
        }
        $hash = hash('sha256', implode('|', [
            $p->uuid, hash('sha256', (string) Storage::disk('local')->get($p->file_path)), $user->id, $at->toIso8601String(),
            $data['kind'], $signer, $imageBytes !== null ? hash('sha256', $imageBytes) : '',
        ]));

        $p->forceFill([
            'signed_at' => $at, 'signature_kind' => $data['kind'], 'signature_name' => $signer, 'signature_path' => $path,
            'signature_hash' => $hash, 'signed_ip' => $request->ip(),
        ])->save();
        AuditLog::record($request, $hotel, 'payslip.signed', $p->owner, ['title' => $p->title, 'kind' => $data['kind']]);

        if ($p->uploaded_by && ($uploader = User::find($p->uploaded_by))) {
            $this->notifications->workspaceNotice($uploader, $user, 'workspace_payslip', "{$user->name} assinou um documento", $p->title, $this->link($hotel, 'recebimentos'));
        }

        return $this->success('Documento assinado com sucesso.', $this->format($p->refresh()->load('owner'), false));
    }

    /** GET /payslips/{uuid}/signature — comprovante da assinatura (dono do documento ou RH/financeiro). */
    public function signature(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $p = $this->findAllowed($request, $hotel, $uuid);
        if (! $p || ! $p->signed_at) {
            return $this->notFound('Este documento ainda não foi assinado.');
        }
        if ($p->user_id !== $request->user()->id) {
            AuditLog::record($request, $hotel, 'payslip.signature_viewed', $p->owner, ['title' => $p->title]);
        }
        $image = $p->signature_path && Storage::disk('local')->exists($p->signature_path)
            ? 'data:image/png;base64,' . base64_encode((string) Storage::disk('local')->get($p->signature_path)) : null;

        return $this->success('Assinatura carregada.', [
            'title' => $p->title, 'kind' => $p->signature_kind, 'name' => $p->signature_name, 'image' => $image,
            'signed_at' => $p->signed_at->toISOString(), 'hash' => $p->signature_hash,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function destroy(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $this->findAllowed($request, $hotel, $uuid);
        if (! $p || ! $this->access->canHandlePayslips($m)) {
            return $this->forbidden('Somente o RH pode remover documentos.');
        }

        AuditLog::record($request, $hotel, 'payslip.deleted', $p->owner, ['title' => $p->title]);
        Storage::disk('local')->delete(array_filter([$p->file_path, $p->signature_path]));
        $p->delete();

        return $this->success('Documento removido com sucesso.');
    }
}
