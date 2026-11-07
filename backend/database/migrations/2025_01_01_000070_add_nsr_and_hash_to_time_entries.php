<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('nsr')->nullable();    // número sequencial de registro, por empresa
            $table->char('hash', 64)->nullable();             // SHA-256 encadeado: altera um registro, quebra a cadeia
            $table->unique(['hotel_id', 'nsr']);
        });

        // Marcações já existentes entram na cadeia, na ordem em que foram criadas.
        foreach (DB::table('time_entries')->select('hotel_id')->distinct()->pluck('hotel_id') as $hotelId) {
            $prev = '';
            $nsr  = 0;
            foreach (DB::table('time_entries')->where('hotel_id', $hotelId)->orderBy('id')->get() as $e) {
                $nsr++;
                $punched = gmdate('Y-m-d\TH:i:s\Z', strtotime($e->punched_at . ' UTC'));
                $hash = hash('sha256', implode('|', [$e->hotel_id, $nsr, $e->user_id, $e->type, $punched, $e->source, $prev]));
                DB::table('time_entries')->where('id', $e->id)->update(['nsr' => $nsr, 'hash' => $hash]);
                $prev = $hash;
            }
        }
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropUnique(['hotel_id', 'nsr']);
            $table->dropColumn(['nsr', 'hash']);
        });
    }
};
