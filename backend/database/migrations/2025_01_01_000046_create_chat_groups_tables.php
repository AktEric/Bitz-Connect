<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grupos de chat corporativo. Independente da tabela `messages` (chat 1:1 e
 * caixa compartilhada do hotel).
 *
 * - only_admins_can_post: "modo aviso" — só administradores enviam mensagens.
 * - chat_group_members.role: admin | member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('only_admins_can_post')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('hotel_id');
        });

        Schema::create('chat_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('member'); // admin | member
            $table->boolean('notifications_muted')->default(false);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_group_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('chat_group_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('chat_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->string('type', 20)->default('text');
            $table->json('metadata')->nullable();
            $table->boolean('is_announcement')->default(false);
            $table->timestamps();

            $table->index(['chat_group_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_group_messages');
        Schema::dropIfExists('chat_group_members');
        Schema::dropIfExists('chat_groups');
    }
};
