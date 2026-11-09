<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\CalendarService;
use App\Domain\Workspace\Services\TimeClock;
use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\WorkspaceJob;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Destaques da página Início do Workspace (widgets da coluna direita e cartão de aniversariantes). */
class WorkspaceHomeController extends WorkspaceBaseController
{
    private const NEW_HIRE_DAYS = 60;
    private const AHEAD_DAYS    = 30;

    public function __construct(
        WorkspaceAccess $access,
        NotificationService $notifications,
        private readonly CalendarService $calendar,
    ) {
        parent::__construct($access, $notifications);
    }

    /**
     * GET /workspaces/{slug}/home/highlights
     *
     * Aniversários, férias e eventos saem do calendário, então seguem as mesmas regras de visibilidade
     * (férias: a própria pessoa, o gestor/chefe e o RH). Aniversário escondido pela pessoa não entra.
     */
    public function highlights(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $today = CarbonImmutable::now(TimeClock::TZ)->startOfDay();
        $items = collect($this->calendar->items($hotel, $request->user(), $m, $today, $today->addDays(self::AHEAD_DAYS)));
        $day   = $today->toDateString();

        $birthdays = $items->where('type', 'birthday')->where('date', $day)->reject(fn ($i) => $i['hidden_from_team'] ?? false)
            ->map(fn ($i) => $i['person'])->values();

        // em andamento primeiro, depois as próximas
        $absences = $items->where('type', 'vacation')
            ->map(fn ($i) => [
                'person' => $i['person'], 'kind' => 'vacation', 'label' => 'Férias',
                'starts_on' => $i['date'], 'ends_on' => $i['end_date'] ?? $i['date'], 'ongoing' => $i['date'] <= $day,
            ])
            ->sortBy(fn ($a) => [$a['ongoing'] ? 0 : 1, $a['starts_on']])->values();

        $events = $items->whereIn('type', ['event', 'holiday'])->take(5)
            ->map(fn ($i) => ['type' => $i['type'], 'title' => $i['title'], 'date' => $i['date'], 'time' => $i['time'] ?? null, 'subtitle' => $i['subtitle'] ?? null])
            ->values();

        $newHires = HotelUser::with(['user', 'department'])->where('hotel_id', $hotel->id)->where('status', 'approved')
            ->whereDate('hired_at', '>=', $today->subDays(self::NEW_HIRE_DAYS)->toDateString())
            ->whereDate('hired_at', '<=', $day)
            ->orderByDesc('hired_at')->limit(10)->get()
            ->filter(fn (HotelUser $h) => $h->user)
            ->map(fn (HotelUser $h) => [
                'person' => $this->access->person($h->user, $h), 'department' => $h->department?->name, 'hired_at' => $h->hired_at->toDateString(),
            ])->values();

        $jobs = WorkspaceJob::with('department')->where('hotel_id', $hotel->id)->orderByDesc('created_at')->limit(30)->get()
            ->filter(fn (WorkspaceJob $j) => $j->isOpen())->take(6)
            ->map(fn (WorkspaceJob $j) => [
                'uuid' => $j->uuid, 'title' => $j->title, 'department' => $j->department?->name,
                'contract_type' => $j->contract_type, 'closes_on' => $j->closes_on?->toDateString(),
            ])->values();

        return $this->success('Destaques carregados.', [
            'birthdays_today' => $birthdays,
            'absences'        => $absences,
            'new_hires'       => $newHires,
            'jobs'            => $jobs,
            'events'          => $events,
        ]);
    }
}
