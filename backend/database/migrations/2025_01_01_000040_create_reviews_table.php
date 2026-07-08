<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avaliações polimórficas (§6/§11) — aplicáveis a criadores (User), cursos e
 * mentorias. Uma avaliação por usuário por alvo (pode ser atualizada).
 * Sprint: monetization-hotelconnect-pro (Avaliações)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('reviewable'); // reviewable_type, reviewable_id
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->unique(['reviewable_type', 'reviewable_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
