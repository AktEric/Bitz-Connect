<?php

namespace App\Domain\Workspace\Services;

use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Regras de ponto: estado da jornada, próxima marcação válida e horas trabalhadas. */
class TimeClock
{
    public const TZ = 'America/Sao_Paulo';
    /** Jornada padrão em minutos (8h) até existirem escalas por pessoa. */
    public const DAILY_MINUTES = 480;

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
        ];
    }
}
