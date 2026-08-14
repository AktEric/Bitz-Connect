<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Base limpa: apenas a conta administrativa. Os dados de demonstração
        // vivem no DemoDataSeeder, que deve ser chamado explicitamente.
        $this->call(AdminUserSeeder::class);
    }
}
