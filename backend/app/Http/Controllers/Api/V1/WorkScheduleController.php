<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\WorkSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Jornadas de trabalho (escalas) por empresa e sua atribuição às pessoas. Somente dono e RH. */
class WorkScheduleController extends WorkspaceBaseController
{
    private function denied(Request $request, Hotel $hotel): ?JsonResponse
    {
        return $this->access->isAdmin($this->membership($request, $hotel))
            ? null
            : $this->forbidden('Somente o dono ou o RH gerenciam jornadas.');
    }

    private function week(array $input): array
    {
        $week = [];
        for ($d = 0; $d <= 6; $d++) {
            $week[$d] = (int) ($input[$d] ?? $input[(string) $d] ?? 0);
        }

        return $week;
    }

    private function format(WorkSchedule $s): array
    {
        return [
            'uuid'          => $s->uuid,
            'name'          => $s->name,
            'week_minutes'  => $this->week($s->week_minutes ?? []),
            'weekly_total'  => array_sum($this->week($s->week_minutes ?? [])),
            'members_count' => $s->members_count ?? $s->members()->count(),
        ];
    }

    private function rules(Hotel $hotel, ?WorkSchedule $current = null): array
    {
        return [
            'name'           => ['required', 'string', 'max:80', Rule::unique('work_schedules', 'name')->where('hotel_id', $hotel->id)->ignore($current?->id)],
            'week_minutes'   => ['required', 'array'],
            'week_minutes.*' => ['integer', 'min:0', 'max:1440'],
        ];
    }

    /** GET /workspaces/{slug}/schedules — jornadas e quem está em cada uma. */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }

        $schedules = WorkSchedule::withCount('members')->where('hotel_id', $hotel->id)->orderBy('name')->get();
        $members   = HotelUser::with(['user', 'workSchedule'])->where('hotel_id', $hotel->id)->where('status', 'approved')->get()
            ->map(fn (HotelUser $hu) => [
                'user'          => $this->access->person($hu->user, $hu),
                'schedule_uuid' => $hu->workSchedule?->uuid,
            ])->sortBy(fn ($r) => $r['user']['name'])->values();

        return $this->success('Jornadas carregadas com sucesso.', [
            'default'   => ['name' => 'Padrão (8h, segunda a sexta)', 'week_minutes' => WorkSchedule::DEFAULT_WEEK],
            'schedules' => $schedules->map(fn ($s) => $this->format($s))->values(),
            'members'   => $members,
        ]);
    }

    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }
        $data = $request->validate($this->rules($hotel));

        $s = WorkSchedule::create([
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id,
            'name' => $data['name'], 'week_minutes' => $this->week($data['week_minutes']),
        ]);

        return $this->created('Jornada criada com sucesso.', $this->format($s));
    }

    public function update(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }
        $s = WorkSchedule::where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();
        if (! $s) {
            return $this->notFound('Jornada não encontrada.');
        }
        $data = $request->validate($this->rules($hotel, $s));
        $s->update(['name' => $data['name'], 'week_minutes' => $this->week($data['week_minutes'])]);

        return $this->success('Jornada atualizada com sucesso.', $this->format($s));
    }

    public function destroy(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }
        $s = WorkSchedule::where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();
        if (! $s) {
            return $this->notFound('Jornada não encontrada.');
        }
        $s->delete(); // quem estava nela volta à jornada padrão (FK nullOnDelete)

        return $this->success('Jornada removida com sucesso.');
    }

    /** PATCH /workspaces/{slug}/schedules/assign  { user_uuid, schedule_uuid|null } */
    public function assign(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }
        $data = $request->validate([
            'user_uuid'     => ['required', 'uuid'],
            'schedule_uuid' => ['nullable', 'uuid'],
        ]);

        $target = $this->access->memberByUuid($hotel, $data['user_uuid']);
        if (! $target) {
            return $this->notFound('Pessoa não encontrada.');
        }
        $schedule = null;
        if (! empty($data['schedule_uuid'])) {
            $schedule = WorkSchedule::where('hotel_id', $hotel->id)->where('uuid', $data['schedule_uuid'])->first();
            if (! $schedule) {
                return $this->notFound('Jornada não encontrada.');
            }
        }

        $target->update(['work_schedule_id' => $schedule?->id]);
        AuditLog::record($request, $hotel, 'schedule.assigned', $target->user, ['schedule' => $schedule?->name]);

        return $this->success('Jornada atribuída com sucesso.', [
            'user_uuid' => $data['user_uuid'], 'schedule_uuid' => $schedule?->uuid,
        ]);
    }
}
