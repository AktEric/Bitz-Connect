<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comentários de vídeo (tabela dedicada).
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['video_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_comments');
    }
};
