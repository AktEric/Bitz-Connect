<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Publicações do perfil oficial da empresa (parabéns, boas-vindas, avisos de admin): o autor exibido é a empresa.
        Schema::table('workspace_posts', fn (Blueprint $table) => $table->boolean('as_company')->default(false));
        DB::table('workspace_posts')->where('kind', 'birthday')->update(['as_company' => true]);
    }

    public function down(): void
    {
        Schema::table('workspace_posts', fn (Blueprint $table) => $table->dropColumn('as_company'));
    }
};
