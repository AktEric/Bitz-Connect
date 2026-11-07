<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_users', function (Blueprint $table) {
            $table->timestamp('punch_consent_at')->nullable(); // aceite para tratar foto e localização do ponto
        });
    }

    public function down(): void
    {
        Schema::table('hotel_users', fn (Blueprint $table) => $table->dropColumn('punch_consent_at'));
    }
};
