<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\DTOs\LoginDTO;
use App\Domain\Auth\DTOs\RegisterUserDTO;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Handles all authentication business logic.
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8
 */
class AuthService
{
    /**
     * Register a new user and return a Sanctum token.
     * Requirements: 1.1
     */
    public function register(RegisterUserDTO $dto): array
    {
        $user = User::create([
            'uuid'     => (string) Str::uuid(),
            'name'     => $dto->name,
            'email'    => $dto->email,
            'password' => $dto->password, // auto-hashed via cast
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return compact('user', 'token');
    }

    /**
     * Authenticate user credentials and return a Sanctum token.
     * Requirements: 1.3, 1.4
     */
    public function login(LoginDTO $dto): array
    {
        $user = User::where('email', $dto->email)->first();

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'credentials' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return compact('user', 'token');
    }

    /**
     * Revoke the current user's token.
     * Requirements: 1.5
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Send a password reset link to the given email.
     * Requirements: 1.6
     */
    public function forgotPassword(string $email): void
    {
        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    /**
     * Reset the user's password using the provided token.
     * Requirements: 1.7
     */
    public function resetPassword(string $token, string $email, string $password): void
    {
        $status = Password::reset(
            [
                'token'    => $token,
                'email'    => $email,
                'password' => $password,
            ],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])
                    ->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => [__($status)],
            ]);
        }
    }

    /**
     * Change the authenticated user's password and revoke all tokens.
     * Requirements: 1.8
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['A senha atual está incorreta.'],
            ]);
        }

        $user->forceFill(['password' => $newPassword])->save();

        // Revoke all tokens (invalidate all active sessions)
        $user->tokens()->delete();
    }
}
