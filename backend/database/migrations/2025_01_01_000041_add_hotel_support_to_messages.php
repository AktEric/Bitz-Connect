<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite que um Hotel seja um dos lados de uma conversa — como uma caixa de
 * entrada compartilhada da empresa, não vinculada a um admin específico.
 *
 * `sender_id`/`receiver_id` continuam apontando para o usuário real que
 * digitou/deveria receber (auditoria e compatibilidade). Os campos novos
 * marcam quando a mensagem foi enviada "como o hotel" ou endereçada à caixa
 * compartilhada do hotel (sem destinatário individual):
 *
 * - sender_hotel_id preenchido   → a mensagem foi enviada em nome do hotel
 *   (sender_id continua sendo o admin que digitou, para auditoria).
 * - receiver_hotel_id preenchido → a mensagem é para a caixa compartilhada
 *   do hotel; nesse caso receiver_id fica nulo (nenhum admin específico é o
 *   destinatário — qualquer admin aprovado pode ver/responder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('sender_hotel_id')->nullable()->after('sender_id')
                ->constrained('hotels')->nullOnDelete();
            $table->foreignId('receiver_hotel_id')->nullable()->after('receiver_id')
                ->constrained('hotels')->nullOnDelete();

            $table->index(['receiver_hotel_id', 'created_at']);
            $table->index(['sender_hotel_id', 'created_at']);
        });

        // receiver_id deixa de ser obrigatório: mensagens endereçadas à caixa
        // compartilhada do hotel não têm um destinatário individual.
        DB::statement('ALTER TABLE messages ALTER COLUMN receiver_id DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sender_hotel_id');
            $table->dropConstrainedForeignId('receiver_hotel_id');
        });

        DB::statement('ALTER TABLE messages ALTER COLUMN receiver_id SET NOT NULL');
    }
};
