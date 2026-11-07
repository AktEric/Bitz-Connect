<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_posts', fn (Blueprint $table) => $table->string('badge', 20)->nullable()); // valor reconhecido (kind = kudos)
    }

    public function down(): void
    {
        Schema::table('workspace_posts', fn (Blueprint $table) => $table->dropColumn('badge'));
    }
};
