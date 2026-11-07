<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\PunchRules;
use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Configuração de localização, foto e cerca geográfica da marcação de ponto (dono e RH). */
class TimeSettingsController extends WorkspaceBaseController
{
    public function __construct(
        WorkspaceAccess $access,
        NotificationService $notifications,
        private readonly PunchRules $rules,
    ) {
        parent::__construct($access, $notifications);
    }

    private function denied(Request $request, Hotel $hotel): ?JsonResponse
    {
        return $this->access->isAdmin($this->membership($request, $hotel))
            ? null
            : $this->forbidden('Somente o dono ou o RH configuram o ponto.');
    }

    public function show(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }

        return $this->success('Configuração do ponto carregada.', $this->rules->settings($hotel) + ['current_ip' => $request->ip()]);
    }

    public function update(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }

        $data = $request->validate([
            'geo'            => ['required', Rule::in(PunchRules::MODES)],
            'photo'          => ['required', Rule::in(PunchRules::MODES)],
            'fence'          => ['nullable', 'array'],
            'fence.lat'      => ['required_with:fence', 'numeric', 'between:-90,90'],
            'fence.lng'      => ['required_with:fence', 'numeric', 'between:-180,180'],
            'fence.radius_m' => ['required_with:fence', 'integer', 'between:20,5000'],
            'fence.block'    => ['nullable', 'boolean'],
            'devices'        => ['sometimes', Rule::in(PunchRules::DEVICE_MODES)],
            'networks'       => ['sometimes', 'nullable', 'array', 'max:10'],
            'networks.*.name' => ['required', 'string', 'max:80'],
            'networks.*.cidr' => ['required', 'string', 'max:50', function ($attr, $value, $fail) {
                [$ip, $mask] = array_pad(explode('/', (string) $value, 2), 2, null);
                $max = str_contains((string) $ip, ':') ? 128 : 32;
                if (! filter_var($ip, FILTER_VALIDATE_IP) || ($mask !== null && (! ctype_digit($mask) || (int) $mask > $max))) {
                    $fail('Informe um IP ou faixa válida (ex.: 200.150.10.5 ou 200.150.10.0/24).');
                }
            }],
            'networks_block' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['fence']) && $data['geo'] === 'off') {
            return $this->error('Para usar a cerca geográfica, a localização precisa estar opcional ou obrigatória.', [], 422);
        }

        $current = $this->rules->settings($hotel);
        $networks = array_key_exists('networks', $data)
            ? array_values(array_map(fn ($n) => ['name' => trim($n['name']), 'cidr' => trim($n['cidr'])], $data['networks'] ?? []))
            : $current['networks'];

        $hotel->forceFill(['time_settings' => [
            'geo'   => $data['geo'],
            'photo' => $data['photo'],
            'devices'        => $data['devices'] ?? $current['devices'],
            'networks'       => $networks,
            'networks_block' => (bool) ($data['networks_block'] ?? $current['networks_block']),
            'fence' => empty($data['fence']) ? null : [
                'lat' => (float) $data['fence']['lat'], 'lng' => (float) $data['fence']['lng'],
                'radius_m' => (int) $data['fence']['radius_m'], 'block' => (bool) ($data['fence']['block'] ?? false),
            ],
        ]])->save();
        AuditLog::record($request, $hotel, 'time.settings_changed', null, ['geo' => $data['geo'], 'photo' => $data['photo'], 'fence' => ! empty($data['fence']), 'devices' => $data['devices'] ?? $current['devices'], 'networks' => count($networks)]);

        return $this->success('Configuração do ponto salva.', $this->rules->settings($hotel));
    }
}
