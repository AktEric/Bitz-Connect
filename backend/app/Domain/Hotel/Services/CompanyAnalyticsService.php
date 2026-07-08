<?php

namespace App\Domain\Hotel\Services;

use App\Models\Hotel;
use App\Models\HotelFollower;
use App\Models\JobApplication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agrega as métricas do dashboard empresarial (§7/§8).
 *
 * Retorna um mapa de indicadores computados a partir dos dados reais do hotel,
 * além de séries temporais (últimos 30 dias) e um detalhamento por vaga, para
 * alimentar os gráficos de tendência e o relatório exportável do dashboard PRO.
 * A estrutura é aberta: novos indicadores entram aqui sem mudar o contrato do
 * endpoint (o frontend renderiza os cards a partir da lista `metrics`).
 * Sprint: monetization-hotelconnect-pro (Empresa PRO)
 */
class CompanyAnalyticsService
{
    private const TREND_DAYS = 30;

    public function forHotel(Hotel $hotel): array
    {
        $activeJobs  = $hotel->jobs()->where('status', 'active')->count();
        $totalJobs   = $hotel->jobs()->count();
        $posts       = $hotel->posts()->count();
        $followers   = (int) $hotel->followers_count;
        $views       = (int) $hotel->profile_views;

        $jobIds = $hotel->jobs()->pluck('id');

        // Candidaturas a todas as vagas do hotel.
        $applications = JobApplication::whereIn('job_id', $jobIds)->count();

        // Taxa de conversão: visitantes do perfil que viraram candidatos.
        $conversion = $views > 0 ? round(($applications / $views) * 100, 1) : 0.0;

        return [
            'metrics' => [
                ['key' => 'profile_views',    'label' => 'Visualizações do perfil', 'value' => $views,        'format' => 'number'],
                ['key' => 'followers',        'label' => 'Seguidores',              'value' => $followers,    'format' => 'number'],
                ['key' => 'active_jobs',      'label' => 'Vagas ativas',            'value' => $activeJobs,   'format' => 'number'],
                ['key' => 'total_jobs',       'label' => 'Total de vagas',          'value' => $totalJobs,    'format' => 'number'],
                ['key' => 'applications',     'label' => 'Candidaturas',            'value' => $applications, 'format' => 'number'],
                ['key' => 'posts',            'label' => 'Publicações',             'value' => $posts,        'format' => 'number'],
                ['key' => 'conversion_rate',  'label' => 'Taxa de conversão',       'value' => $conversion,   'format' => 'percent'],
            ],
            'applications_trend' => $this->dailyCount(
                JobApplication::whereIn('job_id', $jobIds),
            ),
            'followers_trend' => $this->dailyCount(
                HotelFollower::where('hotel_id', $hotel->id),
            ),
            'jobs_breakdown' => $hotel->jobs()
                ->withCount('applications')
                ->orderByDesc('applications_count')
                ->get(['id', 'title', 'status'])
                ->map(fn ($job) => [
                    'title'        => $job->title,
                    'status'       => $job->status,
                    'applications' => $job->applications_count,
                ])
                ->values(),
            'applications_by_status' => JobApplication::whereIn('job_id', $jobIds)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];
    }

    /**
     * Conta registros por dia nos últimos 30 dias, preenchendo dias sem
     * atividade com zero para que o gráfico de tendência não tenha buracos.
     */
    private function dailyCount($query): array
    {
        $since = Carbon::now()->subDays(self::TREND_DAYS - 1)->startOfDay();

        $rows = (clone $query)
            ->where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($i = self::TREND_DAYS - 1; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i)->toDateString();
            $series[] = ['date' => $day, 'count' => (int) ($rows[$day] ?? 0)];
        }

        return $series;
    }
}
