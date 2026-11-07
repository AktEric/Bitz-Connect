<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Services\TwoFactorService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/** Segurança da conta: verificação em duas etapas e sessões (aparelhos conectados). */
class AccountSecurityController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly TwoFactorService $twoFactor) {}

    private function sessions(Request $request): array
    {
        $currentId = $request->user()->currentAccessToken()?->id;

        return $request->user()->tokens()->orderByDesc('last_used_at')->orderByDesc('id')->get()->map(fn (PersonalAccessToken $t) => [
            'id'           => $t->id,
            'label'        => $t->label ?: 'Aparelho desconhecido',
            'ip'           => $t->ip,
            'created_at'   => $t->created_at?->toISOString(),
            'last_used_at' => $t->last_used_at?->toISOString(),
            'current'      => $t->id === $currentId,
        ])->all();
    }

    /** GET /auth/security */
    public function overview(Request $request): JsonResponse
    {
        $u = $request->user();

        return $this->success('Segurança da conta carregada.', [
            'two_factor' => [
                'enabled'        => $u->hasTwoFactor(),
                'confirmed_at'   => $u->two_factor_confirmed_at?->toISOString(),
                'recovery_left'  => $this->twoFactor->recoveryLeft($u),
            ],
            'sessions' => $this->sessions($request),
        ]);
    }

    /** POST /auth/2fa/setup — gera o segredo (ainda inativo) e o link otpauth para o QR code. */
    public function setup(Request $request): JsonResponse
    {
        return $this->success('Segredo gerado. Confirme com o primeiro código do aplicativo.', $this->twoFactor->setup($request->user()));
    }

    /** POST /auth/2fa/enable  { code } */
    public function enable(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $codes = $this->twoFactor->enable($request->user(), $data['code']);

        return $this->success('Verificação em duas etapas ativada. Guarde os códigos de recuperação.', ['recovery_codes' => $codes]);
    }

    /** Exige a senha e um código válido (do app ou de recuperação) antes de mexer na proteção. */
    private function confirmIdentity(Request $request): void
    {
        $data = $request->validate([
            'password'      => ['required', 'string'],
            'code'          => ['nullable', 'string', 'max:12'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ]);
        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => ['Senha incorreta.']]);
        }
        if (! $request->user()->hasTwoFactor() || ! $this->twoFactor->verifyLogin($request->user(), $data['code'] ?? null, $data['recovery_code'] ?? null)) {
            throw ValidationException::withMessages(['code' => ['Código inválido ou expirado.']]);
        }
    }

    /** POST /auth/2fa/disable  { password, code|recovery_code } */
    public function disable(Request $request): JsonResponse
    {
        $this->confirmIdentity($request);
        $this->twoFactor->disable($request->user());

        return $this->success('Verificação em duas etapas desativada.');
    }

    /** POST /auth/2fa/recovery-codes  { password, code|recovery_code } — gera novos e invalida os antigos. */
    public function regenerate(Request $request): JsonResponse
    {
        $this->confirmIdentity($request);

        return $this->success('Novos códigos de recuperação gerados.', ['recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($request->user())]);
    }

    /** DELETE /auth/sessions/{id} — encerra uma sessão (pode ser a atual). */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $id)->delete();

        return $deleted ? $this->success('Sessão encerrada.') : $this->notFound('Sessão não encontrada.');
    }

    /** POST /auth/sessions/revoke-others — encerra todas as sessões, menos esta. */
    public function revokeOthers(Request $request): JsonResponse
    {
        $count = $request->user()->tokens()->where('id', '!=', $request->user()->currentAccessToken()?->id)->delete();

        return $this->success("{$count} sessão(ões) encerrada(s).", ['revoked' => $count]);
    }
}
