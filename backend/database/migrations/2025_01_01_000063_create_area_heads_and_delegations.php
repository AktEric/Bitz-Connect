<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Chefe da área: enxerga e aprova tudo do setor (e de quem reporta a quem está no setor).
            $table->foreignId('head_id')->nullable()->constrained('users')->nullOnDelete();
        });

        // Substituição: durante o período, o substituto passa a ter o alcance de gestor de quem se ausenta.
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'to_user_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegations');
        Schema::table('departments', fn (Blueprint $table) => $table->dropConstrainedForeignId('head_id'));
    }
};
