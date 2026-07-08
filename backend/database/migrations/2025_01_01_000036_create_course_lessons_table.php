<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aulas de um curso. No MVP o vídeo é referenciado por link (video_url).
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->text('content')->nullable();          // texto/markdown da aula
            $table->string('video_url')->nullable();      // link do vídeo (MVP)
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->timestamps();

            $table->index(['course_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lessons');
    }
};
