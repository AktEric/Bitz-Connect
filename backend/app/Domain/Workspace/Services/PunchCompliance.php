<?php

namespace App\Domain\Workspace\Services;

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Confere a marcação contra as normas da CLT (intervalo e jornada) e avisa o gestor quando algo foge delas.
 * Os avisos não impedem a marcação: o ponto é registrado como aconteceu e o gestor/RH trata a exceção.
 */
class PunchCompliance
{
    public const BREAK_MIN = 60;        // CLT art. 71: intervalo mínimo de 1h para jornadas acima de 6h
    public const BREAK_MAX = 120;       // e máximo de 2h (salvo acordo)
    public const TOLERANCE = 10;        // CLT art. 58 §1º: até 10 min diários de tolerância
    public const OVERTIME_LIMIT = 120;  // CLT art. 59: no máximo 2h extras por dia

    public function __construct(
        private readonly TimeClock $clock,
        private readonly WorkspaceAccess $access,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  Collection  $entries  marcações do dia, já com a que acabou de ser registrada
     * @return list<string> motivos de alerta (vazio = dentro das normas)
     */
    public function evaluate(string $type, Collection $entries, CarbonImmutable $now, int $dailyMinutes): array
    {
        $issues = [];

        if ($type === 'break_end') {
            $start = $entries->where('type', 'break_start')->last();
            if ($start) {
                $mins = (int) CarbonImmutable::instance($start->punched_at)->diffInMinutes($now, true);
                if ($mins < self::BREAK_MIN - self::TOLERANCE) {
                    $issues[] = "intervalo de {$mins} min, abaixo do mínimo de " . self::BREAK_MIN . ' min';
                } elseif ($mins > self::BREAK_MAX) {
                    $issues[] = "intervalo de {$mins} min, acima do máximo de " . self::BREAK_MAX . ' min';
                }
            }
        }

        if ($type === 'in' && $dailyMinutes === 0 && $entries->where('type', 'in')->count() === 1) {
            $issues[] = 'marcação em dia sem expediente (folga, feriado ou férias)';
        }

        if ($type === 'out' && $dailyMinutes > 0) {
            $issues = array_merge($issues, $this->overtimeIssues($this->clock->workedMinutes($entries, $now), $dailyMinutes));
        }

        return $issues;
    }

    /** @return list<string> */
    public function overtimeIssues(int $worked, int $dailyMinutes): array
    {
        $extra = $worked - $dailyMinutes;
        if ($extra <= self::TOLERANCE) {
            return [];
        }
        $label = intdiv($extra, 60) . 'h' . str_pad((string) ($extra % 60), 2, '0', STR_PAD_LEFT);
        $issues = ["hora extra de {$label} além do expediente padrão"];
        if ($extra > self::OVERTIME_LIMIT) {
            $issues[] = 'acima do limite legal de 2h extras por dia';
        }

        return $issues;
    }

    /** Avisa o gestor (ou o RH, se a pessoa não tem gestor) sobre uma ou mais irregularidades. */
    public function notify(Hotel $hotel, User $user, array $issues, string $link): void
    {
        if (! $issues) {
            return;
        }
        $member = HotelUser::where('hotel_id', $hotel->id)->where('user_id', $user->id)->where('status', 'approved')->first();
        if (! $member) {
            return;
        }
        $text = ucfirst(implode('; ', $issues)) . '.';
        foreach ($this->access->reviewersFor($member, $hotel) as $reviewer) {
            $this->notifications->workspaceNotice($reviewer, $user, 'workspace_punch_alert', "Ponto fora da norma — {$user->name}", $text, $link);
        }
    }
}
