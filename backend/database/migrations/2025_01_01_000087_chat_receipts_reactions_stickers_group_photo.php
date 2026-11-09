<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enviado / entregue / visualizado (estilo WhatsApp) e a opção de desligar a confirmação de leitura.
        Schema::table('messages', fn (Blueprint $table) => $table->timestamp('delivered_at')->nullable());
        Schema::table('users', fn (Blueprint $table) => $table->boolean('chat_read_receipts')->default(true));

        // Foto do grupo (disco público, como os avatares).
        Schema::table('chat_groups', fn (Blueprint $table) => $table->string('avatar_path')->nullable());

        // Reações às mensagens (conversa direta e grupo): uma por pessoa por mensagem.
        Schema::create('chat_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->string('message_kind', 6);               // direct | group
            $table->unsignedBigInteger('message_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();

            $table->unique(['message_kind', 'message_id', 'user_id']);
            $table->index(['message_kind', 'message_id']);
        });

        // Figurinhas enviadas pelos usuários; cada pessoa guarda as suas (e as que salvou de outros).
        Schema::create('stickers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');                          // disco privado
            $table->string('mime', 30);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
        Schema::create('user_stickers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sticker_id')->constrained('stickers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'sticker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stickers');
        Schema::dropIfExists('stickers');
        Schema::dropIfExists('chat_message_reactions');
        Schema::table('chat_groups', fn (Blueprint $table) => $table->dropColumn('avatar_path'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('chat_read_receipts'));
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn('delivered_at'));
    }
};
