<?php

namespace App\Domain\Workspace\Services;

use App\Models\CalendarEvent;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeAdjustment;
use App\Models\TimeDevice;
use App\Models\TimeEntry;
use App\Models\WorkspacePost;
use App\Models\WorkspacePostAck;
use App\Models\WorkspacePostComment;
use App\Models\WorkspacePostLike;
use App\Models\WorkspaceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Números da empresa para a diretoria. Só agregados: nada aqui identifica o saldo ou a falta de uma pessoa.
 * Somas por setor só aparecem com 3+ pessoas (evita deduzir o saldo de um indivíduo).
 */
class DashboardService
{
    public const MIN_GROUP = 3;

    public function __construct(private readonly TimeClock $clock, private readonly TimeBank $bank, private readonly WorkspaceAccess $access) {}

    /** Resultado em cache por 5 minutos (o banco de horas percorre todas as pessoas). */
    public function build(Hotel $hotel): array
    {
        return Cache::remember("workspace-dashboard:{$hotel->id}", 300, fn () => $this->compute($hotel));
    }

    public function forget(Hotel $hotel): void
    {
        Cache::forget("workspace-dashboard:{$hotel->id}");
    }

    private function compute(Hotel $hotel): array
    {
        $tz    = TimeClock::TZ;
        $now   = CarbonImmutable::now($tz);
        $today = $now->startOfDay();

        $members = HotelUser::with(['user', 'department', 'workSchedule'])->where('hotel_id', $hotel->id)->where('status', 'approved')->get()
            ->filter(fn (HotelUser $m) => $m->user);

        // ── Pessoas
        $byDept = $members->groupBy(fn ($m) => $m->department?->name ?? 'Sem setor');
        $people = [
            'headcount'   => $members->count(),
            'hires_90d'   => $members->filter(fn ($m) => $m->hired_at && $m->hired_at->greaterThanOrEqualTo($today->subDays(90)))->count(),
            'birthdays_month' => $members->filter(fn ($m) => $m->birth_date && $m->birthday_visible && $m->birth_date->month === $today->month)->count(),
            'avg_tenure_years' => $members->filter(fn ($m) => $m->hired_at)->isEmpty() ? null
                : round($members->filter(fn ($m) => $m->hired_at)->avg(fn ($m) => $m->hired_at->diffInDays($today) / 365.25), 1),
        ];

        // ── Hoje
        $entries = TimeEntry::where('hotel_id', $hotel->id)->whereBetween('punched_at', [$today->utc(), $today->endOfDay()->utc()])
            ->orderBy('punched_at')->get()->groupBy('user_id');
        $working = $present = 0;
        foreach ($entries as $list) {
            $present++;
            if ($this->clock->state($list) === 'working') {
                $working++;
            }
        }
        $vacationToday = WorkspaceRequest::where('hotel_id', $hotel->id)->where('type', 'vacation')->where('status', 'approved')
            ->whereDate('starts_on', '<=', $today->toDateString())->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today->toDateString()))
            ->distinct('requester_id')->count('requester_id');
        $holiday = app(CalendarService::class)->holidayDates($hotel, $today, $today)[$today->toDateString()] ?? null;
        $todayBlock = ['present' => $present, 'working_now' => $working, 'on_vacation' => $vacationToday, 'holiday' => $holiday];

        // ── Ponto: banco de horas agregado e sinais dos últimos 7 dias
        $pos = $neg = 0;
        $negHeavy = 0;
        $deptBank = [];
        foreach ($members as $m) {
            $b = $this->bank->summary($hotel, $m->user, $m->workSchedule)['balance_minutes'];
            $b > 0 ? $pos += $b : $neg += $b;
            if ($b <= -600) {
                $negHeavy++;
            }
            $deptBank[$m->department?->name ?? 'Sem setor'][] = $b;
        }
        $since = $today->subDays(7)->utc();
        $flags = TimeEntry::where('hotel_id', $hotel->id)->where('punched_at', '>=', $since);
        $time = [
            'bank_to_receive_minutes' => $pos,
            'bank_to_compensate_minutes' => abs($neg),
            'people_with_heavy_debt' => $negHeavy,
            'outside_fence_7d' => (clone $flags)->where('outside_fence', true)->count(),
            'outside_network_7d' => (clone $flags)->where('outside_network', true)->count(),
            'new_devices_7d' => (clone $flags)->where('new_device', true)->count(),
            'pending_devices' => TimeDevice::where('hotel_id', $hotel->id)->where('status', 'pending')->count(),
            'pending_adjustments' => TimeAdjustment::where('hotel_id', $hotel->id)->where('status', 'pending')->count(),
        ];

        // ── Solicitações
        $reqs = WorkspaceRequest::where('hotel_id', $hotel->id)->where('created_at', '>=', $today->subDays(90)->utc())->get(['type', 'status', 'created_at', 'decided_at']);
        $decided = $reqs->filter(fn ($r) => $r->decided_at && in_array($r->status, ['approved', 'rejected'], true));
        $requests = [
            'pending' => WorkspaceRequest::where('hotel_id', $hotel->id)->where('status', 'pending')->count(),
            'pending_by_type' => WorkspaceRequest::where('hotel_id', $hotel->id)->where('status', 'pending')->select('type', DB::raw('count(*) as c'))->groupBy('type')->pluck('c', 'type'),
            'decided_90d' => $decided->count(),
            'avg_decision_hours' => $decided->isEmpty() ? null : round($decided->avg(fn ($r) => max(0, $r->created_at->diffInMinutes($r->decided_at)) / 60), 1),
            'approval_rate' => $decided->isEmpty() ? null : round($decided->where('status', 'approved')->count() / $decided->count() * 100),
        ];

        // ── Engajamento (30 dias) e comunicados
        $d30 = $today->subDays(30)->utc();
        $postIds = WorkspacePost::where('hotel_id', $hotel->id)->where('created_at', '>=', $d30)->pluck('id');
        $recentAcks = WorkspacePost::where('hotel_id', $hotel->id)->where('requires_ack', true)->orderByDesc('id')->limit(3)->get()->map(function ($p) {
            $total = HotelUser::where('hotel_id', $p->hotel_id)->where('status', 'approved')->where('user_id', '!=', $p->author_id)
                ->when($p->department_id, fn ($q) => $q->where('department_id', $p->department_id))->count();

            return [
                'title' => mb_substr(preg_replace('/@\[([^\]\n]{1,80})\]\([0-9a-fA-F-]{36}\)/', '@$1', (string) $p->body), 0, 80) ?: 'Comunicado',
                'confirmed' => WorkspacePostAck::where('workspace_post_id', $p->id)->count(), 'total' => $total,
            ];
        })->values();
        $engagement = [
            'posts_30d' => $postIds->count(),
            'reactions_30d' => WorkspacePostLike::whereIn('workspace_post_id', $postIds)->count(),
            'comments_30d' => WorkspacePostComment::whereIn('workspace_post_id', $postIds)->count(),
            'upcoming_events' => CalendarEvent::where('hotel_id', $hotel->id)->where('kind', 'event')->whereDate('starts_on', '>=', $today->toDateString())->whereDate('starts_on', '<=', $today->addDays(30)->toDateString())->count(),
            'recent_acks' => $recentAcks,
        ];

        // ── Segurança
        $privileged = $members->filter(fn ($m) => in_array($this->access->accessRole($m, $hotel), ['owner', 'hr', 'finance', 'director'], true));
        $security = [
            'two_factor_adoption' => $members->isEmpty() ? null : round($members->filter(fn ($m) => $m->user->hasTwoFactor())->count() / $members->count() * 100),
            'privileged_without_2fa' => $privileged->reject(fn ($m) => $m->user->hasTwoFactor())->count(),
            'require_2fa' => (bool) ($hotel->security_settings['require_2fa'] ?? false),
        ];

        // ── Por setor (somas só com grupo mínimo)
        $departments = $byDept->map(fn ($g, $name) => [
            'name' => $name, 'headcount' => $g->count(),
            'bank_minutes' => $g->count() >= self::MIN_GROUP ? array_sum($deptBank[$name] ?? []) : null,
        ])->sortByDesc('headcount')->values();

        return [
            'generated_at' => $now->toIso8601String(),
            'people' => $people, 'today' => $todayBlock, 'time' => $time, 'requests' => $requests,
            'engagement' => $engagement, 'security' => $security, 'departments' => $departments,
            'min_group' => self::MIN_GROUP,
        ];
    }
}
