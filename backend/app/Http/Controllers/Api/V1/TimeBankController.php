<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\TimeBank;
use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeBankAdjustment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Banco de horas do Workspace. */
class TimeBankController extends WorkspaceBaseController
{
    public function __construct(
        WorkspaceAccess $access,
        NotificationService $notifications,
        private readonly TimeBank $bank,
    ) {
        parent::__construct($access, $notifications);
    }

    private function summaryFor(Hotel $hotel, HotelUser $member): array
    {
        return $this->bank->summary($hotel, $member->user, $member->workSchedule);
    }

    private function formatAdjustment(TimeBankAdjustment $a): array
    {
        return [
            'uuid'       => $a->uuid,
            'minutes'    => $a->minutes,
            'reason'     => $a->reason,
            'author'     => $this->access->person($a->author),
            'created_at' => $a->created_at?->toISOString(),
        ];
    }

    /** GET /workspaces/{slug}/time/bank[?user=uuid] — saldo e lançamentos (o próprio, o time ou, para o RH, qualquer um). */
    public function show(Request $request, Hotel $hotel): JsonResponse
    {
        $me = $this->membership($request, $hotel);
        if (! $me) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $request->validate(['user' => ['nullable', 'uuid']]);

        $target = $me;
        if ($request->filled('user') && $request->input('user') !== $request->user()->uuid) {
            $other = $this->access->memberByUuid($hotel, $request->input('user'));
            if (! $other || ! $this->access->canViewPerson($request->user(), $me, $hotel, $other->user)) {
                return $this->forbidden('Sem permissão para ver este banco de horas.');
            }
            $target = $other;
        }
        $target->loadMissing(['user', 'workSchedule']);

        $adjustments = TimeBankAdjustment::with('author')->where('hotel_id', $hotel->id)->where('user_id', $target->user_id)
            ->orderByDesc('id')->limit(30)->get();

        return $this->success('Banco de horas carregado.', $this->summaryFor($hotel, $target) + [
            'user'        => $this->access->person($target->user, $target),
            'adjustments' => $adjustments->map(fn ($a) => $this->formatAdjustment($a))->values(),
        ]);
    }

    /** GET /workspaces/{slug}/time/bank/team — saldo de todos (somente dono e RH). */
    public function team(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->access->isAdmin($this->membership($request, $hotel))) {
            return $this->forbidden('Somente o dono ou o RH veem o banco de horas de todos.');
        }

        $rows = HotelUser::with(['user', 'workSchedule'])->where('hotel_id', $hotel->id)->where('status', 'approved')->get()
            ->map(function (HotelUser $m) use ($hotel) {
                $s = $this->summaryFor($hotel, $m);

                return [
                    'person'          => $this->access->person($m->user, $m),
                    'since'           => $s['since'],
                    'balance_minutes' => $s['balance_minutes'],
                ];
            })->sortBy('balance_minutes')->values();

        return $this->success('Banco de horas do time carregado.', $rows);
    }

    /** POST /workspaces/{slug}/time/bank/adjustments  { user_uuid, minutes (±), reason } — dono e RH. */
    public function adjust(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->access->isAdmin($this->membership($request, $hotel))) {
            return $this->forbidden('Somente o dono ou o RH lançam no banco de horas.');
        }
        $data = $request->validate([
            'user_uuid' => ['required', 'uuid'],
            'minutes'   => ['required', 'integer', 'not_in:0', 'between:-6000,6000'],
            'reason'    => ['required', 'string', 'max:255'],
        ]);

        $target = $this->access->memberByUuid($hotel, $data['user_uuid']);
        if (! $target) {
            return $this->notFound('Pessoa não encontrada.');
        }

        $a = TimeBankAdjustment::create([
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'user_id' => $target->user_id,
            'minutes' => $data['minutes'], 'reason' => $data['reason'], 'created_by' => $request->user()->id,
        ]);
        AuditLog::record($request, $hotel, 'time.bank_adjusted', $target->user, ['minutes' => $data['minutes']]);

        $h = intdiv(abs($data['minutes']), 60);
        $m = abs($data['minutes']) % 60;
        $this->notifications->workspaceNotice(
            $target->user, $request->user(), 'workspace_time_bank',
            ($data['minutes'] > 0 ? 'Crédito' : 'Débito') . " no banco de horas: {$h}h" . str_pad((string) $m, 2, '0', STR_PAD_LEFT),
            $data['reason'], $this->link($hotel, 'ponto'),
        );

        $a->load('author');

        return $this->created('Lançamento registrado.', $this->formatAdjustment($a) + $this->summaryFor($hotel, $target->loadMissing(['user', 'workSchedule'])));
    }
}
