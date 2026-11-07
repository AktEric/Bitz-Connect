<?php

namespace App\Domain\Workspace\Services;

use App\Models\CalendarEvent;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use App\Models\WorkspaceRequest;
use Carbon\CarbonImmutable;

/**
 * Calendário do Workspace: junta aniversários, tempo de casa, feriados, férias aprovadas e eventos da empresa
 * respeitando quem pode ver o quê.
 *
 * - Aniversário: só dia/mês; aparece se a pessoa permitir (ou para ela mesma e para o RH).
 * - Férias: a própria pessoa, o gestor/chefe de área (escopo) e o RH.
 * - Evento de um setor: só para o setor, o RH e quem criou.
 * - Feriados nacionais são calculados; feriados municipais/estaduais entram como evento do tipo "feriado".
 */
class CalendarService
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    /** Domingo de Páscoa (algoritmo de Meeus/Jones/Butcher). */
    public function easter(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, TimeClock::TZ);
    }

    /** @return array<string, string> data (Y-m-d) => nome */
    public function nationalHolidays(int $year): array
    {
        $fixed = [
            '01-01' => 'Confraternização Universal', '04-21' => 'Tiradentes', '05-01' => 'Dia do Trabalho',
            '09-07' => 'Independência do Brasil', '10-12' => 'Nossa Senhora Aparecida', '11-02' => 'Finados',
            '11-15' => 'Proclamação da República', '11-20' => 'Consciência Negra', '12-25' => 'Natal',
        ];
        $out = [];
        foreach ($fixed as $md => $name) {
            $out["{$year}-{$md}"] = $name;
        }
        $easter = $this->easter($year);
        $out[$easter->subDays(47)->toDateString()] = 'Carnaval (ponto facultativo)';
        $out[$easter->subDays(2)->toDateString()]  = 'Sexta-feira Santa';
        $out[$easter->addDays(60)->toDateString()] = 'Corpus Christi (ponto facultativo)';

        return $out;
    }

    /** Ocorrência do dia/mês de $date no ano $year (29/02 vira 28/02 em ano não bissexto). */
    private function occurrence(CarbonImmutable $date, int $year): CarbonImmutable
    {
        $day = ($date->month === 2 && $date->day === 29 && ! CarbonImmutable::create($year)->isLeapYear()) ? 28 : $date->day;

        return CarbonImmutable::create($year, $date->month, $day, 0, 0, 0, TimeClock::TZ);
    }

    private function item(string $type, string $date, string $title, array $extra = []): array
    {
        return array_merge([
            'type' => $type, 'date' => $date, 'end_date' => null, 'title' => $title,
            'subtitle' => null, 'person' => null, 'all_day' => true, 'time' => null, 'event' => null,
        ], $extra);
    }

    /** @return array<int, array<string, mixed>> itens ordenados por data */
    public function items(Hotel $hotel, User $viewer, HotelUser $viewerMember, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $isAdmin = $this->access->isAdmin($viewerMember);
        $items   = [];
        $years   = range($from->year, $to->year);

        $members = HotelUser::with('user')->where('hotel_id', $hotel->id)->where('status', 'approved')->get();

        foreach ($members as $m) {
            if (! $m->user) {
                continue;
            }
            $person = $this->access->person($m->user, $m);

            if ($m->birth_date && ($m->birthday_visible || $m->user_id === $viewer->id || $isAdmin)) {
                $birth = CarbonImmutable::parse($m->birth_date->toDateString(), TimeClock::TZ);
                foreach ($years as $y) {
                    $d = $this->occurrence($birth, $y);
                    if ($d->betweenIncluded($from, $to)) {
                        $items[] = $this->item('birthday', $d->toDateString(), $m->user->name, [
                            'subtitle' => $m->job_title, 'person' => $person,
                            'hidden_from_team' => ! $m->birthday_visible,
                        ]);
                    }
                }
            }

            if ($m->hired_at) {
                $hired = CarbonImmutable::parse($m->hired_at->toDateString(), TimeClock::TZ);
                foreach ($years as $y) {
                    $d = $this->occurrence($hired, $y);
                    $n = $y - $hired->year;
                    if ($n >= 1 && $d->betweenIncluded($from, $to)) {
                        $items[] = $this->item('anniversary', $d->toDateString(), $m->user->name, [
                            'subtitle' => $n === 1 ? '1 ano de empresa' : "{$n} anos de empresa", 'person' => $person,
                        ]);
                    }
                }
            }
        }

        // Feriados nacionais
        foreach ($years as $y) {
            foreach ($this->nationalHolidays($y) as $date => $name) {
                if (CarbonImmutable::parse($date, TimeClock::TZ)->betweenIncluded($from, $to)) {
                    $items[] = $this->item('holiday', $date, $name, ['subtitle' => 'Feriado nacional']);
                }
            }
        }

        // Eventos e feriados da empresa
        $events = CalendarEvent::with(['department', 'rsvps'])->where('hotel_id', $hotel->id)
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->where(fn ($q) => $q->whereDate('ends_on', '>=', $from->toDateString())
                ->orWhere(fn ($q2) => $q2->whereNull('ends_on')->whereDate('starts_on', '>=', $from->toDateString())))
            ->orderBy('starts_on')->get();

        foreach ($events as $e) {
            $canSee = $isAdmin || ! $e->department_id || $e->department_id === $viewerMember->department_id || $e->created_by === $viewer->id;
            if (! $canSee) {
                continue;
            }
            $mine  = $e->rsvps->firstWhere('user_id', $viewer->id)?->status;
            $count = fn (string $s) => $e->rsvps->where('status', $s)->count();
            $items[] = $this->item($e->kind === 'holiday' ? 'holiday' : 'event', $e->starts_on->toDateString(), $e->title, [
                'end_date'  => $e->ends_on && ! $e->ends_on->isSameDay($e->starts_on) ? $e->ends_on->toDateString() : null,
                'subtitle'  => $e->kind === 'holiday' ? 'Feriado da empresa' : ($e->location ?: ($e->department?->name ? "Setor {$e->department->name}" : null)),
                'all_day'   => $e->starts_time === null,
                'time'      => $e->starts_time ? substr($e->starts_time, 0, 5) . ($e->ends_time ? '–' . substr($e->ends_time, 0, 5) : '') : null,
                'event'     => [
                    'uuid' => $e->uuid, 'kind' => $e->kind, 'description' => $e->description, 'location' => $e->location,
                    'department' => $e->department?->name,
                    'can_edit' => $isAdmin || $e->created_by === $viewer->id,
                    'rsvp' => ['mine' => $mine, 'going' => $count('going'), 'maybe' => $count('maybe'), 'no' => $count('no')],
                ],
            ]);
        }

        // Férias aprovadas: a própria pessoa, o escopo do gestor/chefe e o RH
        $scope = $isAdmin ? null : array_merge([$viewer->id], $this->access->reportIds($viewer, $hotel));
        $vac = WorkspaceRequest::with('requester')->where('hotel_id', $hotel->id)->where('type', 'vacation')->where('status', 'approved')
            ->whereNotNull('starts_on')->whereDate('starts_on', '<=', $to->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from->toDateString()));
        if ($scope !== null) {
            $vac->whereIn('requester_id', $scope);
        }
        foreach ($vac->get() as $r) {
            $s = $r->starts_on->toDateString();
            $e = $r->ends_on?->toDateString();
            $items[] = $this->item('vacation', $s, $r->requester?->name ?? 'Pessoa', [
                'end_date' => $e && $e !== $s ? $e : null, 'subtitle' => 'Férias',
                'person' => $this->access->person($r->requester),
            ]);
        }

        usort($items, fn ($a, $b) => [$a['date'], $a['type'] === 'event' ? 1 : 0, $a['title']] <=> [$b['date'], $b['type'] === 'event' ? 1 : 0, $b['title']]);

        return $items;
    }

    /** Texto iCalendar (RFC 5545) para assinatura em Google/Outlook/Apple. */
    public function ics(Hotel $hotel, array $items): string
    {
        $esc = fn (string $s) => str_replace(["\\", ";", ",", "\r\n", "\n"], ["\\\\", "\\;", "\\,", "\\n", "\\n"], $s);
        $fold = function (string $line): string {
            $out = '';
            while (mb_strlen($line, '8bit') > 73) {
                $cut = 73;
                while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                    $cut--; // não corta no meio de um caractere UTF-8
                }
                $out .= substr($line, 0, $cut) . "\r\n ";
                $line = substr($line, $cut);
            }

            return $out . $line;
        };
        $label = ['birthday' => 'Aniversário', 'anniversary' => 'Tempo de casa', 'holiday' => 'Feriado', 'event' => 'Evento', 'vacation' => 'Férias'];
        $stamp = CarbonImmutable::now('UTC')->format('Ymd\THis\Z');

        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Workspace//Calendario//PT-BR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $esc($hotel->name . ' — Calendário'), 'X-WR-TIMEZONE:' . TimeClock::TZ];

        foreach ($items as $it) {
            $uid = md5($hotel->id . '|' . $it['type'] . '|' . $it['date'] . '|' . $it['title']) . '@workspace';
            $summary = match ($it['type']) {
                'birthday'    => '🎂 Aniversário de ' . $it['title'],
                'anniversary' => '🎉 ' . $it['title'] . ' — ' . ($it['subtitle'] ?? 'tempo de casa'),
                'vacation'    => '🏖️ Férias — ' . $it['title'],
                default       => $it['title'],
            };
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            $lines[] = "DTSTAMP:{$stamp}";
            if ($it['all_day'] || ! $it['time']) {
                $start = CarbonImmutable::parse($it['date']);
                $end   = CarbonImmutable::parse($it['end_date'] ?? $it['date'])->addDay(); // DTEND de dia inteiro é exclusivo
                $lines[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');
            } else {
                [$st, $en] = array_pad(explode('–', $it['time']), 2, null);
                $day = CarbonImmutable::parse($it['end_date'] ?? $it['date'])->format('Ymd');
                $lines[] = 'DTSTART:' . CarbonImmutable::parse($it['date'])->format('Ymd') . 'T' . str_replace(':', '', $st) . '00';
                $lines[] = 'DTEND:' . $day . 'T' . str_replace(':', '', $en ?: $st) . '00';
            }
            $lines[] = 'SUMMARY:' . $esc($summary);
            $desc = trim(($label[$it['type']] ?? '') . ($it['subtitle'] ? ' · ' . $it['subtitle'] : '') . (($it['event']['description'] ?? null) ? "\n" . $it['event']['description'] : ''));
            if ($desc !== '') {
                $lines[] = 'DESCRIPTION:' . $esc($desc);
            }
            if (! empty($it['event']['location'])) {
                $lines[] = 'LOCATION:' . $esc($it['event']['location']);
            }
            $lines[] = 'TRANSP:TRANSPARENT';
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($fold, $lines)) . "\r\n";
    }
}
