<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds PostgreSQL GIN full-text search indexes to hotels, jobs, and users.
 *
 * Uses `unaccent` via a custom text-search configuration so accented
 * characters (á, ã, é, etc.) are treated the same as their plain equivalents.
 *
 * Requirements: 6.1, 6.2, 6.3
 */
return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL requires index expression functions to be IMMUTABLE.
        // unaccent() is only STABLE, so we create an IMMUTABLE wrapper once
        // per database. The wrapper calls unaccent() in a way that PostgreSQL
        // trusts as immutable (the unaccent extension must already be enabled).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION immutable_unaccent(text)
            RETURNS text AS $$
                SELECT unaccent($1);
            $$ LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT;
        SQL);

        // Hotels: search on name, description, city, state
        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS hotels_fts_idx
            ON hotels
            USING gin(
                to_tsvector(
                    'portuguese',
                    immutable_unaccent(coalesce(name, ''))
                    || ' ' || immutable_unaccent(coalesce(description, ''))
                    || ' ' || immutable_unaccent(coalesce(city, ''))
                    || ' ' || immutable_unaccent(coalesce(state, ''))
                )
            )
        SQL);

        // Jobs: search on title and description (active-status filtering is done at query time)
        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS jobs_fts_idx
            ON jobs
            USING gin(
                to_tsvector(
                    'portuguese',
                    immutable_unaccent(coalesce(title, ''))
                    || ' ' || immutable_unaccent(coalesce(description, ''))
                )
            )
        SQL);

        // Users: search on name
        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS users_fts_idx
            ON users
            USING gin(
                to_tsvector(
                    'portuguese',
                    immutable_unaccent(coalesce(name, ''))
                )
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS hotels_fts_idx');
        DB::statement('DROP INDEX IF EXISTS jobs_fts_idx');
        DB::statement('DROP INDEX IF EXISTS users_fts_idx');
        DB::statement('DROP FUNCTION IF EXISTS immutable_unaccent(text)');
    }
};
