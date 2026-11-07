<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fotos do mural: guardadas em disco PRIVADO e entregues por URL assinada de curta duração.
        Schema::create('workspace_post_media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_post_id')->constrained()->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('mime', 60);
            $table->unsignedInteger('size');
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index(['workspace_post_id', 'position']);
        });

        // Reações: like | love | celebrate | clap (uma por pessoa por publicação).
        Schema::table('workspace_post_likes', function (Blueprint $table) {
            $table->string('reaction', 12)->default('like');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_post_likes', fn (Blueprint $table) => $table->dropColumn('reaction'));
        Schema::dropIfExists('workspace_post_media');
    }
};
