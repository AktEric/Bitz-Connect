<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dispositivos de marcação da empresa (um computador/tablet pode ser compartilhado por várias pessoas).
        Schema::create('time_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('key_hash', 64);                       // sha256 da chave guardada no navegador
            $table->string('name', 80);                           // ex.: "Recepção — PC 01"
            $table->string('status', 10)->default('pending');     // pending | approved | blocked
            $table->string('label', 120)->nullable();             // "Chrome 130 · Windows 10/11 · desktop"
            $table->string('last_ip', 45)->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'key_hash']);
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->foreignId('device_id')->nullable()->constrained('time_devices')->nullOnDelete();
            $table->string('device_name', 80)->nullable();        // retrato do nome no momento da marcação
            $table->string('device_label', 120)->nullable();      // navegador · sistema · tipo
            $table->string('user_agent', 255)->nullable();
            $table->string('network_name', 80)->nullable();       // rede autorizada em que estava (ex.: "Matriz")
            $table->boolean('outside_network')->default(false);
            $table->boolean('new_device')->default(false);        // primeira marcação neste dispositivo
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('device_id');
            $table->dropColumn(['device_name', 'device_label', 'user_agent', 'network_name', 'outside_network', 'new_device']);
        });
        Schema::dropIfExists('time_devices');
    }
};
