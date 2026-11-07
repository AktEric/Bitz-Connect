<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\TimeClock;
use App\Domain\Workspace\Services\PunchRules;
use App\Domain\Workspace\Services\TimeClosingService;
use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeAdjustment;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ponto eletrônico do Workspace. */
class TimeClockController extends WorkspaceBaseController
{
    public function __construct(
        WorkspaceAccess $access,
        NotificationService $notifications,
        private readonly TimeClock $clock,
        private readonly TimeClosingService $closing,
        private readonly PunchRules $rules,
    ) {
        parent::__construct($access, $notifications);
    }

    private function dayEntries(Hotel $hotel, User $user, CarbonImmutable $day)
    {
        $from = $day->startOfDay()->utc();
        $to   = $day->endOfDay()->utc();

        return TimeEntry::where('hotel_id', $hotel->id)->where('user_id', $user->id)
            ->whereBetween('punched_at', [$from, $to])->orderBy('punched_at')->get();
    }

    private function scheduleFor(Hotel $hotel, User $user): ?WorkSchedule
    {
        return HotelUser::with('workSchedule')->where('hotel_id', $hotel->id)->where('user_id', $user->id)
            ->where('status', 'approved')->first()?->workSchedule;
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(TimeClock::TZ);
    }

    /** GET /workspaces/{slug}/time/today */
    public function today_(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->membership($request, $hotel)) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        return $this->success('Ponto carregado com sucesso.', $this->todayPayload($hotel, $request->user()));
    }

    private function todayPayload(Hotel $hotel, User $user): array
    {
        $now     = $this->today();
        $entries = $this->dayEntries($hotel, $user, $now);

        return [
            'state'          => $this->clock->state($entries),
            'allowed_next'   => $this->clock->allowedNext($entries),
            'worked_minutes' => $this->clock->workedMinutes($entries, $now),
            'daily_minutes'  => WorkSchedule::minutesFor($this->scheduleFor($hotel, $user), $now->dayOfWeek),
            'server_time'    => $now->toIso8601String(),
            'requirements'   => $this->rules->requirements($hotel),
            'entries'        => $entries->map(fn ($e) => $this->clock->format($e))->values(),
        ];
    }

    /** GET /workspaces/{slug}/time/entries/{uuid}/photo — foto da marcação (dono da marcação, gestor ou RH). */
    public function photo(Request $request, Hotel $hotel, string $uuid): StreamedResponse|JsonResponse
    {
        $me    = $this->membership($request, $hotel);
        $entry = $me ? TimeEntry::with('user')->where('hotel_id', $hotel->id)->where('uuid', $uuid)->first() : null;
        if (! $entry || ! $entry->photo_path || ! Storage::disk('local')->exists($entry->photo_path)) {
            return $this->notFound('Foto não encontrada.');
        }
        if ($entry->user_id !== $request->user()->id) {
            if (! $this->access->canViewPerson($request->user(), $me, $hotel, $entry->user)) {
                return $this->notFound('Foto não encontrada.');
            }
            AuditLog::record($request, $hotel, 'time.photo_viewed', $entry->user);
        }

        return Storage::disk('local')->response($entry->photo_path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, no-store',
        ]);
    }

    /** POST /workspaces/{slug}/time/punch  { type?: break_start|break_end|out|in } */
    public function punch(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->membership($request, $hotel)) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        $data = $request->validate([
            'type'   => ['nullable', 'string', 'in:' . implode(',', TimeEntry::TYPES)],
            'source' => ['nullable', 'string', 'in:web,mobile'],
            'lat'      => ['nullable', 'numeric', 'between:-90,90'],
            'lng'      => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'photo'    => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:3072'],
        ]);

        $user    = $request->user();
        $now     = $this->today();
        $entries = $this->dayEntries($hotel, $user, $now);
        $allowed = $this->clock->allowedNext($entries);
        $type    = $data['type'] ?? $allowed[0];

        if (! in_array($type, $allowed, true)) {
            return $this->error('Essa marcação não é válida agora.', [], 422);
        }
        $last = $entries->last();
        if ($last && $last->punched_at->diffInSeconds($now, false) < 15) {
            return $this->error('Marcação repetida. Aguarde alguns segundos.', [], 429);
        }

        $rules   = $this->rules->settings($hotel);
        $hasGeo  = isset($data['lat'], $data['lng']);
        if ($rules['geo'] === 'required' && ! $hasGeo) {
            return $this->error('Esta empresa exige a localização para marcar o ponto. Permita o acesso à localização no navegador.', [], 422);
        }
        if ($rules['photo'] === 'required' && ! $request->hasFile('photo')) {
            return $this->error('Esta empresa exige uma foto para marcar o ponto.', [], 422);
        }

        $outside = false;
        if ($hasGeo && $rules['fence']) {
            $distance = $this->rules->distanceMeters((float) $data['lat'], (float) $data['lng'], $rules['fence']['lat'], $rules['fence']['lng']);
            $outside  = $distance > $rules['fence']['radius_m'];
            if ($outside && $rules['fence']['block']) {
                return $this->error('Você está fora do local de trabalho. Aproxime-se para marcar o ponto.', [], 422);
            }
        }

        $photoPath = $request->hasFile('photo') && $rules['photo'] !== 'off'
            ? \App\Support\ImageSanitizer::store($request->file('photo'), "punch-photos/{$hotel->id}", 'local')
            : null;

        TimeEntry::create([
            'uuid'       => (string) Str::uuid(),
            'hotel_id'   => $hotel->id,
            'user_id'    => $user->id,
            'type'       => $type,
            'punched_at' => $now->utc(),
            'source'     => $data['source'] ?? 'web',
            'ip'         => $request->ip(),
            'lat'        => $rules['geo'] === 'off' ? null : ($data['lat'] ?? null),
            'lng'        => $rules['geo'] === 'off' ? null : ($data['lng'] ?? null),
            'accuracy_m' => $rules['geo'] === 'off' || ! isset($data['accuracy']) ? null : (int) round($data['accuracy']),
            'photo_path' => $photoPath,
            'outside_fence' => $outside,
        ]);

        return $this->created('Ponto registrado.', $this->todayPayload($hotel, $user));
    }

    /** GET /workspaces/{slug}/time/timesheet?month=YYYY-MM&user=uuid */
    public function timesheet(Request $request, Hotel $hotel): JsonResponse
    {
        $membership = $this->membership($request, $hotel);
        if (! $membership) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'user' => ['nullable', 'uuid']]);

        $target = $request->user();
        if ($request->filled('user') && $request->input('user') !== $target->uuid) {
            $other = $this->access->memberByUuid($hotel, $request->input('user'));
            if (! $other || ! $this->access->canViewPerson($target, $membership, $hotel, $other->user)) {
                return $this->forbidden('Sem permissão para ver este espelho de ponto.');
            }
            $target = $other->user;
        }

        $now   = $this->today();
        $first = $request->filled('month')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->input('month') . '-01', TimeClock::TZ)->startOfDay()
            : $now->startOfMonth();
        $last  = $first->endOfMonth();

        $all = TimeEntry::where('hotel_id', $hotel->id)->where('user_id', $target->id)
            ->whereBetween('punched_at', [$first->utc(), $last->utc()])->orderBy('punched_at')->get()
            ->groupBy(fn ($e) => CarbonImmutable::instance($e->punched_at)->setTimezone(TimeClock::TZ)->toDateString());

        $schedule = $this->scheduleFor($hotel, $target);
        $days = [];
        $worked = 0;
        $expected = 0;
        for ($d = $first; $d <= $last; $d = $d->addDay()) {
            $key     = $d->toDateString();
            $entries = $all->get($key, collect());
            $mins    = $this->clock->workedMinutes($entries, $key === $now->toDateString() ? $now : null);
            $exp     = $d <= $now ? WorkSchedule::minutesFor($schedule, $d->dayOfWeek) : 0;
            $worked   += $mins;
            $expected += $exp;
            $days[] = [
                'date'             => $key,
                'weekday'          => $d->dayOfWeek,
                'worked_minutes'   => $mins,
                'expected_minutes' => $exp,
                'entries'          => $entries->map(fn ($e) => $this->clock->format($e) + [
                    'local_time' => CarbonImmutable::instance($e->punched_at)->setTimezone(TimeClock::TZ)->format('H:i'),
                ])->values(),
            ];
        }

        return $this->success('Espelho de ponto carregado.', [
            'month'            => $first->format('Y-m'),
            'user'             => $this->access->person($target),
            'worked_minutes'   => $worked,
            'expected_minutes' => $expected,
            'balance_minutes'  => $worked - $expected,
            'days'             => $days,
        ]);
    }

    /** GET /workspaces/{slug}/time/team — quem está trabalhando agora (RH vê todos; gestor, o time). */
    public function team(Request $request, Hotel $hotel): JsonResponse
    {
        $membership = $this->membership($request, $hotel);
        if (! $membership) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $isAdmin = $this->access->isAdmin($membership);
        if (! $isAdmin && ! $this->access->isManager($request->user(), $hotel)) {
            return $this->forbidden('Apenas RH ou gestores veem o ponto do time.');
        }

        $q = HotelUser::with(['user', 'workSchedule'])->where('hotel_id', $hotel->id)->where('status', 'approved');
        if (! $isAdmin) {
            $q->whereIn('user_id', $this->access->reportIds($request->user(), $hotel) ?: [0]);
        }
        $members = $q->get();

        $now = $this->today();
        $entries = TimeEntry::where('hotel_id', $hotel->id)->whereIn('user_id', $members->pluck('user_id'))
            ->whereBetween('punched_at', [$now->startOfDay()->utc(), $now->endOfDay()->utc()])
            ->orderBy('punched_at')->get()->groupBy('user_id');

        $rows = $members->map(function (HotelUser $m) use ($entries, $now) {
            $e = $entries->get($m->user_id, collect());

            return [
                'person'         => $this->access->person($m->user, $m),
                'state'          => $this->clock->state($e),
                'worked_minutes' => $this->clock->workedMinutes($e, $now),
                'daily_minutes'  => WorkSchedule::minutesFor($m->workSchedule, $now->dayOfWeek),
                'first_in'       => $e->firstWhere('type', 'in')?->punched_at?->toIso8601String(),
            ];
        })->values();

        return $this->success('Ponto do time carregado.', $rows);
    }

    /** POST /workspaces/{slug}/time/entries — lançamento manual por RH/gestor (com justificativa). */
    public function manual(Request $request, Hotel $hotel): JsonResponse
    {
        $membership = $this->membership($request, $hotel);
        if (! $membership) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $data = $request->validate([
            'user_uuid'  => ['required', 'uuid'],
            'type'       => ['required', 'string', 'in:' . implode(',', TimeEntry::TYPES)],
            'punched_at' => ['required', 'date', 'before_or_equal:now'],
            'note'       => ['required', 'string', 'max:255'],
        ]);

        $target = $this->access->memberByUuid($hotel, $data['user_uuid']);
        if (! $target || ! $this->access->canManagePerson($request->user(), $membership, $hotel, $target->user)) {
            return $this->forbidden('Sem permissão para ajustar o ponto desta pessoa.');
        }

        if ($this->closing->isClosed($hotel, CarbonImmutable::parse($data['punched_at'], TimeClock::TZ))) {
            return $this->error('Este mês já foi fechado para a folha. Peça ao dono para reabri-lo.', [], 422);
        }

        $entry = TimeEntry::create([
            'uuid'       => (string) Str::uuid(),
            'hotel_id'   => $hotel->id,
            'user_id'    => $target->user_id,
            'type'       => $data['type'],
            'punched_at' => CarbonImmutable::parse($data['punched_at'], TimeClock::TZ)->utc(),
            'source'     => 'manual',
            'note'       => $data['note'],
            'created_by' => $request->user()->id,
            'ip'         => $request->ip(),
        ]);

        AuditLog::record($request, $hotel, 'time.manual_entry', $target->user, ['type' => $entry->type]);

        return $this->created('Lançamento registrado.', $this->clock->format($entry));
    }

    private function formatAdjustment(TimeAdjustment $a): array
    {
        return [
            'uuid'         => $a->uuid,
            'type'         => $a->type,
            'requested_at' => $a->requested_at->toIso8601String(),
            'reason'       => $a->reason,
            'status'       => $a->status,
            'review_note'  => $a->review_note,
            'person'       => $this->access->person($a->user),
        ];
    }

    /** POST /workspaces/{slug}/time/adjustments — colaborador pede inclusão de marcação. */
    public function requestAdjustment(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->membership($request, $hotel)) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $data = $request->validate([
            'type'         => ['required', 'string', 'in:' . implode(',', TimeEntry::TYPES)],
            'requested_at' => ['required', 'date', 'before_or_equal:now', 'after:-45 days'],
            'reason'       => ['required', 'string', 'max:255'],
        ]);

        if ($this->closing->isClosed($hotel, CarbonImmutable::parse($data['requested_at'], TimeClock::TZ))) {
            return $this->error('Este mês já foi fechado para a folha. Fale com o RH.', [], 422);
        }

        $adj = TimeAdjustment::create([
            'uuid'         => (string) Str::uuid(),
            'hotel_id'     => $hotel->id,
            'user_id'      => $request->user()->id,
            'type'         => $data['type'],
            'requested_at' => CarbonImmutable::parse($data['requested_at'], TimeClock::TZ)->utc(),
            'reason'       => $data['reason'],
        ]);

        $m = $this->membership($request, $hotel);
        $reviewers = $m->manager_id
            ? collect([$m->manager])->filter()
            : $this->access->adminUsers($hotel)->reject(fn ($u) => $u->id === $request->user()->id);
        foreach ($reviewers->filter() as $reviewer) {
            $this->notifications->workspaceNotice(
                $reviewer, $request->user(), 'workspace_request',
                "Ajuste de ponto de {$request->user()->name}", $adj->reason, $this->link($hotel, 'ponto?aba=time'),
            );
        }

        return $this->created('Pedido de ajuste enviado.', $this->formatAdjustment($adj->load('user')));
    }

    /** GET /workspaces/{slug}/time/adjustments?scope=mine|review */
    public function adjustments(Request $request, Hotel $hotel): JsonResponse
    {
        $membership = $this->membership($request, $hotel);
        if (! $membership) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $user = $request->user();
        $q = TimeAdjustment::with('user')->where('hotel_id', $hotel->id)->latest();

        if ($request->input('scope') === 'review') {
            $isAdmin = $this->access->isAdmin($membership);
            if (! $isAdmin && ! $this->access->isManager($user, $hotel)) {
                return $this->forbidden('Apenas RH ou gestores analisam ajustes.');
            }
            $q->where('status', 'pending')->where('user_id', '!=', $user->id);
            if (! $isAdmin) {
                $q->whereIn('user_id', $this->access->reportIds($user, $hotel) ?: [0]);
            }
        } else {
            $q->where('user_id', $user->id);
        }

        return $this->success('Ajustes carregados.', $q->limit(50)->get()->map(fn ($a) => $this->formatAdjustment($a))->values());
    }

    /** POST /workspaces/{slug}/time/adjustments/{adjustment}/decision  { decision: approved|rejected, note? } */
    public function decideAdjustment(Request $request, Hotel $hotel, TimeAdjustment $adjustment): JsonResponse
    {
        $membership = $this->membership($request, $hotel);
        if (! $membership || $adjustment->hotel_id !== $hotel->id) {
            return $this->forbidden('Sem permissão.');
        }
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'note'     => ['nullable', 'string', 'max:255'],
        ]);
        if (! $this->access->canManagePerson($request->user(), $membership, $hotel, $adjustment->user)) {
            return $this->forbidden('Sem permissão para decidir este ajuste.');
        }
        if ($adjustment->status !== 'pending') {
            return $this->error('Este pedido já foi analisado.', [], 422);
        }

        if ($data['decision'] === 'approved' && $this->closing->isClosed($hotel, $adjustment->requested_at)) {
            return $this->error('O mês deste ajuste já foi fechado. Reabra o mês antes de aprovar.', [], 422);
        }

        $adjustment->update([
            'status'      => $data['decision'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['note'] ?? null,
        ]);
        AuditLog::record($request, $hotel, 'time.adjustment_decided', $adjustment->user, ['decision' => $data['decision']]);

        $this->notifications->workspaceNotice(
            $adjustment->user, $request->user(), 'workspace_request_decision',
            $data['decision'] === 'approved' ? 'Ajuste de ponto aprovado' : 'Ajuste de ponto recusado',
            $adjustment->reason, $this->link($hotel, 'ponto'),
        );

        if ($data['decision'] === 'approved') {
            TimeEntry::create([
                'uuid'       => (string) Str::uuid(),
                'hotel_id'   => $hotel->id,
                'user_id'    => $adjustment->user_id,
                'type'       => $adjustment->type,
                'punched_at' => $adjustment->requested_at,
                'source'     => 'manual',
                'note'       => 'Ajuste aprovado: ' . $adjustment->reason,
                'created_by' => $request->user()->id,
            ]);
        }

        return $this->success('Ajuste analisado.', $this->formatAdjustment($adjustment->load('user')));
    }
}
