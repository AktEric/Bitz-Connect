<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stories de destaque (vaga ou mentoria) publicados por contas PRO.
 * MVP sem expiração: o story fica ativo até o dono removê-lo (soft delete).
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('story_type', 20); // job | mentorship
            $table->foreignId('job_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('mentorship_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('caption', 280)->nullable();

            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['story_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
