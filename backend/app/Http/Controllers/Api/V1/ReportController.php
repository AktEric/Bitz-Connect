<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\TimeBank;
use App\Domain\Workspace\Services\TimeClock;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeAdjustment;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Relatórios da empresa. Cada um tem um público: dono/RH veem tudo, o gestor só o próprio alcance
 * (equipe, área e substituições) e a diretoria apenas números agregados, sem dados individuais.
 */
class ReportController extends WorkspaceBaseController
{
    private const CATALOG = [
        'hours'       => ['title' => 'Horas do mês', 'description' => 'Dias trabalhados, faltas, horas esperadas, trabalhadas, saldo e horas extras por pessoa.', 'scope' => 'team'],
        'occurrences' => ['title' => 'Ocorrências de ponto', 'description' => 'Marcações fora da norma, fora do local/rede autorizados e em aparelho novo.', 'scope' => 'team'],
        'adjustments' => ['title' => 'Ajustes e justificativas', 'description' => 'Pedidos de ajuste de ponto, quem decidiu e a justificativa registrada.', 'scope' => 'team'],
        'bank'        => ['title' => 'Banco de horas', 'description' => 'Saldo atual de cada pessoa.', 'scope' => 'admin'],
        'headcount'   => ['title' => 'Quadro de pessoas', 'description' => 'Pessoas por área e admissões do mês (agregado).', 'scope' => 'aggregate'],
    ];

    public function __construct(
        \App\Domain\Workspace\Services\WorkspaceAccess $access,
        \App\Services\NotificationService $notifications,
        private readonly TimeBank $bank,
    ) {
        parent::__construct($access, $notifications);
    }

    private function allowed(string $scope, Request $request, Hotel $hotel): bool
    {
        $m = $this->membership($request, $hotel);
        if (! $m) {
            return false;
        }

        return match ($scope) {
            'admin'     => $this->access->isAdmin($m),
            'aggregate' => $this->access->canSeeDashboard($m),
            default     => $this->access->isAdmin($m) || $this->access->isManager($request->user(), $hotel),
        };
    }

    /** GET /workspaces/{slug}/reports — relatórios que a pessoa pode gerar. */
    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        if (! $this->membership($request, $hotel)) {
            return $this->forbidden('Você não faz parte desta empresa.');
        }
        $list = [];
        foreach (self::CATALOG as $key => $r) {
            if ($this->allowed($r['scope'], $request, $hotel)) {
                $list[] = ['key' => $key, 'title' => $r['title'], 'description' => $r['description'], 'monthly' => $key !== 'bank'];
            }
        }

