<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de benefícios da empresa (nome e cor do quadrado na tela do colaborador).
        Schema::create('benefit_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 10)->default('gray');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['hotel_id', 'name']);
        });

        // Benefícios de UMA pessoa. Valor criptografado (nulo = benefício sem valor, ex.: plano de saúde).
        Schema::create('employee_benefits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('benefit_type_id')->constrained('benefit_types')->cascadeOnDelete();
            $table->text('amount')->nullable();                   // centavos, criptografado
            $table->string('frequency', 10)->default('monthly');  // monthly | one_time
            $table->unsignedTinyInteger('deposit_day')->nullable(); // 1–31 (mensal)
            $table->date('deposit_on')->nullable();                 // data (pontual)
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();                    // nulo = vigente
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'user_id']);
        });

        // Pacotes: modelos de benefícios aplicados de uma vez (ex.: "Operacional CLT").
        Schema::create('benefit_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('items');   // [{benefit_type_uuid, amount_cents|null, deposit_day|null, note|null}]
            $table->timestamps();

            $table->unique(['hotel_id', 'name']);
        });

        Schema::table('company_invites', function (Blueprint $table) {
            $table->foreignId('benefit_package_id')->nullable()->constrained('benefit_packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_invites', fn (Blueprint $table) => $table->dropConstrainedForeignId('benefit_package_id'));
        Schema::dropIfExists('benefit_packages');
        Schema::dropIfExists('employee_benefits');
        Schema::dropIfExists('benefit_types');
    }
};
