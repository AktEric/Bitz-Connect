<?php

namespace App\Domain\Workspace\Services;

use App\Models\Hotel;
use App\Models\HotelUser;
use App\Models\User;

/**
 * Traduz papel de acesso + posição na hierarquia em *capabilities* que o frontend usa para decidir o que mostrar.
 *
 * É só apresentação: a barreira real continua em cada controller, que usa as mesmas regras de {@see WorkspaceAccess}.
 * Ao criar uma tela/ação restrita, declare a capability aqui e use `useCan()` no frontend.
 */
class WorkspacePermissions
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    /**
     * @return string[] capabilities da pessoa nesta empresa (vazio para quem não é membro aprovado)
     */
    public function for(User $user, Hotel $hotel, ?HotelUser $membership): array
    {
        if (! $membership) {
            return [];
        }

        $admin   = $this->access->isAdmin($membership);
        $owner   = $this->access->isOwner($membership);
        $lead    = $admin || $this->access->isManager($user, $hotel);
        $summary = $this->access->canSeeDashboard($membership);

        $caps = [
            'feed.view'  => true,
            'jobs.apply' => true,
            'marketplace.view' => true,
            'marketplace.sell' => true,
            'marketplace.moderate' => $admin,
            'people.view' => true,
            // gestão de pessoas e estrutura
            'people.manage'    => $admin,
            'career.manage'    => $admin,
            'areas.manage'     => $admin,
            'invites.manage'   => $admin,
            'onboarding.view'  => $admin,
            'roles.view'       => $admin,
            'roles.manage'     => $owner,
            'security.view'    => $admin,
            'security.manage'  => $owner,
            'audit.view'       => $admin,
            // conteúdo
            'feed.post'        => $lead,
            'feed.moderate'    => $admin,
            'jobs.manage'      => $lead,
            'calendar.manage'  => $lead,
            'calendar.holiday' => $admin,
            'climate.manage'   => $admin,
            'climate.results'  => $summary,
            // acompanhamento
            'requests.review'  => $lead,
            'goals.manage'     => $lead,
            'development.manage' => $lead,
            'time.team'        => $lead,
            'time.settings'    => $admin,
            'time.closing'     => $admin,
            'schedules.manage' => $admin,
            'payslips.manage'  => $this->access->canHandlePayslips($membership),
            'dashboard.view'   => $summary,
            'reports.view'     => $lead || $summary,
        ];

        return array_keys(array_filter($caps));
    }
}
