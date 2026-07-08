<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curtidas de vídeo (tabela dedicada — não reutiliza likes de posts).
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['video_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_likes');
    }
};
