<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ciência/assinatura do colaborador no documento: desenhada (PNG privado) ou digitada (nome).
        Schema::table('payslips', function (Blueprint $table) {
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_kind', 10)->nullable();   // drawn | typed
            $table->string('signature_name', 160)->nullable();
            $table->string('signature_path')->nullable();       // PNG da assinatura desenhada, em disco privado
            $table->string('signature_hash', 64)->nullable();   // sha256(documento + pessoa + momento + assinatura)
            $table->string('signed_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payslips', fn (Blueprint $table) => $table->dropColumn(['signed_at', 'signature_kind', 'signature_name', 'signature_path', 'signature_hash', 'signed_ip']));
    }
};
