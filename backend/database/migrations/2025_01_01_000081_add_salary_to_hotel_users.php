<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Salário mensal bruto, criptografado em repouso. Só a própria pessoa lê; o RH apenas grava.
        Schema::table('hotel_users', fn (Blueprint $table) => $table->text('salary')->nullable());
    }

    public function down(): void
    {
        Schema::table('hotel_users', fn (Blueprint $table) => $table->dropColumn('salary'));
    }
};
