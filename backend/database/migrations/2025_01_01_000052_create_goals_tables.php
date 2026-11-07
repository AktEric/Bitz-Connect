<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Metas individuais com acompanhamento do gestor. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('unit', 30)->default('%');
            $table->decimal('target_value', 14, 2);
            $table->decimal('current_value', 14, 2)->default(0);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('active'); // active|achieved|missed|cancelled
            $table->timestamps();

            $table->index(['hotel_id', 'owner_id', 'status']);
        });

        Schema::create('goal_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('value', 14, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_updates');
        Schema::dropIfExists('goals');
    }
};
