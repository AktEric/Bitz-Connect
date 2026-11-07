<?php

namespace App\Domain\Workspace\Services;

use App\Models\Hotel;
use App\Models\TimeBankAdjustment;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkspaceRequest;
use Carbon\CarbonImmutable;

/**
 * Banco de horas: saldo acumulado = (trabalhado − esperado) dos dias já encerrados
 * + lançamentos manuais do RH.
 *
 * - Começa no dia da primeira marcação da pessoa (antes disso ela não usava o ponto).
 * - O dia de hoje não entra: só dias completos, para o saldo não ficar negativo no meio do expediente.
 * - Férias aprovadas zeram o esperado nos dias cobertos.
 */
class TimeBank
{
    public function __construct(private readonly TimeClock $clock, private readonly CalendarService $calendar) {}

    /**
     * Minutos esperados em cada dia do intervalo: a jornada da pessoa, zerada em feriados e em férias aprovadas.
     *
     * @return array<string, int> data (Y-m-d) => minutos
     */
    public function expectedByDay(Hotel $hotel, User $user, ?WorkSchedule $schedule, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $vacation = $this->vacationDays($hotel, $user, $from, $to);
        $holidays = $this->calendar->holidayDates($hotel, $from, $to);

        $out = [];
        for ($d = $from; $d <= $to; $d = $d->addDay()) {
            $key = $d->toDateString();
            $out[$key] = isset($vacation[$key]) || isset($holidays[$key]) ? 0 : WorkSchedule::minutesFor($schedule, $d->dayOfWeek);
        }

        return $out;
    }

    /** @return array{since: ?string, worked_minutes: int, expected_minutes: int, entries_balance: int, adjustments_minutes: int, balance_minutes: int} */
    public function summary(Hotel $hotel, User $user, ?WorkSchedule $schedule): array
    {
        $today = CarbonImmutable::now(TimeClock::TZ)->startOfDay();
        $adjustments = (int) TimeBankAdjustment::where('hotel_id', $hotel->id)->where('user_id', $user->id)->sum('minutes');

        $worked = 0;
        $expected = 0;
        $since = null;

        $firstRaw = TimeEntry::where('hotel_id', $hotel->id)->where('user_id', $user->id)->min('punched_at');
        if ($firstRaw) {
            $start = CarbonImmutable::parse($firstRaw, 'UTC')->setTimezone(TimeClock::TZ)->startOfDay();
            if ($start < $today) {
                $since = $start->toDateString();
                $byDay = TimeEntry::where('hotel_id', $hotel->id)->where('user_id', $user->id)
                    ->where('punched_at', '>=', $start->utc())->where('punched_at', '<', $today->utc())
                    ->orderBy('punched_at')->get()
                    ->groupBy(fn ($e) => CarbonImmutable::instance($e->punched_at)->setTimezone(TimeClock::TZ)->toDateString());
                $perDay = $this->expectedByDay($hotel, $user, $schedule, $start, $today->subDay());

                for ($d = $start; $d < $today; $d = $d->addDay()) {
                    $key = $d->toDateString();
                    $worked += $this->clock->workedMinutes($byDay->get($key, collect()));
                    $expected += $perDay[$key];
                }
            }
        }

        $entriesBalance = $worked - $expected;

        return [
            'since'               => $since,
            'worked_minutes'      => $worked,
            'expected_minutes'    => $expected,
            'entries_balance'     => $entriesBalance,
            'adjustments_minutes' => $adjustments,
            'balance_minutes'     => $entriesBalance + $adjustments,
        ];
    }

    /**
     * Horas de um mês já terminado: trabalhadas, esperadas, dias trabalhados e faltas
     * (dia com jornada esperada, sem marcação e sem férias).
     *
     * @return array{worked_minutes: int, expected_minutes: int, days_worked: int, absent_days: int}
     */
    public function monthStats(Hotel $hotel, User $user, ?WorkSchedule $schedule, CarbonImmutable $first, ?CarbonImmutable $until = null): array
    {
        $last   = $until ?? $first->endOfMonth();
        $today  = CarbonImmutable::now(TimeClock::TZ)->startOfDay();
        $byDay  = TimeEntry::where('hotel_id', $hotel->id)->where('user_id', $user->id)
            ->whereBetween('punched_at', [$first->utc(), $last->utc()])->orderBy('punched_at')->get()
            ->groupBy(fn ($e) => CarbonImmutable::instance($e->punched_at)->setTimezone(TimeClock::TZ)->toDateString());
        $perDay = $this->expectedByDay($hotel, $user, $schedule, $first, $last);

        $worked = $expected = $daysWorked = $absent = 0;
        for ($d = $first; $d <= $last; $d = $d->addDay()) {
            $key  = $d->toDateString();
            $mins = $this->clock->workedMinutes($byDay->get($key, collect()));
            $exp  = $perDay[$key];
            $worked   += $mins;
            $expected += $exp;
            if ($mins > 0) {
                $daysWorked++;
            } elseif ($exp > 0 && $d->lt($today)) { // só é falta um dia que já passou
                $absent++;
            }
        }

        return ['worked_minutes' => $worked, 'expected_minutes' => $expected, 'days_worked' => $daysWorked, 'absent_days' => $absent];
    }

    /** @return array<string, true> datas (Y-m-d) cobertas por férias aprovadas dentro do intervalo */
    private function vacationDays(Hotel $hotel, User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = [];
        $requests = WorkspaceRequest::where('hotel_id', $hotel->id)->where('requester_id', $user->id)
            ->where('type', 'vacation')->where('status', 'approved')
            ->whereNotNull('starts_on')->whereDate('starts_on', '<=', $to->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from->toDateString()))
            ->get();

        foreach ($requests as $r) {
            $s = CarbonImmutable::parse($r->starts_on->toDateString(), TimeClock::TZ);
            $e = $r->ends_on ? CarbonImmutable::parse($r->ends_on->toDateString(), TimeClock::TZ) : $s;
            for ($d = $s->max($from); $d <= $e->min($to); $d = $d->addDay()) {
                $days[$d->toDateString()] = true;
            }
        }

        return $days;
    }
}
