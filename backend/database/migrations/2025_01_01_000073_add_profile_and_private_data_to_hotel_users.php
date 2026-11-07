<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_users', function (Blueprint $table) {
            $table->text('private_data')->nullable(); // ficha cadastral (RH preenche), criptografada
            $table->json('profile')->nullable();      // perfil social (a própria pessoa preenche)
        });
    }

    public function down(): void
    {
        Schema::table('hotel_users', fn (Blueprint $table) => $table->dropColumn(['private_data', 'profile']));
    }
};
