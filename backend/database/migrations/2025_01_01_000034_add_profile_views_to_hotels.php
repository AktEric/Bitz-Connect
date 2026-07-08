<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contador de visualizações do perfil do hotel (métrica base do dashboard PRO).
 * Sprint: monetization-hotelconnect-pro (Empresa PRO — Analytics §7/§8)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_views')->default(0)->after('followers_count');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('profile_views');
        });
    }
};
