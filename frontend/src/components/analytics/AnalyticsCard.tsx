import type { Metric } from '@/types/analytics.types'

interface AnalyticsCardProps {
  metric: Metric
  icon?: React.ReactNode
}

function formatValue(metric: Metric): string {
  if (metric.format === 'percent') return `${metric.value}%`
  return new Intl.NumberFormat('pt-BR').format(metric.value)
}

/**
 * Card de indicador reutilizável do dashboard (§12: AnalyticsCard).
 * Sprint: monetization-hotelconnect-pro (Empresa PRO)
 */
export function AnalyticsCard({ metric, icon }: AnalyticsCardProps) {
  return (
    <div className="rounded-xl border border-gray-100 bg-white p-4">
      <div className="flex items-center justify-between">
        <span className="text-xs font-medium text-gray-500">{metric.label}</span>
        {icon && <span className="text-gray-300">{icon}</span>}
      </div>
      <p className="mt-2 text-2xl font-bold text-gray-900">{formatValue(metric)}</p>
    </div>
  )
}
