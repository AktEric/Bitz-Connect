/**
 * Subscription & entitlement types (Sprint: monetization-hotelconnect-pro).
 * Mirrors the backend App\Enums\{SubscriptionPlan,Feature} vocabulary.
 */

export type PlanTier = 'free' | 'pro_consultor' | 'pro_empresa'

export type SubscriptionStatus = 'pending' | 'active' | 'cancelled' | 'expired'

export type PlanAudience = 'user' | 'hotel'

/**
 * Feature keys — the centralized entitlement vocabulary. UI gating always
 * references these keys via useCan(), never the plan string.
 */
export type Feature =
  | 'publish_videos'
  | 'create_courses'
  | 'sell_mentorships'
  | 'creator_page'
  | 'content_analytics'
  | 'view_stats'
  | 'recommendation_boost'
  | 'premium_company_profile'
  | 'company_dashboard'
  | 'company_analytics'
  | 'job_visibility_boost'
  | 'advanced_recruitment'
  | 'candidate_search'
  | 'professional_filters'
  | 'unlimited_invites'
  | 'featured_company_page'
  | 'pro_badge'
  | 'search_highlight'

export type Entitlements = Record<Feature, boolean>

export interface PlanBenefit {
  label: string
  /** Structurally ready but not yet live — rendered with an "em breve" hint. */
  prepared?: boolean
}

export interface Plan {
  id: PlanTier
  name: string
  tagline: string | null
  price_cents: number
  interval: 'month' | 'year' | null
  audience: PlanAudience
  featured: boolean
  features: Feature[]
  benefits: PlanBenefit[]
}

export interface Subscription {
  uuid: string
  plan: PlanTier
  status: SubscriptionStatus
  started_at: string | null
  expires_at: string | null
  cancelled_at: string | null
  provider: string | null
}

/** Standard payload returned by subscription endpoints. */
export interface SubscriptionState {
  plan: PlanTier
  subscription: Subscription | null
  entitlements: Entitlements
}

export interface PlansCatalog {
  currency: string
  plans: Plan[]
}

export interface SubscribeData {
  plan: PlanTier
  hotel_slug?: string
}
