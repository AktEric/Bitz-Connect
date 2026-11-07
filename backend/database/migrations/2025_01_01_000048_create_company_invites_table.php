<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Convites por link para funcionários entrarem no Workspace da empresa. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_invites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->string('email')->nullable();            // se informado, só esse e-mail aceita
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('job_title', 120)->nullable();
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('hotel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_invites');
    }
};
