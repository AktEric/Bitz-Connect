<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trajetória da pessoa DENTRO da empresa: um registro por cargo/período. `ended_on` nulo = cargo atual.
        Schema::create('career_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('department', 80)->nullable();   // texto livre: o setor da época pode nem existir mais
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->string('note', 300)->nullable();
            $table->string('source', 10)->default('manual'); // manual | job (contratação por vaga interna)
            $table->timestamps();

            $table->index(['hotel_id', 'user_id', 'started_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('career_entries');
    }
};
