<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cursos publicados por Consultores PRO (§11).
 * price_cents fica preparado para monetização futura — no MVP a matrícula é livre.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('category', 50)->nullable();
            $table->string('level', 20)->nullable(); // beginner | intermediate | advanced

            $table->unsignedInteger('price_cents')->default(0); // preparado (sem cobrança no MVP)
            $table->string('status', 20)->default('draft');     // draft | published

            $table->unsignedInteger('lessons_count')->default(0);
            $table->unsignedBigInteger('enrollments_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
