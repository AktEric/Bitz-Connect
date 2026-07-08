<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Requirements: 6.3, 6.4
 *
 * PostgreSQL does not support ALTER COLUMN ... TYPE ENUM directly via Laravel's
 * Blueprint::change(). We use DB::statement to alter the underlying enum type
 * and add the new status column.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Alter the existing 'role' column to accept 'admin' | 'employee'
        //    In PostgreSQL, enum columns use CHECK constraints rather than a named
        //    enum type in Laravel's default driver, so we drop the old constraint
        //    and add a new one.
        DB::statement("ALTER TABLE hotel_users DROP CONSTRAINT IF EXISTS hotel_users_role_check");
        DB::statement("ALTER TABLE hotel_users ADD CONSTRAINT hotel_users_role_check CHECK (role IN ('admin', 'employee'))");

        // 2. Add the status column
        Schema::table('hotel_users', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_users', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        // Restore original role constraint (admin only)
        DB::statement("ALTER TABLE hotel_users DROP CONSTRAINT IF EXISTS hotel_users_role_check");
        DB::statement("ALTER TABLE hotel_users ADD CONSTRAINT hotel_users_role_check CHECK (role IN ('admin'))");
    }
};
