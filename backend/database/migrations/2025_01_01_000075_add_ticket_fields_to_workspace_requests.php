<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chamados ao RH: categoria, quem atende, prazo (SLA) e quando houve o primeiro retorno.
        Schema::table('workspace_requests', function (Blueprint $table) {
            $table->string('category', 20)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspace_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['category', 'due_at', 'first_response_at']);
        });
    }
};
