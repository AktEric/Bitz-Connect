import api from './api'
import type { ApiResponse } from '@/types/api.types'
import type {
  PlansCatalog,
  SubscriptionState,
  SubscribeData,
  PlanTier,
} from '@/types/subscription.types'

/**
 * Subscription service — wraps all /plans and /subscriptions API calls.
 * Sprint: monetization-hotelconnect-pro
 */
const subscriptionService = {
  /** GET /plans — public plan catalog for the pricing page */
  async getPlans(): Promise<ApiResponse<PlansCatalog>> {
    const res = await api.get<ApiResponse<PlansCatalog>>('/plans')
    return res.data
  },

  /** GET /me/subscription — current user's plan + entitlement map */
  async getMySubscription(): Promise<ApiResponse<SubscriptionState>> {
    const res = await api.get<ApiResponse<SubscriptionState>>('/me/subscription')
    return res.data
  },

  /** POST /subscriptions — fake checkout: activate a plan (user or hotel) */
  async subscribe(data: SubscribeData): Promise<ApiResponse<SubscriptionState>> {
    const res = await api.post<ApiResponse<SubscriptionState>>('/subscriptions', data)
    return res.data
  },

  /** DELETE /subscriptions — cancel (reverts to Free); pass hotel_slug for a hotel */
  async cancel(hotelSlug?: string): Promise<ApiResponse<SubscriptionState>> {
    const res = await api.delete<ApiResponse<SubscriptionState>>('/subscriptions', {
      data: hotelSlug ? { hotel_slug: hotelSlug } : {},
    })
    return res.data
  },

  /** GET /hotels/:slug/subscription — a hotel's plan + entitlements (admin only) */
  async getHotelSubscription(slug: string): Promise<ApiResponse<SubscriptionState>> {
    const res = await api.get<ApiResponse<SubscriptionState>>(`/hotels/${slug}/subscription`)
    return res.data
  },

  /** PATCH /admin/subscriptions — platform-admin plan override */
  async adminSetPlan(
    subjectType: 'user' | 'hotel',
    subjectId: string,
    plan: PlanTier,
  ): Promise<ApiResponse<SubscriptionState>> {
    const res = await api.patch<ApiResponse<SubscriptionState>>('/admin/subscriptions', {
      subject_type: subjectType,
      subject_id: subjectId,
      plan,
    })
    return res.data
  },
}

export default subscriptionService
