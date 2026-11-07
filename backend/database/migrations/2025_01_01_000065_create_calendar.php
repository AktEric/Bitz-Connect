<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_users', function (Blueprint $table) {
            // Aniversário: o ano é privado (só a própria pessoa e o RH enxergam); dia/mês aparecem se a pessoa permitir.
            $table->date('birth_date')->nullable();
            $table->boolean('birthday_visible')->default(true);
            // Segredo do link pessoal de assinatura do calendário (Google/Outlook/Apple). Pode ser trocado a qualquer hora.
            $table->string('calendar_token', 64)->nullable()->unique();
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('kind', 10)->default('event');          // event | holiday (feriado/folga da empresa)
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->time('starts_time')->nullable();
            $table->time('ends_time')->nullable();
            $table->string('location', 120)->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete(); // público: null = toda a empresa
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'starts_on']);
        });

        Schema::create('calendar_event_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 8);                            // going | maybe | no
            $table->timestamps();

            $table->unique(['calendar_event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_rsvps');
        Schema::dropIfExists('calendar_events');
        Schema::table('hotel_users', fn (Blueprint $table) => $table->dropColumn(['birth_date', 'birthday_visible', 'calendar_token']));
    }
};
