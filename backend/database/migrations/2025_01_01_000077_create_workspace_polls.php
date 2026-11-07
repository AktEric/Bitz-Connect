<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enquetes do mural (clima, escolhas rápidas). Voto anônimo para os colegas: só os totais aparecem.
        Schema::table('workspace_posts', fn (Blueprint $table) => $table->date('poll_closes_on')->nullable());

        Schema::create('workspace_poll_options', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_post_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->unsignedTinyInteger('position')->default(0);
        });

        Schema::create('workspace_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_poll_option_id')->constrained('workspace_poll_options')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['workspace_post_id', 'user_id']); // um voto por pessoa
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_poll_votes');
        Schema::dropIfExists('workspace_poll_options');
        Schema::table('workspace_posts', fn (Blueprint $table) => $table->dropColumn('poll_closes_on'));
    }
};
