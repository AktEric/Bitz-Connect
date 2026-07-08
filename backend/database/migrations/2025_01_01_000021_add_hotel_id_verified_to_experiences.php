<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requirements: 6.1, 6.2
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->foreignId('hotel_id')->nullable()->constrained('hotels')->nullOnDelete()->after('user_id');
            $table->boolean('verified')->default(false)->after('hotel_id');
        });
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropForeign(['hotel_id']);
            $table->dropColumn(['hotel_id', 'verified']);
        });
    }
};
