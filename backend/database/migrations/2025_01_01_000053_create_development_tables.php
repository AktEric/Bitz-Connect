<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** PDI — Plano de Desenvolvimento Individual. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->text('objective')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status', 20)->default('active'); // active|completed|archived
            $table->timestamps();

            $table->index(['hotel_id', 'user_id']);
        });

        Schema::create('development_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('development_plan_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('type', 20)->default('other');   // course|mentoring|project|reading|other
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('todo');  // todo|doing|done
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('development_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('development_notes');
        Schema::dropIfExists('development_items');
        Schema::dropIfExists('development_plans');
    }
};
