<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Hotel;
use App\Models\Payslip;
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

        $isAdmin = $m->role === 'admin';
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
        if (! $m || $m->role !== 'admin') {
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

        return $p && ($p->user_id === $request->user()->id || $m->role === 'admin') ? $p : null;
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

        return Storage::disk('local')->download($p->file_path, $p->original_name, [
            'Content-Type'           => $p->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    public function destroy(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $p = $this->findAllowed($request, $hotel, $uuid);
        if (! $p || $m->role !== 'admin') {
            return $this->forbidden('Somente o RH pode remover documentos.');
        }

        Storage::disk('local')->delete($p->file_path);
        $p->delete();

        return $this->success('Documento removido com sucesso.');
    }
}
