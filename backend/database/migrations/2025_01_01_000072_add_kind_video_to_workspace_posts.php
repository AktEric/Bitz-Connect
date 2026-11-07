<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_posts', function (Blueprint $table) {
            $table->string('kind', 20)->default('post');            // post | birthday
            $table->foreignId('subject_id')->nullable()->constrained('users')->nullOnDelete(); // quem é homenageado
            $table->date('subject_date')->nullable();               // dia da homenagem (evita repetir)
            $table->index(['kind', 'subject_id', 'subject_date']);
        });
        Schema::table('workspace_post_media', function (Blueprint $table) {
            $table->string('kind', 10)->default('image');           // image | video
        });
    }

    public function down(): void
    {
        Schema::table('workspace_post_media', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('workspace_posts', function (Blueprint $table) {
            $table->dropIndex(['kind', 'subject_id', 'subject_date']);
            $table->dropConstrainedForeignId('subject_id');
            $table->dropColumn(['kind', 'subject_date']);
        });
    }
};
