<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\TimeClock;
use App\Domain\Workspace\Services\TimeClosingService;
use App\Domain\Workspace\Services\WorkspaceAccess;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\TimeClosing;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Fechamento mensal do ponto para a folha. Dono e RH fecham e exportam; só o dono reabre. */
class TimeClosingController extends WorkspaceBaseController
{
    public function __construct(
        WorkspaceAccess $access,
        NotificationService $notifications,
        private readonly TimeClosingService $closing,
    ) {
        parent::__construct($access, $notifications);
    }

    private function denied(Request $request, Hotel $hotel): ?JsonResponse
    {
        return $this->access->isAdmin($this->membership($request, $hotel))
            ? null
            : $this->forbidden('Somente o dono ou o RH acessam o fechamento do ponto.');
    }

    private function validMonth(string $month): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month);
    }

    private function formatClosing(TimeClosing $c): array
    {
        return [
            'month'     => $c->month,
            'status'    => 'closed',
            'closed_at' => $c->closed_at?->toISOString(),
            'closed_by' => $this->access->person($c->closer),
            'people'    => count($c->rows ?? []),
        ];
    }

    /** GET /workspaces/{slug}/time/closings — últimos 12 meses terminados, fechados ou não. */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }

        $closed = TimeClosing::with('closer')->where('hotel_id', $hotel->id)->get()->keyBy('month');
        $now    = CarbonImmutable::now(TimeClock::TZ)->startOfMonth();
        $items  = [];
        for ($i = 1; $i <= 12; $i++) {
            $month = $now->subMonths($i)->format('Y-m');
            $items[] = $closed->has($month)
                ? $this->formatClosing($closed[$month])
                : ['month' => $month, 'status' => 'open', 'closed_at' => null, 'closed_by' => null, 'people' => null,
                   'pending_adjustments' => $this->closing->pendingAdjustments($hotel, $month)];
        }

        return $this->success('Fechamentos carregados.', $items);
    }

    /** GET /workspaces/{slug}/time/closings/{month} — retrato gravado (fechado) ou prévia calculada (aberto). */
    public function show(Request $request, Hotel $hotel, string $month): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }
        if (! $this->validMonth($month)) {
            return $this->notFound('Mês inválido.');
        }

        $c = TimeClosing::with('closer')->where('hotel_id', $hotel->id)->where('month', $month)->first();
        if ($c) {
            return $this->success('Fechamento carregado.', $this->formatClosing($c) + ['rows' => $c->rows]);
        }
        if (! $this->closing->hasEnded($month)) {
            return $this->error('Este mês ainda não terminou.', [], 422);
        }

        return $this->success('Prévia do fechamento carregada.', [
            'month' => $month, 'status' => 'open', 'closed_at' => null, 'closed_by' => null,
            'pending_adjustments' => $this->closing->pendingAdjustments($hotel, $month),
            'rows' => $this->closing->rows($hotel, $month),
        ]);
    }

    /** POST /workspaces/{slug}/time/closings  { month } */
    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }
        $data = $request->validate(['month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $month = $data['month'];

        if (! $this->closing->hasEnded($month)) {
            return $this->error('Só é possível fechar um mês depois que ele terminar.', [], 422);
        }
        if (TimeClosing::where('hotel_id', $hotel->id)->where('month', $month)->exists()) {
            return $this->error('Este mês já está fechado.', [], 409);
        }
        $pending = $this->closing->pendingAdjustments($hotel, $month);
        if ($pending > 0) {
            return $this->error("Há {$pending} pedido(s) de ajuste de ponto pendente(s) neste mês. Aprove ou recuse antes de fechar.", [], 422);
        }

        $c = TimeClosing::create([
            'uuid' => (string) Str::uuid(), 'hotel_id' => $hotel->id, 'month' => $month,
            'rows' => $this->closing->rows($hotel, $month),
            'closed_by' => $request->user()->id, 'closed_at' => now(),
        ]);
        AuditLog::record($request, $hotel, 'time.closed', null, ['month' => $month]);
        $c->load('closer');

        return $this->created('Mês fechado com sucesso.', $this->formatClosing($c) + ['rows' => $c->rows]);
    }

    /** DELETE /workspaces/{slug}/time/closings/{month} — reabre (somente o dono). */
    public function destroy(Request $request, Hotel $hotel, string $month): JsonResponse
    {
        if (! $this->access->isOwner($this->membership($request, $hotel))) {
            return $this->forbidden('Somente o dono pode reabrir um mês fechado.');
        }
        $c = $this->validMonth($month) ? TimeClosing::where('hotel_id', $hotel->id)->where('month', $month)->first() : null;
        if (! $c) {
            return $this->notFound('Fechamento não encontrado.');
        }

        $c->delete();
        AuditLog::record($request, $hotel, 'time.reopened', null, ['month' => $month]);

        return $this->success('Mês reaberto com sucesso.');
    }

    private function hhmm(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '';
        $a = abs($minutes);

        return sprintf('%s%d:%02d', $sign, intdiv($a, 60), $a % 60);
    }

    /** GET /workspaces/{slug}/time/closings/{month}/export — CSV para a folha (só mês fechado). */
    public function export(Request $request, Hotel $hotel, string $month): Response|JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }
        $c = $this->validMonth($month) ? TimeClosing::where('hotel_id', $hotel->id)->where('month', $month)->first() : null;
        if (! $c) {
            return $this->error('Feche o mês antes de exportar.', [], 422);
        }

        $esc = fn (?string $v) => '"' . str_replace('"', '""', (string) $v) . '"';
        $lines = [implode(';', ['Nome', 'Cargo', 'Setor', 'Dias trabalhados', 'Faltas (dias)', 'Horas trabalhadas', 'Horas esperadas', 'Saldo do mês', 'Banco de horas'])];
        foreach ($c->rows as $r) {
            $lines[] = implode(';', [
                $esc($r['name']), $esc($r['job_title'] ?? ''), $esc($r['department'] ?? ''),
                $r['days_worked'], $r['absent_days'],
                $this->hhmm($r['worked_minutes']), $this->hhmm($r['expected_minutes']),
                $this->hhmm($r['balance_minutes']), $this->hhmm($r['bank_minutes']),
            ]);
        }
        AuditLog::record($request, $hotel, 'time.exported', null, ['month' => $month]);

        return response("\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n", 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"ponto-{$month}.csv\"",
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
