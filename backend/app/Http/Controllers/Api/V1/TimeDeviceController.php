<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\PunchRules;
use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\TimeDevice;
use App\Models\TimeEntry;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Dispositivos de marcação: o colaborador consulta o do aparelho atual; dono e RH aprovam, bloqueiam e removem. */
class TimeDeviceController extends WorkspaceBaseController
{
    public function __construct(
        WorkspaceAccess $access,
        NotificationService $notifications,
        private readonly PunchRules $rules,
    ) {
        parent::__construct($access, $notifications);
    }

    private function denied(Request $request, Hotel $hotel): ?JsonResponse
    {
        return $this->access->isAdmin($this->membership($request, $hotel))
            ? null
            : $this->forbidden('Somente o dono ou o RH gerenciam os dispositivos do ponto.');
    }

    /** GET /workspaces/{slug}/time/device — estado do aparelho atual (cabeçalho X-Device-Key). */
    public function mine(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->membership($request, $hotel)) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $mode = $this->rules->settings($hotel)['devices'];
        $key  = (string) $request->header('X-Device-Key', '');
        $device = ($mode !== 'off' && preg_match('/^[A-Za-z0-9_-]{16,128}$/', $key))
            ? TimeDevice::where('hotel_id', $hotel->id)->where('key_hash', hash('sha256', $key))->first()
            : null;

        return $this->success('Dispositivo consultado.', [
            'mode'   => $mode,
            'status' => $device?->status ?? 'unknown',
            'name'   => $device?->name,
        ]);
    }

    /** GET /workspaces/{slug}/time/devices */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }

        $devices = TimeDevice::with('registrar')->where('hotel_id', $hotel->id)
            ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 else 2 end")->orderByDesc('last_seen_at')->get();
        $users = TimeEntry::whereIn('device_id', $devices->pluck('id'))
            ->select('device_id', DB::raw('count(distinct user_id) as c'))->groupBy('device_id')->pluck('c', 'device_id');

        return $this->success('Dispositivos carregados.', $devices->map(fn (TimeDevice $d) => [
            'uuid'          => $d->uuid,
            'name'          => $d->name,
            'status'        => $d->status,
            'label'         => $d->label,
            'last_ip'       => $d->last_ip,
            'last_seen_at'  => $d->last_seen_at?->toISOString(),
            'registered_by' => $this->access->person($d->registrar),
            'users_count'   => (int) ($users[$d->id] ?? 0),
        ])->values());
    }

    /** PATCH /workspaces/{slug}/time/devices/{uuid}  { name?, status?: approved|blocked|pending } */
    public function update(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }
        $data = $request->validate([
            'name'   => ['sometimes', 'string', 'min:2', 'max:80'],
            'status' => ['sometimes', 'in:approved,blocked,pending'],
        ]);
        $d = TimeDevice::where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();
        if (! $d) {
            return $this->notFound('Dispositivo não encontrado.');
        }

        $changes = [];
        if (isset($data['name'])) {
            $changes['name'] = trim($data['name']);
        }
        if (isset($data['status']) && $data['status'] !== $d->status) {
            $changes['status'] = $data['status'];
            if ($data['status'] === 'approved') {
                $changes += ['approved_by' => $request->user()->id, 'approved_at' => now()];
            }
        }
        $d->update($changes);

        if (isset($changes['status'])) {
            AuditLog::record($request, $hotel, 'time.device_' . $changes['status'], null, ['device' => $d->name]);
        }

        return $this->success('Dispositivo atualizado.', ['uuid' => $d->uuid, 'name' => $d->name, 'status' => $d->status]);
    }

    /** DELETE /workspaces/{slug}/time/devices/{uuid} — remove o cadastro (as marcações antigas mantêm o nome do aparelho). */
    public function destroy(Request $request, Hotel $hotel, string $uuid): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }
        $d = TimeDevice::where('hotel_id', $hotel->id)->where('uuid', $uuid)->first();
        if (! $d) {
            return $this->notFound('Dispositivo não encontrado.');
        }
        AuditLog::record($request, $hotel, 'time.device_removed', null, ['device' => $d->name]);
        $d->delete();

        return $this->success('Dispositivo removido.');
    }
}
