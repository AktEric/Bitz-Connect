<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\TimeAdjustment;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** LGPD: aceite do tratamento de foto/localização e exportação dos dados da própria pessoa. */
class PrivacyController extends Controller
{
    use ApiResponseTrait;

    private function member(Request $request, Hotel $hotel): ?HotelUser
    {
        return HotelUser::where('hotel_id', $hotel->id)->where('user_id', $request->user()->id)->where('status', 'approved')->first();
    }

    /** GET /workspaces/{slug}/me/privacy */
    public function status(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->member($request, $hotel);
        if (! $m) {
            return $this->notFound('Você não faz parte desta empresa.');
        }

        return $this->success('Privacidade carregada.', ['punch_consent_at' => $m->punch_consent_at?->toIso8601String()]);
    }

    /** POST /workspaces/{slug}/me/privacy/consent */
    public function consent(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->member($request, $hotel);
        if (! $m) {
            return $this->notFound('Você não faz parte desta empresa.');
        }
        $m->forceFill(['punch_consent_at' => now()])->save();
        AuditLog::record($request, $hotel, 'privacy.consent_given', null, []);

        return $this->success('Aceite registrado.', ['punch_consent_at' => $m->punch_consent_at->toIso8601String()]);
    }

    /** GET /workspaces/{slug}/me/export — cópia dos dados pessoais da pessoa nesta empresa (JSON). */
    public function export(Request $request, Hotel $hotel): JsonResponse
    {
        $m = $this->member($request, $hotel);
        if (! $m) {
            return $this->notFound('Você não faz parte desta empresa.');
        }
        $u = $request->user();

        $entries = TimeEntry::where('hotel_id', $hotel->id)->where('user_id', $u->id)->orderBy('nsr')->get()->map(fn (TimeEntry $e) => [
            'nsr' => $e->nsr, 'tipo' => $e->type, 'data_hora_utc' => $e->punched_at->toIso8601String(), 'origem' => $e->source,
            'ip' => $e->ip, 'latitude' => $e->lat, 'longitude' => $e->lng, 'precisao_m' => $e->accuracy_m,
            'dispositivo' => $e->device_name, 'rede' => $e->network_name, 'tem_foto' => $e->photo_path !== null, 'observacao' => $e->note,
        ])->all();

        $adjustments = TimeAdjustment::where('hotel_id', $hotel->id)->where('user_id', $u->id)->orderBy('id')->get()->map(fn (TimeAdjustment $a) => [
            'tipo' => $a->type, 'solicitado_para' => $a->requested_at?->toIso8601String(), 'motivo' => $a->reason, 'situacao' => $a->status,
        ])->all();

        AuditLog::record($request, $hotel, 'privacy.data_exported', null, []);

        return $this->success('Exportação pronta.', [
            'gerado_em'     => now()->toIso8601String(),
            'empresa'       => $hotel->name,
            'pessoa'        => ['nome' => $u->name, 'email' => $u->email],
            'vinculo'       => ['cargo' => $m->job_title, 'admissao' => $m->hired_at, 'aceite_foto_localizacao' => $m->punch_consent_at?->toIso8601String()],
            'marcacoes'     => $entries,
            'ajustes_ponto' => $adjustments,
        ]);
    }
}
