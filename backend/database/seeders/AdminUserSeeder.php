<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cria (ou reaproveita) a conta administrativa da plataforma.
 *
 * - Fora de produção: admin@admin.com / admin (apenas para desenvolvimento).
 * - EM PRODUÇÃO: exige ADMIN_EMAIL e ADMIN_PASSWORD (mínimo de 12 caracteres) no ambiente;
 *   sem eles o seeder aborta, para nunca existir uma conta com senha conhecida.
 */
class AdminUserSeeder extends Seeder
{
    public const EMAIL    = 'admin@admin.com';
    public const PASSWORD = 'admin';

    public function run(): void
    {
        $email    = self::EMAIL;
        $password = self::PASSWORD;

        if (app()->environment('production')) {
            $email    = (string) env('ADMIN_EMAIL');
            $password = (string) env('ADMIN_PASSWORD');
            if ($email === '' || strlen($password) < 12) {
                throw new \RuntimeException('Em produção defina ADMIN_EMAIL e ADMIN_PASSWORD (mínimo de 12 caracteres) antes de rodar este seeder.');
            }
        }

        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'uuid'     => (string) Str::uuid(),
                'name'     => 'Admin',
                'password' => Hash::make($password),
            ],
        );

        // `role` não é atribuível em massa (evita escalada de privilégio): definido explicitamente aqui.
        $user->forceFill(['role' => 'platform_admin', 'email_verified_at' => now(), 'deleted_at' => null])->save();
    }
}
