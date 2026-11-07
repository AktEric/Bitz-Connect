<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setores da empresa (Recepção, Governança, RH...). Base para grupos de chat
 * por setor e, nas próximas fases, RH/financeiro/aprovações.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['hotel_id', 'name']);
        });

        Schema::table('hotel_users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('status')
                ->constrained('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
