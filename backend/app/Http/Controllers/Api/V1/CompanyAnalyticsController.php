<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Hotel\Services\CompanyAnalyticsService;
use App\Domain\Subscription\Services\EntitlementService;
use App\Enums\Feature;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dashboard/analytics da empresa (§7/§8). Exclusivo de admins de hotéis
 * no plano PRO Empresa (entitlement company_analytics).
 * Sprint: monetization-hotelconnect-pro (Empresa PRO)
 */
class CompanyAnalyticsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CompanyAnalyticsService $analytics,
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * GET /hotels/{hotel:slug}/analytics
     */
    public function show(Request $request, Hotel $hotel): JsonResponse
    {
        $denied = $this->denyUnlessCanAccess($request, $hotel);
        if ($denied) {
            return $denied;
        }

        return $this->success('Analytics carregado com sucesso.', $this->analytics->forHotel($hotel));
    }

    /**
     * GET /hotels/{hotel:slug}/analytics/export
     * Relatório em CSV com as métricas, o detalhamento por vaga e as
     * candidaturas por status — para download/arquivo do time de RH.
     */
    public function export(Request $request, Hotel $hotel): JsonResponse|StreamedResponse
    {
        $denied = $this->denyUnlessCanAccess($request, $hotel);
        if ($denied) {
            return $denied;
        }

        $data = $this->analytics->forHotel($hotel);

        $filename = 'relatorio-' . $hotel->slug . '-' . now()->format('Y-m-d') . '.csv';

        return new StreamedResponse(function () use ($data, $hotel) {
            $out = fopen('php://output', 'w');
            // BOM para o Excel reconhecer UTF-8 corretamente.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Relatório ConnectHotel — ' . $hotel->name]);
            fputcsv($out, ['Gerado em', now()->format('d/m/Y H:i')]);
            fputcsv($out, []);

            fputcsv($out, ['Métrica', 'Valor']);
            foreach ($data['metrics'] as $metric) {
                fputcsv($out, [$metric['label'], $metric['value']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Vaga', 'Status', 'Candidaturas']);
            foreach ($data['jobs_breakdown'] as $job) {
                fputcsv($out, [$job['title'], $job['status'], $job['applications']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Candidaturas por status', 'Total']);
            foreach ($data['applications_by_status'] as $status => $total) {
                fputcsv($out, [$status, $total]);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function denyUnlessCanAccess(Request $request, Hotel $hotel): ?JsonResponse
    {
        // Precisa ser admin do hotel.
        $isAdmin = $hotel->hotelUsers()
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')->where('status', 'approved')->exists();

        if (! $isAdmin) {
            return $this->forbidden('Você não administra este hotel.');
        }

        // Precisa do plano PRO Empresa.
        if (! $this->entitlements->allows($hotel, Feature::CompanyAnalytics)) {
            return $this->forbidden('O dashboard de analytics é exclusivo do ConnectHotel PRO - Empresa.');
        }

        return null;
    }
}
