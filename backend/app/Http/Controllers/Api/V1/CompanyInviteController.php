<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\CompanyInvite;
use App\Models\Hotel;
use App\Models\HotelUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Convites por link para entrar no Workspace de uma empresa.
 * Criação/listagem/revogação: administradores. Pré-visualização: pública (por token). Aceite: usuário logado.
 */
class CompanyInviteController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly WorkspaceAccess $access) {}

    /** GET /workspaces/{hotel:slug}/invites */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denyUnlessAdmin($request, $hotel)) {
            return $denied;
        }

        $data = CompanyInvite::with('department')
            ->where('hotel_id', $hotel->id)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (CompanyInvite $i) => $this->format($i));

        return $this->success('Convites carregados com sucesso.', $data);
    }

    /** POST /workspaces/{hotel:slug}/invites */
    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denyUnlessAdmin($request, $hotel)) {
            return $denied;
        }

        $data = $request->validate([
            'email'           => ['nullable', 'email', 'max:255'],
            'department_uuid' => ['nullable', 'string'],
            'job_title'       => ['nullable', 'string', 'max:120'],
            'max_uses'        => ['nullable', 'integer', 'min:1', 'max:1000'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $departmentId = null;
        if (! empty($data['department_uuid'])) {
            $department = $hotel->departments()->where('uuid', $data['department_uuid'])->first();
            if (! $department) {
                return $this->notFound('Setor não encontrado.');
            }
            $departmentId = $department->id;
        }

        $invite = CompanyInvite::create([
            'uuid'          => (string) Str::uuid(),
            'hotel_id'      => $hotel->id,
            'created_by'    => $request->user()->id,
            'token'         => Str::random(40),
            'email'         => isset($data['email']) ? Str::lower($data['email']) : null,
            'department_id' => $departmentId,
            'job_title'     => $data['job_title'] ?? null,
            'max_uses'      => $data['max_uses'] ?? (isset($data['email']) ? 1 : 50),
            'expires_at'    => now()->addDays($data['expires_in_days'] ?? 14),
        ]);

        return $this->created('Convite criado com sucesso.', $this->format($invite->load('department')));
    }

    /** DELETE /workspaces/{hotel:slug}/invites/{invite} — revoga. */
    public function destroy(Request $request, Hotel $hotel, CompanyInvite $invite): JsonResponse
    {
        if ($denied = $this->denyUnlessAdmin($request, $hotel)) {
            return $denied;
        }

        if ($invite->hotel_id !== $hotel->id) {
            return $this->notFound('Convite não encontrado.');
        }

        $invite->update(['revoked_at' => now()]);

        return $this->success('Convite revogado.');
    }

    /** GET /invites/{token} — pública: mostra a empresa do convite antes de entrar/criar conta. */
    public function preview(string $token): JsonResponse
    {
        $invite = CompanyInvite::with('hotel')->where('token', $token)->first();

        if (! $invite || ! $invite->isUsable() || ! $invite->hotel) {
            return $this->notFound('Convite inválido ou expirado.');
        }

        return $this->success('Convite válido.', [
            'company' => [
                'name'       => $invite->hotel->name,
                'segment'    => $invite->hotel->segment,
                'avatar_url' => $invite->hotel->logo_path ? asset('storage/' . $invite->hotel->logo_path) : null,
            ],
            'job_title'    => $invite->job_title,
            'email_locked' => (bool) $invite->email,
        ]);
    }

    /** POST /invites/{token}/accept — usuário logado entra na empresa. */
    public function accept(Request $request, string $token): JsonResponse
    {
        $user = $request->user();

        $result = DB::transaction(function () use ($token, $user) {
            $invite = CompanyInvite::with('hotel')->where('token', $token)->lockForUpdate()->first();

            if (! $invite || ! $invite->isUsable() || ! $invite->hotel) {
                return ['error' => $this->notFound('Convite inválido ou expirado.')];
            }

            if ($invite->email && Str::lower($user->email) !== $invite->email) {
                return ['error' => $this->forbidden('Este convite é destinado a outro e-mail.')];
            }

            $existing = HotelUser::where('hotel_id', $invite->hotel_id)->where('user_id', $user->id)->first();

            if ($existing && $existing->status === 'approved') {
                return ['hotel' => $invite->hotel, 'already' => true];
            }

            $attributes = [
                'role'          => 'employee',
                'status'        => 'approved',
                'department_id' => $invite->department_id,
                'job_title'     => $invite->job_title,
            ];

            $existing
                ? $existing->update($attributes)
                : HotelUser::create($attributes + ['hotel_id' => $invite->hotel_id, 'user_id' => $user->id]);

            $invite->increment('uses');

            return ['hotel' => $invite->hotel, 'already' => false];
        });

        if (isset($result['error'])) {
            return $result['error'];
        }

        return $this->success(
            $result['already'] ? 'Você já faz parte desta empresa.' : 'Bem-vindo(a) à empresa!',
            ['slug' => $result['hotel']->slug, 'name' => $result['hotel']->name],
        );
    }

    private function denyUnlessAdmin(Request $request, Hotel $hotel): ?JsonResponse
    {
        return $this->access->isAdmin($this->access->membership($request->user(), $hotel))
            ? null
            : $this->forbidden('Somente administradores da empresa podem fazer isso.');
    }

    private function format(CompanyInvite $invite): array
    {
        return [
            'uuid'       => $invite->uuid,
            'token'      => $invite->token,
            'path'       => '/convite/' . $invite->token,
            'email'      => $invite->email,
            'department' => $invite->department?->name,
            'job_title'  => $invite->job_title,
            'max_uses'   => $invite->max_uses,
            'uses'       => $invite->uses,
            'expires_at' => $invite->expires_at?->toISOString(),
            'revoked'    => (bool) $invite->revoked_at,
            'usable'     => $invite->isUsable(),
        ];
    }
}
