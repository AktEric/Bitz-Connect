<?php

namespace Database\Seeders;

use App\Models\CareerEntry;
use App\Models\HotelUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Histórico de carreira de demonstração do Eric (dono). Idempotente: não mexe em quem já tem histórico.
 * Rodar: php artisan db:seed --class=CareerDemoSeeder
 */
class CareerDemoSeeder extends Seeder
{
    /** título, setor, início, fim (null = atual) */
    private const ERIC = [
        ['Suporte Técnico',        'Suporte',      '2010-01-01', '2014-01-01'],
        ['Líder de Suporte',       'Suporte',      '2014-01-01', '2016-01-01'],
        ['Gestor de Consultoria',  'Consultoria',  '2016-01-01', '2022-01-01'],
        ['Diretor',                'Diretoria',    '2022-01-01', null],
    ];

    public function run(): void
    {
        $members = HotelUser::with('user')->where('status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('name', 'Eric Ribeiro')->orWhere('email', 'eric@inovacao.local'))
            ->get();

        foreach ($members as $m) {
            if (CareerEntry::where('hotel_id', $m->hotel_id)->where('user_id', $m->user_id)->exists()) {
                continue;
            }
            foreach (self::ERIC as [$title, $dept, $from, $to]) {
                CareerEntry::create([
                    'uuid' => (string) Str::uuid(), 'hotel_id' => $m->hotel_id, 'user_id' => $m->user_id,
                    'title' => $title, 'department' => $dept, 'started_on' => $from, 'ended_on' => $to, 'source' => 'manual',
                ]);
            }
        }
    }
}
