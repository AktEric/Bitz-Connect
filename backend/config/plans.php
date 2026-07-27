<?php

use App\Enums\Feature;
use App\Enums\SubscriptionPlan;

/*
|--------------------------------------------------------------------------
| Subscription Plan Catalog
|--------------------------------------------------------------------------
| Single source of truth for the plans offered by HotelConnect. Adding a new
| plan or benefit is done here — the EntitlementService, pricing page and API
| all read from this catalog, so no plan-specific logic lives elsewhere.
|
| price_cents .... amount in BRL cents (0 = free). Billing gateway (Stripe /
|                  Mercado Pago) will map to these amounts later.
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
            'name'        => 'HotelConnect PRO — Consultor',
            'tagline'     => 'Para consultores independentes que criam conteúdo.',
            'price_cents' => 4990,
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
            'name'        => 'HotelConnect PRO — Empresa',
            'tagline'     => 'Para hotéis e empresas que recrutam e se destacam.',
            'price_cents' => 19990,
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
