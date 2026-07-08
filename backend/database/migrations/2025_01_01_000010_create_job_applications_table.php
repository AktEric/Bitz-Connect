<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requirements: 5.5, 5.7, 5.8, 5.9
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('cover_letter')->nullable();
            $table->enum('status', ['pending', 'viewed', 'rejected', 'hired'])->default('pending');
            $table->timestamps();

            // Prevent duplicate applications
            $table->unique(['job_id', 'user_id'], 'job_applications_job_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
