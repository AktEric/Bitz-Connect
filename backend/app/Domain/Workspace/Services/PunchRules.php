<?php

namespace App\Domain\Workspace\Services;

use App\Models\Hotel;

/** Regras de localização e foto na marcação de ponto, configuráveis por empresa. */
class PunchRules
{
    public const MODES = ['off', 'optional', 'required'];

    /** @return array{geo: string, photo: string, fence: ?array{lat: float, lng: float, radius_m: int, block: bool}} */
    public function settings(Hotel $hotel): array
    {
        $s = $hotel->time_settings ?? [];

        return [
            'geo'   => in_array($s['geo'] ?? null, self::MODES, true) ? $s['geo'] : 'off',
            'photo' => in_array($s['photo'] ?? null, self::MODES, true) ? $s['photo'] : 'off',
            'fence' => isset($s['fence']['lat'], $s['fence']['lng'], $s['fence']['radius_m']) ? [
                'lat'      => (float) $s['fence']['lat'],
                'lng'      => (float) $s['fence']['lng'],
                'radius_m' => (int) $s['fence']['radius_m'],
                'block'    => (bool) ($s['fence']['block'] ?? false),
            ] : null,
        ];
    }

    /** O que o app do colaborador precisa saber (sem expor as coordenadas da cerca). */
    public function requirements(Hotel $hotel): array
    {
        $s = $this->settings($hotel);

        return ['geo' => $s['geo'], 'photo' => $s['photo'], 'fence' => $s['fence'] !== null];
    }

    /** Distância em metros entre dois pontos (fórmula de Haversine). */
    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = $p2 - $p1;
        $dl = deg2rad($lng2 - $lng1);
        $a  = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
