/**
 * Tipos de analytics de empresa (§7/§8).
 * A lista de métricas é aberta — o dashboard renderiza o que vier.
 */
export type MetricFormat = 'number' | 'percent'

export interface Metric {
  key: string
  label: string
  value: number
  format: MetricFormat
}

export interface TrendPoint {
  date: string
  count: number
}

export interface JobBreakdown {
  title: string
  status: string
  applications: number
}

export interface CompanyAnalytics {
  metrics: Metric[]
  applications_trend: TrendPoint[]
  followers_trend: TrendPoint[]
  jobs_breakdown: JobBreakdown[]
  applications_by_status: Record<string, number>
}
