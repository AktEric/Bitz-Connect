<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\CalendarService;
use App\Domain\Workspace\Services\TimeClock;
use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\CalendarEventRsvp;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Calendário do Workspace: aniversários, tempo de casa, feriados, férias e eventos. */
class CalendarController extends WorkspaceBaseController
{
    public function __construct(
        WorkspaceAccess $access,
        NotificationService $notifications,
        private readonly CalendarService $calendar,
    ) {
        parent::__construct($access, $notifications);
    }

    private function canCreate(Request $request, Hotel $hotel, HotelUser $m): bool
    {
        return $this->access->isAdmin($m) || $this->access->isManager($request->user(), $hotel);
    }

    /** GET /workspaces/{slug}/calendar?from=YYYY-MM-DD&to=YYYY-MM-DD (máx. 100 dias; padrão: mês atual) */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);

        $today = CarbonImmutable::now(TimeClock::TZ)->startOfDay();
        $from  = $request->filled('from') ? CarbonImmutable::parse($request->input('from'), TimeClock::TZ) : $today->startOfMonth();
        $to    = $request->filled('to') ? CarbonImmutable::parse($request->input('to'), TimeClock::TZ) : $from->endOfMonth()->startOfDay();
        if ($to < $from || $from->diffInDays($to) > 100) {
            return $this->error('Período inválido (máximo de 100 dias).', [], 422);
        }

        return $this->success('Calendário carregado.', [
            'from'       => $from->toDateString(),
            'to'         => $to->toDateString(),
            'items'      => $this->calendar->items($hotel, $request->user(), $m, $from, $to),
            'can_create' => $this->canCreate($request, $hotel, $m),
            'can_holiday' => $this->access->isAdmin($m),
            'me'         => ['birth_date' => $m->birth_date?->toDateString(), 'birthday_visible' => (bool) $m->birthday_visible],
        ]);
    }

    private function eventRules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:120'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'kind'            => ['sometimes', 'in:event,holiday'],
            'starts_on'       => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:2100-12-31'],
            'ends_on'         => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'starts_time'     => ['nullable', 'date_format:H:i'],
            'ends_time'       => ['nullable', 'date_format:H:i', 'required_with:starts_time'],
            'location'        => ['nullable', 'string', 'max:120'],
            'department_uuid' => ['nullable', 'uuid'],
        ];
    }

    private function eventFields(Request $request, Hotel $hotel, HotelUser $m, array $data): array|JsonResponse
    {
        $kind = $data['kind'] ?? 'event';
        if ($kind === 'holiday' && ! $this->access->isAdmin($m)) {
            return $this->forbidden('Somente o dono ou o RH cadastram feriados da empresa.');
        }
        $deptId = null;
        if (! empty($data['department_uuid']) && $kind !== 'holiday') {
            $dept = $hotel->departments()->where('uuid', $data['department_uuid'])->first();
            if (! $dept) {
                return $this->error('Setor inválido.', ['department_uuid' => ['Setor não encontrado.']], 422);
            }
            $deptId = $dept->id;
        }

        return [
            'title' => $data['title'], 'description' => $data['description'] ?? null, 'kind' => $kind,
            'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'] ?? null,
            'starts_time' => $kind === 'holiday' ? null : ($data['starts_time'] ?? null),
            'ends_time' => $kind === 'holiday' ? null : ($data['ends_time'] ?? null),
            'location' => $data['location'] ?? null, 'department_id' => $deptId,
        ];
    }

    /** POST /workspaces/{slug}/calendar/events */
    public function storeEvent(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m || ! $this->canCreate($request, $hotel, $m)) {
            return $this->forbidden('Somente RH e gestores criam eventos.');
        }
        $data   = $request->validate($this->eventRules());
        $fields = $this->eventFields($request, $hotel, $m, $data);
        if ($fields instanceof JsonResponse) {
            return $fields;
        }

        $e = CalendarEvent::create($fields + ['uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'created_by' => $request->user()->id]);
        AuditLog::record($request, $hotel, 'calendar.event_created', null, ['title' => $e->title, 'kind' => $e->kind]);

        return $this->created('Evento criado com sucesso.', ['uuid' => $e->uuid]);
    }

    /** PATCH /workspaces/{slug}/calendar/events/{uuid} — autor ou RH. */
    public function updateEvent(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $e = $m ? CalendarEvent::where('hotel_id', $hotel->id)->where('uuid', $uuid)->first() : null;
        if (! $e || ($e->created_by !== $request->user()->id && ! $this->access->isAdmin($m))) {
            return $this->notFound('Evento não encontrado.');
        }
        $data   = $request->validate($this->eventRules());
        $fields = $this->eventFields($request, $hotel, $m, $data);
        if ($fields instanceof JsonResponse) {
            return $fields;
        }
        $e->update($fields);

        return $this->success('Evento atualizado com sucesso.', ['uuid' => $e->uuid]);
    }

    public function destroyEvent(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $e = $m ? CalendarEvent::where('hotel_id', $hotel->id)->where('uuid', $uuid)->first() : null;
        if (! $e || ($e->created_by !== $request->user()->id && ! $this->access->isAdmin($m))) {
            return $this->notFound('Evento não encontrado.');
        }
        AuditLog::record($request, $hotel, 'calendar.event_deleted', null, ['title' => $e->title]);
        $e->delete();

        return $this->success('Evento removido com sucesso.');
    }

    /** POST /workspaces/{slug}/calendar/events/{uuid}/rsvp  { status: going|maybe|no|null } */
    public function rsvp(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        $e = $m ? CalendarEvent::where('hotel_id', $hotel->id)->where('uuid', $uuid)->where('kind', 'event')->first() : null;
        if (! $e || ($e->department_id && $e->department_id !== $m->department_id && ! $this->access->isAdmin($m) && $e->created_by !== $request->user()->id)) {
            return $this->notFound('Evento não encontrado.');
        }
        $data = $request->validate(['status' => ['nullable', 'in:going,maybe,no']]);

        if (empty($data['status'])) {
            CalendarEventRsvp::where('calendar_event_id', $e->id)->where('user_id', $request->user()->id)->delete();
        } else {
            CalendarEventRsvp::updateOrCreate(['calendar_event_id' => $e->id, 'user_id' => $request->user()->id], ['status' => $data['status']]);
        }
        $count = fn (string $s) => CalendarEventRsvp::where('calendar_event_id', $e->id)->where('status', $s)->count();

        return $this->success('Presença atualizada.', [
            'mine' => $data['status'] ?? null, 'going' => $count('going'), 'maybe' => $count('maybe'), 'no' => $count('no'),
        ]);
    }

    /** PATCH /workspaces/{slug}/calendar/me  { birth_date: Y-m-d|null, birthday_visible: bool } — dado da própria pessoa. */
    public function updateMyBirthday(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $data = $request->validate([
            'birth_date'       => ['nullable', 'date_format:Y-m-d', 'after:1920-01-01', 'before:today'],
            'birthday_visible' => ['sometimes', 'boolean'],
        ]);
        $changes = [];
        if (array_key_exists('birth_date', $data)) {
            $changes['birth_date'] = $data['birth_date'];
        }
        if (array_key_exists('birthday_visible', $data)) {
            $changes['birthday_visible'] = $data['birthday_visible'];
        }
        $m->update($changes);

        return $this->success('Aniversário atualizado.', ['birth_date' => $m->birth_date?->toDateString(), 'birthday_visible' => (bool) $m->birthday_visible]);
    }

    /** PATCH /workspaces/{slug}/calendar/birthdays/{uuid}  { birth_date } — RH cadastra a data de outra pessoa. */
    public function setBirthday(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if (! $this->access->isAdmin($this->membership($request, $hotel))) {
            return $this->forbidden('Somente o dono ou o RH cadastram a data de outra pessoa.');
        }
        $data   = $request->validate(['birth_date' => ['nullable', 'date_format:Y-m-d', 'after:1920-01-01', 'before:today']]);
        $target = $this->access->memberByUuid($hotel, $uuid);
        if (! $target) {
            return $this->notFound('Pessoa não encontrada.');
        }
        $target->update(['birth_date' => $data['birth_date'] ?? null]);
        AuditLog::record($request, $hotel, 'calendar.birthday_set', $target->user);

        return $this->success('Data de nascimento atualizada.');
    }

    // ── Assinatura do calendário (ICS) ────────────────────────

    private function icsUrl(string $token): string
    {
        return url("/api/v1/calendar-feed/{$token}.ics");
    }

    private function ensureToken(HotelUser $m, bool $rotate = false): string
    {
        if ($m->calendar_token && ! $rotate) {
            return $m->calendar_token;
        }
        do {
            $token = Str::random(40);
        } while (HotelUser::where('calendar_token', $token)->exists());
        $m->forceFill(['calendar_token' => $token])->save();

        return $token;
    }

    /** GET /workspaces/{slug}/calendar/ics-link — link pessoal para assinar no Google/Outlook/Apple. */
    public function icsLink(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        return $this->success('Link de assinatura.', ['url' => $this->icsUrl($this->ensureToken($m))]);
    }

    /** POST /workspaces/{slug}/calendar/ics-link — gera um link novo e invalida o anterior. */
    public function rotateIcsLink(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }

        return $this->success('Link renovado. O anterior deixou de funcionar.', ['url' => $this->icsUrl($this->ensureToken($m, true))]);
    }

    /** GET /calendar-feed/{token}.ics — pública, mas só com o segredo pessoal; mostra o que a pessoa já veria no app. */
    public function feed(string $token): Response|JsonResponse
    {
        $m = HotelUser::with(['user', 'hotel'])->where('calendar_token', $token)->where('status', 'approved')->first();
        if (! $m || ! $m->user || ! $m->hotel) {
            return $this->notFound('Calendário não encontrado.');
        }
        $today = CarbonImmutable::now(TimeClock::TZ)->startOfDay();
        $items = $this->calendar->items($m->hotel, $m->user, $m, $today->subDays(30), $today->addDays(90));

        return response($this->calendar->ics($m->hotel, $items), 200, [
            'Content-Type'           => 'text/calendar; charset=UTF-8',
            'Content-Disposition'    => 'inline; filename="calendario.ics"',
            'Cache-Control'          => 'private, max-age=900',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
