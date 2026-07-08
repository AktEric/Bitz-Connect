import { useQuery } from '@tanstack/react-query'
import analyticsService from '@/services/analytics.service'

/**
 * Hook do dashboard de analytics de empresa.
 * Sprint: monetization-hotelconnect-pro (Empresa PRO)
 */
export function useCompanyAnalytics(slug: string, enabled = true) {
  return useQuery({
    queryKey: ['analytics', 'company', slug],
    queryFn: () => analyticsService.getCompanyAnalytics(slug).then((r) => r.data),
    enabled: enabled && !!slug,
    retry: false, // 403 (não-PRO) não deve ficar re-tentando
  })
}
