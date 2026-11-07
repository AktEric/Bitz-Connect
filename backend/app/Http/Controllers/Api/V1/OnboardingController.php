<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Services\PunchRules;
use App\Models\CalendarEvent;
use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\WorkSchedule;
use App\Models\WorkspacePost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Assistente de implantação: lista os primeiros passos para a empresa começar a usar o Workspace.
 * O estado de cada passo é deduzido dos dados reais (nada para marcar à mão).
 */
class OnboardingController extends WorkspaceBaseController
{
    private function denied(Request $request, Hotel $hotel): ?JsonResponse
    {
        return $this->access->isAdmin($this->membership($request, $hotel))
            ? null
            : $this->forbidden('Somente o dono ou o RH acompanham a implantação.');
    }

    /** GET /workspaces/{slug}/onboarding */
    public function show(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }

        $people = HotelUser::where('hotel_id', $hotel->id)->where('status', 'approved');
        $rules  = app(PunchRules::class)->settings($hotel);
        $owner  = $request->user();

        $steps = [
            ['key' => 'structure', 'title' => 'Monte os setores da empresa', 'hint' => 'Aplique o modelo do seu segmento e ajuste os setores.',
             'to' => 'configuracoes', 'cta' => 'Abrir estrutura', 'done' => $hotel->departments()->exists()],
            ['key' => 'people', 'title' => 'Convide as pessoas', 'hint' => 'Gere convites por e-mail ou link. Cada pessoa entra com a própria conta.',
             'to' => 'equipe', 'cta' => 'Convidar', 'done' => (clone $people)->count() >= 2],
            ['key' => 'managers', 'title' => 'Defina gestores e chefes de área', 'hint' => 'Quem aprova férias, ajustes de ponto e acompanha cada equipe.',
             'to' => 'configuracoes', 'cta' => 'Definir chefias', 'done' => (clone $people)->whereNotNull('manager_id')->exists() || $hotel->departments()->whereNotNull('head_id')->exists()],
            ['key' => 'schedules', 'title' => 'Cadastre as jornadas de trabalho', 'hint' => '8h seg–sex, 6x1, 12x36… cada pessoa pode ter a sua.',
             'to' => 'configuracoes', 'cta' => 'Criar jornadas', 'done' => WorkSchedule::where('hotel_id', $hotel->id)->exists()],
            ['key' => 'punch', 'title' => 'Escolha as regras do ponto', 'hint' => 'Localização, foto, dispositivos e redes autorizadas.',
             'to' => 'configuracoes', 'cta' => 'Configurar ponto',
             'done' => $rules['geo'] !== 'off' || $rules['photo'] !== 'off' || $rules['devices'] !== 'off' || $rules['networks'] !== [] || $rules['fence'] !== null],
            ['key' => 'security', 'title' => 'Proteja os perfis privilegiados', 'hint' => 'Ative a verificação em duas etapas para você e exija-a de dono, RH e financeiro.',
             'to' => 'seguranca', 'cta' => 'Abrir segurança', 'done' => (bool) ($hotel->security_settings['require_2fa'] ?? false) || $owner->hasTwoFactor()],
            ['key' => 'calendar', 'title' => 'Cadastre feriados e eventos da empresa', 'hint' => 'Feriados municipais e folgas coletivas entram no cálculo de horas.',
             'to' => 'calendario', 'cta' => 'Abrir calendário', 'done' => CalendarEvent::where('hotel_id', $hotel->id)->exists()],
            ['key' => 'post', 'title' => 'Publique o primeiro comunicado', 'hint' => 'Dê as boas-vindas à equipe no mural, com fotos se quiser.',
             'to' => 'mural', 'cta' => 'Ir ao mural', 'done' => WorkspacePost::where('hotel_id', $hotel->id)->exists()],
        ];

        $done = count(array_filter($steps, fn ($s) => $s['done']));

        return $this->success('Implantação carregada.', [
            'steps'     => $steps,
            'done'      => $done,
            'total'     => count($steps),
            'percent'   => (int) round($done / count($steps) * 100),
            'dismissed' => $hotel->onboarding_dismissed_at !== null,
        ]);
    }

    /** POST /workspaces/{slug}/onboarding/dismiss — esconde o convite da Home (os passos continuam acessíveis no menu). */
    public function dismiss(Request $request, Hotel $hotel): JsonResponse
    {
        if ($denied = $this->denied($request, $hotel)) {
            return $denied;
        }
        $hotel->forceFill(['onboarding_dismissed_at' => now()])->save();

        return $this->success('Convite escondido.');
    }
}
