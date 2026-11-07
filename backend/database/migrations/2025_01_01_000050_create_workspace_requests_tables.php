<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Solicitações do funcionário ao RH/gestor (férias, documentos, reembolso...). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);                       // vacation|document|reimbursement|schedule|other
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|cancelled
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'status', 'created_at']);
            $table->index(['requester_id', 'created_at']);
        });

        Schema::create('workspace_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_request_comments');
        Schema::dropIfExists('workspace_requests');
    }
};
