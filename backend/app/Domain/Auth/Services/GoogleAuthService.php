<?php

namespace App\Domain\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Login/cadastro com Google via ID token (Google Identity Services).
 * O token é validado no endpoint oficial do Google e só é aceito se for para o NOSSO client id
 * e se o e-mail estiver verificado.
 */
class GoogleAuthService
{
    public function clientId(): ?string
    {
        $id = config('services.google.client_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** @return array{user: User, token: string, created: bool} */
    public function authenticate(string $credential): array
    {
        $clientId = $this->clientId();
        if (! $clientId) {
            throw new RuntimeException('Login com Google não está configurado.');
        }

        $res = Http::timeout(8)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $credential]);
        $claims = $res->successful() ? $res->json() : null;

        $valid = is_array($claims)
            && ($claims['aud'] ?? null) === $clientId
            && in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
            && filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && ! empty($claims['email'])
            && (int) ($claims['exp'] ?? 0) > time();

        if (! $valid) {
            throw ValidationException::withMessages(['credential' => ['Não foi possível validar sua conta Google.']]);
        }

        $email = mb_strtolower($claims['email']);
        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        $created = false;

        if (! $user) {
            $user = User::create([
                'uuid'     => (string) Str::uuid(),
                'name'     => $claims['name'] ?? Str::before($email, '@'),
                'email'    => $email,
                'password' => Str::random(40), // conta social: sem senha utilizável (pode definir via "esqueci a senha")
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $created = true;
        }

        return ['user' => $user, 'token' => app(AuthService::class)->issueToken($user), 'created' => $created];
    }
}
