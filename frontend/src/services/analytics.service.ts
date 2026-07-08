import api from './api'
import type { ApiResponse } from '@/types/api.types'
import type { CompanyAnalytics } from '@/types/analytics.types'

/**
 * Serviço de analytics de empresa.
 * Sprint: monetization-hotelconnect-pro (Empresa PRO)
 */
const analyticsService = {
  /** GET /hotels/:slug/analytics — métricas do dashboard (PRO Empresa) */
  async getCompanyAnalytics(slug: string): Promise<ApiResponse<CompanyAnalytics>> {
    const res = await api.get<ApiResponse<CompanyAnalytics>>(`/hotels/${slug}/analytics`)
    return res.data
  },

  /** GET /hotels/:slug/analytics/export — baixa o relatório em CSV (PRO Empresa) */
  async exportReport(slug: string): Promise<Blob> {
    const res = await api.get(`/hotels/${slug}/analytics/export`, { responseType: 'blob' })
    return res.data
  },
}

export default analyticsService
