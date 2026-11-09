<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fluxo de aprovação configurado pela empresa, por tipo de solicitação (ex.: reembolso = gestor → financeiro).
        Schema::create('request_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->json('steps');                       // ["manager","finance",...] em ordem
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hotel_id', 'type']);
        });

        // Etapas de UMA solicitação (cópia do fluxo no momento do pedido: mudar o fluxo depois não mexe nas em andamento).
        Schema::create('workspace_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('role', 10);                  // manager | hr | finance | director
            $table->string('status', 10)->default('waiting'); // waiting | pending | approved | rejected | skipped
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['workspace_request_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_request_approvals');
        Schema::dropIfExists('request_flows');
    }
};
