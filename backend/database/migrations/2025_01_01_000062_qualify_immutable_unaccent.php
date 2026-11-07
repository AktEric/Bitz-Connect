<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Qualifica a chamada a unaccent() com o schema. Sem isso, ao restaurar um backup (pg_restore roda com
 * search_path vazio) a criação dos índices de busca falha com "function unaccent(text) does not exist"
 * e os índices full-text de usuários, empresas e vagas não voltam.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION immutable_unaccent(text)
            RETURNS text AS $$
                SELECT public.unaccent($1);
            $$ LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT;
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION immutable_unaccent(text)
            RETURNS text AS $$
                SELECT unaccent($1);
            $$ LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT;
        SQL);
    }
};
