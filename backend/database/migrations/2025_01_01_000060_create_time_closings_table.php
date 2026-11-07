<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_closings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);                 // YYYY-MM
            $table->json('rows');                       // retrato do fechamento, por pessoa
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();

            $table->unique(['hotel_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_closings');
    }
};
