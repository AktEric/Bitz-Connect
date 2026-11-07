<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vagas abertas da própria empresa: para o time interno se candidatar e para divulgar por link (WhatsApp etc.).
        Schema::create('workspace_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->string('location', 120)->nullable();
            $table->string('contract_type', 30)->nullable();      // CLT, PJ, estágio, temporário…
            $table->string('contact', 120)->nullable();           // e-mail/WhatsApp para candidatos de fora
            $table->string('status', 10)->default('open');        // open | closed
            $table->boolean('public')->default(false);            // divulgação por link público
            $table->string('share_token', 40)->unique();
            $table->date('closes_on')->nullable();
            $table->timestamps();
            $table->index(['hotel_id', 'status']);
        });

        Schema::create('workspace_job_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_job_id')->constrained('workspace_jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('status', 12)->default('new');         // new | reviewing | rejected | hired
            $table->timestamps();
            $table->unique(['workspace_job_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_job_applications');
        Schema::dropIfExists('workspace_jobs');
    }
};
