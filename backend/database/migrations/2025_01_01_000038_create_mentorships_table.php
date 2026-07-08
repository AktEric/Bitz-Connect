<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mentorias oferecidas por Consultores PRO (§11).
 * price_cents preparado para monetização; no MVP a solicitação é gratuita.
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentorships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->string('session_type', 20)->default('online'); // online | presential
            $table->unsignedInteger('price_cents')->default(0);     // preparado
            $table->string('status', 20)->default('draft');          // draft | published
            $table->unsignedBigInteger('bookings_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorships');
    }
};
