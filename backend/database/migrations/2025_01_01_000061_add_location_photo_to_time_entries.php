<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_m')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->boolean('outside_fence')->default(false);
        });

        Schema::table('hotels', function (Blueprint $table) {
            // { geo: off|optional|required, photo: off|optional|required, fence: {lat,lng,radius_m,block}|null }
            $table->json('time_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hotels', fn (Blueprint $table) => $table->dropColumn('time_settings'));
        Schema::table('time_entries', fn (Blueprint $table) => $table->dropColumn(['lat', 'lng', 'accuracy_m', 'photo_path', 'outside_fence']));
    }
};
