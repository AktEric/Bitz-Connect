<?php

namespace App\Domain\Workspace\Services;

use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Regras de ponto: estado da jornada, próxima marcação válida e horas trabalhadas. */
class TimeClock
{
    public const TZ = 'America/Sao_Paulo';

    /** @param Collection<int, TimeEntry> $entries ordenadas por punched_at */
    public function state(Collection $entries): string
    {
        return match ($entries->last()?->type) {
            'in', 'break_end' => 'working',
            'break_start'     => 'break',
            default           => 'out',
        };
    }

    /** @return string[] tipos permitidos como próxima marcação */
    public function allowedNext(Collection $entries): array
    {
        return match ($this->state($entries)) {
            'working' => ['break_start', 'out'],
            'break'   => ['break_end'],
            default   => ['in'],
        };
    }

    /** Minutos trabalhados no dia; se a jornada está aberta e $now é informado, conta até $now. */
    public function workedMinutes(Collection $entries, ?CarbonImmutable $now = null): int
    {
        $total = 0;
        $start = null;
        foreach ($entries as $e) {
            $t = CarbonImmutable::instance($e->punched_at);
            if (in_array($e->type, ['in', 'break_end'], true)) {
                $start = $t;
            } elseif ($start && in_array($e->type, ['break_start', 'out'], true)) {
                $total += max(0, $start->diffInMinutes($t, false));
                $start = null;
            }
        }
        if ($start && $now) {
            $total += max(0, $start->diffInMinutes($now, false));
        }

        return (int) $total;
    }

    public function format(TimeEntry $e): array
    {
        return [
            'uuid'       => $e->uuid,
            'type'       => $e->type,
            'punched_at' => $e->punched_at->toIso8601String(),
            'source'     => $e->source,
            'note'       => $e->note,
            'lat'        => $e->lat,
            'lng'        => $e->lng,
            'accuracy_m' => $e->accuracy_m,
            'has_photo'  => $e->photo_path !== null,
            'outside_fence' => (bool) $e->outside_fence,
            'ip'            => $e->ip,
            'device_name'   => $e->device_name,
            'device_label'  => $e->device_label,
            'network_name'  => $e->network_name,
            'outside_network' => (bool) $e->outside_network,
            'new_device'    => (bool) $e->new_device,
        ];
    }
}
