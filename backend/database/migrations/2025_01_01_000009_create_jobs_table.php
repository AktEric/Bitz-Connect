<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requirements: 5.1, 5.2, 5.6
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->text('benefits')->nullable();
            $table->enum('contract_type', ['clt', 'pj', 'freelance', 'internship']);
            $table->enum('modality', ['on_site', 'remote', 'hybrid']);
            $table->string('city');
            $table->string('state', 2);
            $table->string('salary_range')->nullable();
            $table->enum('status', ['active', 'paused', 'closed'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['hotel_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['city', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
