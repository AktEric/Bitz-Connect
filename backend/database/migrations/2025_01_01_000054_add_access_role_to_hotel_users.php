<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_users', function (Blueprint $table) {
            // Papel de acesso granular: owner | hr | finance | manager | employee.
            // Nulo = derivado de `role` (admin => owner, employee => employee).
            $table->string('access_role', 20)->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_users', function (Blueprint $table) {
            $table->dropColumn('access_role');
        });
    }
};
