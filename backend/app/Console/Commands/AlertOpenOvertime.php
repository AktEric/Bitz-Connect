<?php

namespace App\Console\Commands;

use App\Domain\Workspace\Services\PunchCompliance;
use App\Domain\Workspace\Services\TimeBank;
use App\Domain\Workspace\Services\TimeClock;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Avisa o gestor quando alguém segue com a jornada aberta além do expediente padrão (hora extra em andamento). */
class AlertOpenOvertime extends Command
{
    protected $signature = 'ponto:alert-overtime';

    protected $description = 'Notifica gestores sobre jornadas abertas que passaram do expediente padrão.';

    public function handle(TimeClock $clock, TimeBank $bank, PunchCompliance $compliance): int
    {
        $now   = CarbonImmutable::now(TimeClock::TZ);
        $day   = $now->toDateString();
        $count = 0;

        $entries = TimeEntry::whereBetween('punched_at', [$now->startOfDay()->utc(), $now->endOfDay()->utc()])
            ->orderBy('punched_at')->get()->groupBy(fn ($e) => $e->hotel_id . ':' . $e->user_id);

        foreach ($entries as $list) {
            if ($clock->state($list) !== 'working') {
                continue;
            }
            $first  = $list->first();
            $hotel  = Hotel::find($first->hotel_id);
            $member = HotelUser::with(['user', 'workSchedule'])->where('hotel_id', $first->hotel_id)->where('user_id', $first->user_id)->where('status', 'approved')->first();
            if (! $hotel || ! $member) {
                continue;
            }
            $daily  = $bank->expectedByDay($hotel, $member->user, $member->workSchedule, $now->startOfDay(), $now->startOfDay())[$day] ?? 0;
            $issues = $daily > 0 ? $compliance->overtimeIssues($clock->workedMinutes($list, $now), $daily) : [];
            if ($issues && Cache::add("punch-ot:{$hotel->id}:{$member->user_id}:{$day}", 1, now()->addDay())) {
                $compliance->notify($hotel, $member->user, $issues, "/w/{$hotel->slug}/ponto");
                $count++;
            }
        }

        $this->info("{$count} aviso(s) de hora extra em andamento.");

        return self::SUCCESS;
    }
}
