<?php

namespace App\Domain\Workspace\Services;

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeAdjustment;
use App\Models\TimeClosing;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Regras do fechamento mensal do ponto. */
class TimeClosingService
{
    public function __construct(private readonly TimeBank $bank) {}

    public function monthStart(string $month): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $month . '-01', TimeClock::TZ)->startOfDay();
    }

    /** O mês só pode ser fechado depois de terminar. */
    public function hasEnded(string $month): bool
    {
        return $this->monthStart($month)->endOfMonth() < CarbonImmutable::now(TimeClock::TZ);
    }

    public function isClosed(Hotel $hotel, CarbonInterface $when): bool
    {
        $month = CarbonImmutable::instance($when)->setTimezone(TimeClock::TZ)->format('Y-m');

        return TimeClosing::where('hotel_id', $hotel->id)->where('month', $month)->exists();
    }

    public function pendingAdjustments(Hotel $hotel, string $month): int
    {
        $first = $this->monthStart($month);

        return TimeAdjustment::where('hotel_id', $hotel->id)->where('status', 'pending')
            ->whereBetween('requested_at', [$first->utc(), $first->endOfMonth()->utc()])->count();
    }

    /** Linhas do mês por pessoa (calculadas na hora; o fechamento grava este retrato). */
    public function rows(Hotel $hotel, string $month): array
    {
        $first = $this->monthStart($month);

        return HotelUser::with(['user', 'workSchedule', 'department'])->where('hotel_id', $hotel->id)->where('status', 'approved')
            ->get()
            ->map(function (HotelUser $m) use ($hotel, $first) {
                $stats = $this->bank->monthStats($hotel, $m->user, $m->workSchedule, $first);
                $bank  = $this->bank->summary($hotel, $m->user, $m->workSchedule);

                return [
                    'user_uuid'        => $m->user->uuid,
                    'name'             => $m->user->name,
                    'job_title'        => $m->job_title,
                    'department'       => $m->department?->name,
                    'days_worked'      => $stats['days_worked'],
                    'absent_days'      => $stats['absent_days'],
                    'worked_minutes'   => $stats['worked_minutes'],
                    'expected_minutes' => $stats['expected_minutes'],
                    'balance_minutes'  => $stats['worked_minutes'] - $stats['expected_minutes'],
                    'bank_minutes'     => $bank['balance_minutes'],
                ];
            })->sortBy('name')->values()->all();
    }
}
