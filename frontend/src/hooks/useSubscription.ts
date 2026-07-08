import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import subscriptionService from '@/services/subscription.service'
import { profileKeys } from '@/hooks/useProfile'
import { useToast } from '@/contexts/ToastContext'
import { extractApiError } from '@/utils/api-error'
import type { Feature, SubscribeData, PlanTier } from '@/types/subscription.types'

/**
 * Subscription & entitlement hooks.
 *
 * The entitlement layer (§3) lives here: UI components ask `useCan(feature)`
 * or `useHotelCan(slug, feature)` and never inspect the plan string directly.
 * This keeps every permission decision flowing through one place.
 *
 * Sprint: monetization-hotelconnect-pro
 */

export const subscriptionKeys = {
  plans: ['plans'] as const,
  mine: ['subscription', 'me'] as const,
  hotel: (slug: string) => ['subscription', 'hotel', slug] as const,
}

// ── Queries ──────────────────────────────────────────────────────────────

/** Public plan catalog for the pricing page. */
export function usePlans() {
  return useQuery({
    queryKey: subscriptionKeys.plans,
    queryFn: () => subscriptionService.getPlans().then((r) => r.data),
    staleTime: 1000 * 60 * 10,
  })
}

/** Current user's plan + entitlement map. */
export function useMySubscription() {
  return useQuery({
    queryKey: subscriptionKeys.mine,
    queryFn: () => subscriptionService.getMySubscription().then((r) => r.data),
  })
}

/** A hotel's plan + entitlements (admin only). */
export function useHotelSubscription(slug: string) {
  return useQuery({
    queryKey: subscriptionKeys.hotel(slug),
    queryFn: () => subscriptionService.getHotelSubscription(slug).then((r) => r.data),
    enabled: !!slug,
  })
}

// ── Centralized entitlement gate ───────────────────────────────────────────

/**
 * Whether the current user is entitled to a feature. Defaults to `false` while
 * loading or on error, so gated UI safely shows the upgrade path (§13).
 */
export function useCan(feature: Feature): boolean {
  const { data } = useMySubscription()
  return data?.entitlements?.[feature] ?? false
}

/** Whether a hotel is entitled to a feature (company-scoped gate). */
export function useHotelCan(slug: string, feature: Feature): boolean {
  const { data } = useHotelSubscription(slug)
  return data?.entitlements?.[feature] ?? false
}

// ── Mutations ──────────────────────────────────────────────────────────────

/** Fake checkout — activate a plan for the user or an administered hotel. */
export function useSubscribe() {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: (data: SubscribeData) => subscriptionService.subscribe(data),
    onSuccess: (_res, vars) => {
      success('Assinatura ativada com sucesso.')
      qc.invalidateQueries({ queryKey: subscriptionKeys.mine })
      qc.invalidateQueries({ queryKey: profileKeys.me })
      if (vars.hotel_slug) {
        qc.invalidateQueries({ queryKey: subscriptionKeys.hotel(vars.hotel_slug) })
      }
    },
    onError: (err) => error(extractApiError(err)),
  })
}

/** Cancel the active subscription (user, or a hotel via slug). */
export function useCancelSubscription() {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: (hotelSlug?: string) => subscriptionService.cancel(hotelSlug),
    onSuccess: (_res, hotelSlug) => {
      success('Assinatura cancelada.')
      qc.invalidateQueries({ queryKey: subscriptionKeys.mine })
      qc.invalidateQueries({ queryKey: profileKeys.me })
      if (hotelSlug) {
        qc.invalidateQueries({ queryKey: subscriptionKeys.hotel(hotelSlug) })
      }
    },
    onError: (err) => error(extractApiError(err)),
  })
}

/** Platform-admin plan override. */
export function useAdminSetPlan() {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: (vars: { subjectType: 'user' | 'hotel'; subjectId: string; plan: PlanTier }) =>
      subscriptionService.adminSetPlan(vars.subjectType, vars.subjectId, vars.plan),
    onSuccess: () => {
      success('Plano atualizado.')
      qc.invalidateQueries({ queryKey: subscriptionKeys.mine })
    },
    onError: (err) => error(extractApiError(err)),
  })
}
