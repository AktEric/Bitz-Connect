<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requirements: 8.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['hotel_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_followers');
    }
};
