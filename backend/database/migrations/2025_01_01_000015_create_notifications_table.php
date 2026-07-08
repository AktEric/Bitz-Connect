<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications table — stores in-app notifications for users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Type: connection_invite, connection_accepted, new_job, job_application,
            //        post_like, post_comment, hotel_linked
            $table->string('type', 50);

            $table->string('title');
            $table->string('description')->nullable();

            // Optional reference to related resource
            $table->string('link')->nullable();

            $table->boolean('read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'read']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
