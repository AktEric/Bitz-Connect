<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vídeos profissionais publicados por Consultores PRO.
 * Contadores desnormalizados já preparam curtidas/comentários/compartilhamentos
 * (tabelas dedicadas virão numa próxima etapa).
 *
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('video_path');
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->string('category', 50)->nullable();
            $table->json('tags')->nullable();

            // Preparados para evolução (privacidade/processamento assíncrono)
            $table->string('visibility', 20)->default('public'); // public | unlisted | private
            $table->string('status', 20)->default('published');  // processing | published

            // Contadores desnormalizados
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('comments_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'visibility', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
