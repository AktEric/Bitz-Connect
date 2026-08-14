<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cria (ou reaproveita) a única conta administrativa da plataforma.
 * Usado depois de um `migrate:fresh` para deixar a base limpa, com um único
 * acesso: admin@admin.com / admin.
 */
class AdminUserSeeder extends Seeder
{
    public const EMAIL    = 'admin@admin.com';
    public const PASSWORD = 'admin';

    public function run(): void
    {
        User::withTrashed()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'uuid'              => (string) Str::uuid(),
                'name'              => 'Admin',
                'password'          => Hash::make(self::PASSWORD),
                'role'              => 'platform_admin',
                'email_verified_at' => now(),
                'deleted_at'        => null,
            ],
        );
    }
}
