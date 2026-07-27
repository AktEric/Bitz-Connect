<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imagem de fundo opcional do story (upload real, evita cards genéricos).
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('caption');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
