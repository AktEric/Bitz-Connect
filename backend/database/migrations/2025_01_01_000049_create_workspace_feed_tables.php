<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Feed interno da empresa: comunicados, comentários e curtidas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete(); // público-alvo (null = todos)
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['hotel_id', 'pinned', 'created_at']);
        });

        Schema::create('workspace_post_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['workspace_post_id', 'created_at']);
        });

        Schema::create('workspace_post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workspace_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_post_likes');
        Schema::dropIfExists('workspace_post_comments');
        Schema::dropIfExists('workspace_posts');
    }
};
