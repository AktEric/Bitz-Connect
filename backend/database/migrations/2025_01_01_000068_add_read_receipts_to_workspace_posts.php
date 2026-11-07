<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_posts', function (Blueprint $table) {
            // Comunicado oficial: cada pessoa do público precisa confirmar a leitura ("li e estou ciente").
            $table->boolean('requires_ack')->default(false);
        });

        Schema::create('workspace_post_acks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('acked_at');
            $table->string('ip', 45)->nullable();

            $table->unique(['workspace_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_post_acks');
        Schema::table('workspace_posts', fn (Blueprint $table) => $table->dropColumn('requires_ack'));
    }
};
