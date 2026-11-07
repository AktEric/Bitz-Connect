<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            // minutos esperados por dia da semana: chaves 0 (domingo) a 6 (sábado)
            $table->json('week_minutes');
            $table->timestamps();

            $table->unique(['hotel_id', 'name']);
        });

        Schema::table('hotel_users', function (Blueprint $table) {
            $table->foreignId('work_schedule_id')->nullable()->constrained('work_schedules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_schedule_id');
        });
        Schema::dropIfExists('work_schedules');
    }
};
