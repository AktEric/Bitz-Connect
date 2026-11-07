<?php

namespace App\Console\Commands;

use App\Models\TimeEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * LGPD: fotos e coordenadas de marcações antigas deixam de ser guardadas (minimização/retenção).
 * A marcação em si (hora, tipo, NSR, hash) permanece, e o hash encadeado não depende desses campos.
 */
class PurgePunchSensitiveData extends Command
{
    protected $signature = 'ponto:purge-sensitive {--months= : Meses de retenção (padrão: PUNCH_SENSITIVE_RETENTION_MONTHS ou 12)}';

    protected $description = 'Apaga fotos e localização de marcações de ponto mais antigas que o prazo de retenção.';

    public function handle(): int
    {
        $months = max(1, (int) ($this->option('months') ?: env('PUNCH_SENSITIVE_RETENTION_MONTHS', 12)));
        $limit  = now()->subMonths($months);
        $count  = 0;

        TimeEntry::where('punched_at', '<', $limit)
            ->where(fn ($q) => $q->whereNotNull('photo_path')->orWhereNotNull('lat')->orWhereNotNull('lng'))
            ->chunkById(200, function ($entries) use (&$count) {
                foreach ($entries as $e) {
                    if ($e->photo_path) {
                        Storage::disk('local')->delete($e->photo_path);
                    }
                    $e->forceFill(['photo_path' => null, 'lat' => null, 'lng' => null, 'accuracy_m' => null])->save();
                    $count++;
                }
            });

        $this->info("{$count} marcações tiveram foto/localização apagadas (anteriores a {$months} meses).");

        return self::SUCCESS;
    }
}
