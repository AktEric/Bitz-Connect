<?php

namespace App\Domain\Workspace\Services;

use App\Models\Hotel;
use Symfony\Component\HttpFoundation\IpUtils;

/** Regras de localização e foto na marcação de ponto, configuráveis por empresa. */
class PunchRules
{
    public const MODES = ['off', 'optional', 'required'];

    public const DEVICE_MODES = ['off', 'log', 'required'];

    /** @return array{geo: string, photo: string, fence: ?array{lat: float, lng: float, radius_m: int, block: bool}, devices: string, networks: array<int, array{name: string, cidr: string}>, networks_block: bool} */
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
            // devices: off = só registra aparelho/navegador/IP | log = cadastra todo aparelho automaticamente | required = RH aprova antes
            'devices'        => in_array($s['devices'] ?? null, self::DEVICE_MODES, true) ? $s['devices'] : 'off',
            'networks'       => array_values(array_filter(array_map(
                fn ($n) => isset($n['name'], $n['cidr']) ? ['name' => (string) $n['name'], 'cidr' => (string) $n['cidr']] : null,
                (array) ($s['networks'] ?? []),
            ))),
            'networks_block' => (bool) ($s['networks_block'] ?? false),
        ];
    }

    /** Nome da rede autorizada que contém o IP, ou null se nenhuma contém. */
    public function matchNetwork(array $networks, string $ip): ?string
    {
        foreach ($networks as $n) {
            if (IpUtils::checkIp($ip, $n['cidr'])) {
                return $n['name'];
            }
        }

        return null;
    }

    /** O que o app do colaborador precisa saber (sem expor as coordenadas da cerca). */
    public function requirements(Hotel $hotel): array
    {
        $s = $this->settings($hotel);

        return [
            'geo' => $s['geo'], 'photo' => $s['photo'], 'fence' => $s['fence'] !== null,
            'devices' => $s['devices'], 'networks' => $s['networks'] !== [],
        ];
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
