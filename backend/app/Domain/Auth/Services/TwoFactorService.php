<?php

namespace App\Domain\Auth\Services;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Verificação em duas etapas (TOTP) com códigos de recuperação de uso único. */
class TwoFactorService
{
    private const RECOVERY_CODES = 10;

    public function issuer(): string
    {
        return (string) config('app.name', 'Workspace');
    }

    private function hashRecovery(string $code): string
    {
        return hash_hmac('sha256', strtolower(preg_replace('/[^A-Za-z0-9]/', '', $code)), (string) config('app.key'));
    }

    /** Gera um segredo novo ainda NÃO confirmado (substitui um anterior não confirmado). */
    public function setup(User $user): array
    {
        if ($user->hasTwoFactor()) {
            throw ValidationException::withMessages(['code' => ['A verificação em duas etapas já está ativa. Desative antes de configurar de novo.']]);
        }
        $secret = Totp::generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret, 'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null, 'two_factor_last_step' => null,
        ])->save();

        return ['secret' => $secret, 'uri' => Totp::uri($secret, $user->email, $this->issuer())];
    }

    /** @return string[] códigos de recuperação em texto claro (mostrados uma única vez) */
    public function enable(User $user, string $code): array
    {
        $this->throttle($user);
        $step = $user->two_factor_secret ? Totp::verify($user->two_factor_secret, $code) : null;
        if ($step === null) {
            throw ValidationException::withMessages(['code' => ['Código inválido. Confira o horário do celular e tente de novo.']]);
        }
        RateLimiter::clear($this->key($user));

        $codes = $this->newRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step,
            'two_factor_recovery_codes' => array_map(fn ($c) => $this->hashRecovery($c), $codes),
        ])->save();

        return $codes;
    }

    /** @return string[] */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODES))
            ->map(fn () => strtolower(Str::random(5) . '-' . Str::random(5)))->all();
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => $this->hashRecovery($c), $codes)])->save();

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null, 'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null, 'two_factor_last_step' => null,
        ])->save();
    }

    public function recoveryLeft(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    private function key(User $user): string
    {
        return '2fa:' . $user->id;
    }

    /** No máximo 5 tentativas por minuto por conta (além do limite por IP do login). */
    private function throttle(User $user): void
    {
        if (RateLimiter::tooManyAttempts($this->key($user), app()->environment('testing') ? 1000 : 5)) {
            throw ValidationException::withMessages(['code' => ['Muitas tentativas. Aguarde um minuto e tente de novo.']]);
        }
        RateLimiter::hit($this->key($user), 60);
    }

    /** Confere o código do app (uma vez só por passo) ou um código de recuperação (consumido). */
    public function verifyLogin(User $user, ?string $code, ?string $recovery): bool
    {
        $this->throttle($user);

        if ($code) {
            $step = $user->two_factor_secret ? Totp::verify($user->two_factor_secret, $code) : null;
            if ($step !== null && ($user->two_factor_last_step === null || $step > $user->two_factor_last_step)) {
                $user->forceFill(['two_factor_last_step' => $step])->save();
                RateLimiter::clear($this->key($user));

                return true;
            }

            return false;
        }

        if ($recovery) {
            $hash  = $this->hashRecovery($recovery);
            $codes = $user->two_factor_recovery_codes ?? [];
            $idx   = array_search($hash, $codes, true);
            if ($idx !== false) {
                unset($codes[$idx]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                RateLimiter::clear($this->key($user));

                return true;
            }
        }

        return false;
    }
}
