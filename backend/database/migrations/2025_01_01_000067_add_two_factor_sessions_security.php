<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();               // criptografado (cast encrypted)
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->json('two_factor_recovery_codes')->nullable();       // só hashes (HMAC); o código aparece uma vez
            $table->unsignedBigInteger('two_factor_last_step')->nullable(); // impede reutilizar o mesmo código TOTP
        });

        // Sessões: de onde e com qual aparelho cada token foi criado.
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('ip', 45)->nullable();
            $table->string('label', 120)->nullable();
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->json('security_settings')->nullable();               // { require_2fa: bool }
        });
    }

    public function down(): void
    {
        Schema::table('hotels', fn (Blueprint $table) => $table->dropColumn('security_settings'));
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->dropColumn(['ip', 'label']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes', 'two_factor_last_step',
        ]));
    }
};
