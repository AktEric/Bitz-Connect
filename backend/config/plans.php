<?php

use App\Enums\Feature;
use App\Enums\SubscriptionPlan;

/*
|--------------------------------------------------------------------------
| Subscription Plan Catalog
|--------------------------------------------------------------------------
| Single source of truth for the plans offered by ConnectHotel. Adding a new
| plan or benefit is done here — the EntitlementService, pricing page and API
| all read from this catalog, so no plan-specific logic lives elsewhere.
|
| price_cents .... amount in BRL cents (0 = free). Billing gateway (Stripe /
|                  Mercado Pago) will map to these amounts later.
| price_note ..... extra pricing line shown under the amount (e.g. the take
|                  rate charged on transactions besides the subscription).
| audience ....... 'user' | 'hotel' — which kind of subject may hold the plan.
| features ....... Feature enum values granted by the plan (the entitlements).
| benefits ....... human-readable bullet list for the pricing page. Items may
|                  carry a "prepared" flag to render a "em breve" hint while the
|                  underlying feature is structurally ready but not yet live.
*/

return [

    'currency' => 'BRL',

    'plans' => [

        SubscriptionPlan::Free->value => [
            'name'        => 'Gratuito',
            'tagline'     => 'Tudo o que você precisa para começar.',
            'price_cents' => 0,
            'interval'    => null,
            'audience'    => 'user',
            'featured'    => false,
            'features'    => [],
            'benefits'    => [
                ['label' => 'Perfil profissional'],
                ['label' => 'Feed e comunidade'],
                ['label' => 'Conexões'],
                ['label' => 'Mensagens'],
                ['label' => 'Candidaturas a vagas'],
                ['label' => 'Publicações, curtidas e comentários'],
            ],
        ],

        SubscriptionPlan::ProConsultor->value => [
            'name'        => 'ConnectHotel PRO — Consultor',
            'tagline'     => 'Para consultores independentes que criam conteúdo.',
            'price_cents' => 4990,
            'price_note'  => '+ 2,9% por consultoria ou venda transacionada pela plataforma',
            'interval'    => 'month',
            'audience'    => 'user',
            'featured'    => true,
            'features'    => [
                Feature::ProBadge->value,
                Feature::PublishVideos->value,
                Feature::CreatorPage->value,
                Feature::ContentAnalytics->value,
                Feature::ViewStats->value,
                Feature::SearchHighlight->value,
                Feature::CreateCourses->value,
                Feature::SellMentorships->value,
                Feature::RecommendationBoost->value,
                Feature::PublishStories->value,
            ],
            'benefits' => [
                ['label' => 'Selo PRO no perfil'],
                ['label' => 'Publicação de vídeos'],
                ['label' => 'Página exclusiva de criador'],
                ['label' => 'Analytics dos conteúdos'],
                ['label' => 'Estatísticas de visualização'],
                ['label' => 'Destaque nos resultados de busca'],
                ['label' => 'Criação de cursos', 'prepared' => true],
                ['label' => 'Venda de mentorias', 'prepared' => true],
                ['label' => 'Maior alcance nas recomendações', 'prepared' => true],
                ['label' => 'Stories em destaque'],
            ],
        ],

        SubscriptionPlan::ProEmpresa->value => [
            'name'        => 'ConnectHotel PRO — Empresa',
            'tagline'     => 'Para empresas de qualquer segmento que recrutam e se destacam.',
            'price_cents' => 9990,
            'interval'    => 'month',
            'audience'    => 'hotel',
            'featured'    => true,
            'features'    => [
                Feature::ProBadge->value,
                Feature::PremiumCompanyProfile->value,
                Feature::FeaturedCompanyPage->value,
                Feature::CompanyDashboard->value,
                Feature::CompanyAnalytics->value,
                Feature::JobVisibilityBoost->value,
                Feature::SearchHighlight->value,
                Feature::AdvancedRecruitment->value,
                Feature::CandidateSearch->value,
                Feature::ProfessionalFilters->value,
                Feature::UnlimitedInvites->value,
                Feature::PublishStories->value,
            ],
            'benefits' => [
                ['label' => 'Acesso aos principais candidatos, de acordo com as suas vagas ou com os perfis que já atuam na área'],
                ['label' => 'Selo Empresa PRO'],
                ['label' => 'Perfil empresarial premium'],
                ['label' => 'Página empresarial destacada'],
                ['label' => 'Dashboard empresarial'],
                ['label' => 'Analytics completos'],
                ['label' => 'Maior visibilidade nas vagas'],
                ['label' => 'Destaque nas pesquisas'],
                ['label' => 'Ferramentas avançadas de recrutamento'],
                ['label' => 'Busca avançada de candidatos'],
                ['label' => 'Filtros profissionais'],
                ['label' => 'Convites ilimitados', 'prepared' => true],
                ['label' => 'Stories em destaque'],
            ],
        ],
    ],
];
