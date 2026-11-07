<?php

namespace App\Domain\Workspace\Services;

use App\Models\Hotel;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Grava marcações de ponto com número sequencial (NSR) por empresa e um hash encadeado:
 * cada registro inclui o hash do anterior, então alterar ou apagar um registro no banco quebra a cadeia
 * e a verificação de integridade acusa. É evidência de adulteração, não uma certificação legal do ponto.
 */
class TimeEntryRecorder
{
    public static function hashFor(int $hotelId, int $nsr, int $userId, string $type, string $punchedAtUtc, string $source, string $prev): string
    {
        return hash('sha256', implode('|', [$hotelId, $nsr, $userId, $type, $punchedAtUtc, $source, $prev]));
    }

    private static function iso($value): string
    {
        return CarbonImmutable::parse($value)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /** @param array<string, mixed> $attrs atributos de TimeEntry (sem nsr/hash) */
    public function record(array $attrs): TimeEntry
    {
        for ($try = 1; ; $try++) {
            try {
                return DB::transaction(function () use ($attrs) {
                    $last = TimeEntry::where('hotel_id', $attrs['hotel_id'])->whereNotNull('nsr')->orderByDesc('nsr')->lockForUpdate()->first();
                    $nsr  = ($last?->nsr ?? 0) + 1;
                    $hash = self::hashFor(
                        (int) $attrs['hotel_id'], $nsr, (int) $attrs['user_id'], (string) $attrs['type'],
                        self::iso($attrs['punched_at']), (string) ($attrs['source'] ?? 'web'), $last?->hash ?? '',
                    );

                    return TimeEntry::create($attrs + ['nsr' => $nsr, 'hash' => $hash]);
                });
            } catch (QueryException $e) {
                // duas marcações simultâneas disputaram o mesmo NSR: tenta de novo com o próximo
                if ($try >= 4) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Confere a cadeia inteira da empresa.
     *
     * @return array{ok: bool, checked: int, first_broken_nsr: ?int, reason: ?string}
     */
    public function verify(Hotel $hotel): array
    {
        $prev = '';
        $expected = 0;
        $checked = 0;

        foreach (TimeEntry::where('hotel_id', $hotel->id)->whereNotNull('nsr')->orderBy('nsr')->cursor() as $e) {
            $expected++;
            if ($e->nsr !== $expected) {
                return ['ok' => false, 'checked' => $checked, 'first_broken_nsr' => $expected, 'reason' => 'Registro ausente na sequência (alguém apagou uma marcação).'];
            }
            $hash = self::hashFor($e->hotel_id, $e->nsr, $e->user_id, $e->type, self::iso($e->punched_at), (string) $e->source, $prev);
            if (! hash_equals($hash, (string) $e->hash)) {
                return ['ok' => false, 'checked' => $checked, 'first_broken_nsr' => $e->nsr, 'reason' => 'O conteúdo do registro foi alterado depois de gravado.'];
            }
            $prev = $e->hash;
            $checked++;
        }

        return ['ok' => true, 'checked' => $checked, 'first_broken_nsr' => null, 'reason' => null];
    }
}
