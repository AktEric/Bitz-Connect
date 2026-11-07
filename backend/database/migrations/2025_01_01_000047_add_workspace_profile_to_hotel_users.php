<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Perfil interno do funcionário na empresa: cargo, admissão e gestor direto. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_users', function (Blueprint $table) {
            $table->string('job_title', 120)->nullable()->after('department_id');
            $table->date('hired_at')->nullable()->after('job_title');
            $table->foreignId('manager_id')->nullable()->after('hired_at')->constrained('users')->nullOnDelete();

            $table->index(['hotel_id', 'manager_id']);
        });
    }

    public function down(): void
    {
        Schema::table('hotel_users', function (Blueprint $table) {
            $table->dropIndex(['hotel_id', 'manager_id']);
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['job_title', 'hired_at']);
        });
    }
};
