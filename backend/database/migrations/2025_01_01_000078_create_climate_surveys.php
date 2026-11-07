<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('climate_surveys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->string('status', 10)->default('open'); // open | closed
            $table->date('closes_on')->nullable();
            $table->timestamps();
            $table->index(['hotel_id', 'status']);
        });

        // Quem já respondeu (só a data, sem hora) — separado das respostas de propósito.
        Schema::create('climate_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('climate_survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('answered_on');
            $table->unique(['climate_survey_id', 'user_id']);
        });

        // As respostas NÃO guardam user_id nem hora: não há como ligar uma resposta a uma pessoa.
        Schema::create('climate_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('climate_survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->json('scores');
            $table->text('comment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('climate_answers');
        Schema::dropIfExists('climate_participations');
        Schema::dropIfExists('climate_surveys');
    }
};
