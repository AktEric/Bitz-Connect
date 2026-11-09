<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('description');
            $table->unsignedBigInteger('price_cents')->nullable();   // nulo = "a combinar"
            $table->string('category', 20);
            $table->string('condition', 12)->default('used');
            $table->string('location', 80)->nullable();
            $table->string('status', 10)->default('active');          // active | sold | hidden
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('reposts_count')->default(0);
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['hotel_id', 'status', 'created_at']);
            $table->index(['hotel_id', 'category']);
            $table->index(['seller_id', 'status']);
        });

        Schema::create('marketplace_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
            $table->string('path', 255);                              // disco privado
            $table->string('mime', 60);
            $table->unsignedInteger('size');
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index(['listing_id', 'position']);
        });

        Schema::create('marketplace_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['listing_id', 'user_id']);
        });

        Schema::create('marketplace_reposts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('marketplace_listings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('workspace_posts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['listing_id', 'user_id']);
        });

        // Cartão do anúncio dentro de uma publicação do mural (repost)
        Schema::table('workspace_posts', function (Blueprint $table) {
            $table->foreignId('listing_id')->nullable()->constrained('marketplace_listings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workspace_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('listing_id');
        });
        Schema::dropIfExists('marketplace_reposts');
        Schema::dropIfExists('marketplace_likes');
        Schema::dropIfExists('marketplace_images');
        Schema::dropIfExists('marketplace_listings');
    }
};
