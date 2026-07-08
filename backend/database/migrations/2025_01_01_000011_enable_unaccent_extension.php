<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enables the PostgreSQL unaccent extension for accent-insensitive full-text search.
 * Requirements: 6.1, 6.2, 6.3
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS unaccent');
    }
};