        return $this->success('Relatórios carregados.', $list);
    }

    private static function hm(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '';
        $m    = abs($minutes);

        return $sign . intdiv($m, 60) . ':' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
    }

    private static function local($value): string
    {
        return CarbonImmutable::parse($value)->setTimezone(TimeClock::TZ)->format('d/m/Y H:i');
    }

    /** Membros no alcance de quem pede: todos (RH) ou o alcance do gestor. */
    private function scoped(Request $request, Hotel $hotel)
    {
        $q = HotelUser::with(['user', 'department', 'workSchedule'])->where('hotel_id', $hotel->id)->where('status', 'approved');
        if (! $this->access->isAdmin($this->membership($request, $hotel))) {
            $q->whereIn('user_id', $this->access->reportIds($request->user(), $hotel));
        }

        return $q->get()->sortBy(fn ($m) => mb_strtolower($m->user?->name ?? ''))->values();
    }

    /** GET /workspaces/{slug}/reports/{key}?month=YYYY-MM */
    public function show(Request $request, Hotel $hotel, string $key): JsonResponse
    {
        $def = self::CATALOG[$key] ?? null;
        if (! $def) {
            return $this->notFound('Relatório não encontrado.');
        }
        if (! $this->allowed($def['scope'], $request, $hotel)) {
            return $this->forbidden('Seu papel não dá acesso a este relatório.');
        }
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $first = $request->filled('month')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->input('month') . '-01', TimeClock::TZ)->startOfDay()
            : CarbonImmutable::now(TimeClock::TZ)->startOfMonth();
        $last = $first->endOfMonth();

        [$columns, $rows] = match ($key) {
            'hours'       => $this->hours($request, $hotel, $first),
            'occurrences' => $this->occurrences($request, $hotel, $first, $last),
            'adjustments' => $this->adjustments($request, $hotel, $first, $last),
            'bank'        => $this->bankReport($request, $hotel),
            'headcount'   => $this->headcount($hotel, $first, $last),
        };

        AuditLog::record($request, $hotel, 'report.generated', null, ['report' => $key, 'month' => $first->format('Y-m'), 'rows' => count($rows)]);

        return $this->success('Relatório gerado.', [
            'key' => $key, 'title' => $def['title'], 'month' => $key === 'bank' ? null : $first->format('Y-m'),
            'generated_at' => now()->toIso8601String(), 'columns' => $columns, 'rows' => $rows,
        ]);
    }

    private function hours(Request $request, Hotel $hotel, CarbonImmutable $first): array
    {
        $rows = $this->scoped($request, $hotel)->map(function (HotelUser $m) use ($hotel, $first) {
            $s = $this->bank->monthStats($hotel, $m->user, $m->workSchedule, $first);
            $diff = $s['worked_minutes'] - $s['expected_minutes'];

            return [$m->user?->name, $m->department?->name ?? '—', $s['days_worked'], $s['absent_days'], self::hm($s['expected_minutes']),
                self::hm($s['worked_minutes']), self::hm($diff), self::hm(max(0, $diff))];
        })->all();

        return [['Pessoa', 'Área', 'Dias trabalhados', 'Faltas', 'Esperado (h)', 'Trabalhado (h)', 'Saldo do mês (h)', 'Horas extras (h)'], $rows];
    }

    private function occurrences(Request $request, Hotel $hotel, CarbonImmutable $first, CarbonImmutable $last): array
    {
        $members = $this->scoped($request, $hotel);
        $ids     = $members->pluck('user_id')->all();
        $names   = $members->mapWithKeys(fn ($m) => [$m->user_id => $m->user?->name]);
        $rows    = [];

        foreach (AuditLog::where('hotel_id', $hotel->id)->where('action', 'time.punch_flagged')->whereIn('target_id', $ids)
            ->whereBetween('created_at', [$first->utc(), $last->utc()])->get() as $a) {
            $rows[] = [$a->created_at, $names[$a->target_id] ?? '—', 'Fora da norma: ' . implode('; ', $a->meta['issues'] ?? [])];
        }
        foreach (TimeEntry::where('hotel_id', $hotel->id)->whereIn('user_id', $ids)->whereBetween('punched_at', [$first->utc(), $last->utc()])
            ->where(fn ($q) => $q->where('outside_fence', true)->orWhere('outside_network', true)->orWhere('new_device', true))->get() as $e) {
            $why = array_filter([$e->outside_fence ? 'fora do local de trabalho' : null, $e->outside_network ? 'fora da rede autorizada' : null, $e->new_device ? 'aparelho novo' : null]);
            $rows[] = [$e->punched_at, $names[$e->user_id] ?? '—', 'Marcação ' . $e->type . ': ' . implode('; ', $why)];
        }
        usort($rows, fn ($a, $b) => $a[0] <=> $b[0]);

        return [['Data', 'Pessoa', 'Ocorrência'], array_map(fn ($r) => [self::local($r[0]), $r[1], $r[2]], $rows)];
    }

    private function adjustments(Request $request, Hotel $hotel, CarbonImmutable $first, CarbonImmutable $last): array
    {
        $ids = $this->scoped($request, $hotel)->pluck('user_id')->all();
        $rows = TimeAdjustment::with(['user', 'reviewer'])->where('hotel_id', $hotel->id)->whereIn('user_id', $ids)
            ->whereBetween('requested_at', [$first->utc(), $last->utc()])->orderBy('requested_at')->get()
            ->map(fn ($a) => [self::local($a->requested_at), $a->user?->name, $a->type, $a->reason,
                ['pending' => 'Pendente', 'approved' => 'Aprovado', 'rejected' => 'Recusado'][$a->status] ?? $a->status,
                $a->reviewer?->name ?? '—', $a->review_note ?? '—'])->all();

        return [['Data do ajuste', 'Pessoa', 'Tipo', 'Motivo do pedido', 'Situação', 'Decidido por', 'Justificativa'], $rows];
    }

    private function bankReport(Request $request, Hotel $hotel): array
    {
        $rows = $this->scoped($request, $hotel)->map(function (HotelUser $m) use ($hotel) {
            $s = $this->bank->summary($hotel, $m->user, $m->workSchedule);

            return [$m->user?->name, $m->department?->name ?? '—', self::hm($s['balance_minutes'])];
        })->all();

        return [['Pessoa', 'Área', 'Saldo atual (h)'], $rows];
    }

    private function headcount(Hotel $hotel, CarbonImmutable $first, CarbonImmutable $last): array
    {
        $members = HotelUser::with('department')->where('hotel_id', $hotel->id)->where('status', 'approved')->get();
        $rows = $members->groupBy(fn ($m) => $m->department?->name ?? 'Sem área')->map(fn ($g, $name) => [
            $name, $g->count(), $g->filter(fn ($m) => $m->hired_at && $m->hired_at->between($first, $last))->count(),
        ])->sortKeys()->values()->all();
        $rows[] = ['Total', $members->count(), array_sum(array_column($rows, 2))];

        return [['Área', 'Pessoas', 'Admissões no mês'], $rows];
    }
}
