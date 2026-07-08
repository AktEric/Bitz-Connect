<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de criador: especialidade (headline pública) e contador de seguidores.
 * Sprint: monetization-hotelconnect-pro (Página do Criador)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('specialty', 120)->nullable()->after('bio');
            $table->unsignedBigInteger('followers_count')->default(0)->after('specialty');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['specialty', 'followers_count']);
        });
    }
};
