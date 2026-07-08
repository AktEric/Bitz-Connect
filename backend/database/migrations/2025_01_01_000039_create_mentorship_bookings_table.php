<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitações/agendamentos de mentoria (gratuitas no MVP).
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentorship_bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('mentorship_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->text('message')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 20)->default('requested'); // requested | accepted | declined | completed

            $table->timestamps();

            $table->index(['mentorship_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorship_bookings');
    }
};
