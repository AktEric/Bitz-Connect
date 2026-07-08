<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguidores de criador (creator economy): follower_id segue creator_id.
 * Espelha o padrão de hotel_followers.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos / Página do Criador)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['creator_id', 'follower_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_followers');
    }
};
