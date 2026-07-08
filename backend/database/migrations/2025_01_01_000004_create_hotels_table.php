<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requirements: 3.1, 3.2, 3.6
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('website')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['city', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
