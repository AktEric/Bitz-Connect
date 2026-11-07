<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Perguntas de cada pesquisa (editáveis). Nulo = perguntas padrão (pesquisas antigas).
        Schema::table('climate_surveys', function (Blueprint $table) {
            $table->json('questions')->nullable()->after('closes_on');
        });
    }

    public function down(): void
    {
        Schema::table('climate_surveys', fn (Blueprint $table) => $table->dropColumn('questions'));
    }
};
