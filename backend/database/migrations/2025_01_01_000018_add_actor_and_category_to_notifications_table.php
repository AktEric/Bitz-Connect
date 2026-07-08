<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds actor_id (who triggered the notification) and category
 * (grouping key for filters) to the notifications table.
 *
 * actor_id  — nullable FK to users (NULL for system notifications)
 * category  — enum-like string: connection | message | feed | job | hotel | profile | system
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Who triggered the action (actor); NULL for system notifications
            $table->foreignId('actor_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();

            // Category for filtering: connection|message|feed|job|hotel|profile|system
            $table->string('category', 30)
                ->default('system')
                ->after('type');

            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
            $table->dropColumn(['actor_id', 'category']);
            $table->dropIndex(['user_id', 'category']);
        });
    }
};
