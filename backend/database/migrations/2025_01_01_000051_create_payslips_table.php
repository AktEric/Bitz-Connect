<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Recebimentos: holerites/comprovantes anexados pelo RH, em disco privado. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();       // dono do documento
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20)->default('payslip');                       // payslip|receipt|bonus|other
            $table->string('title', 160);
            $table->date('reference_month')->nullable();
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'user_id', 'reference_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
