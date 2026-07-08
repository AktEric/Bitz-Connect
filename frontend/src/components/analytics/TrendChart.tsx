import type { TrendPoint } from '@/types/analytics.types'

interface TrendChartProps {
  title: string
  data: TrendPoint[]
  color?: string
}

const WIDTH = 600
const HEIGHT = 120
const PADDING = 8

/**
 * Gráfico de linha leve (SVG puro, sem dependência externa) para exibir a
 * tendência de um indicador nos últimos 30 dias.
 * Sprint: monetization-hotelconnect-pro (Empresa PRO)
 */
export function TrendChart({ title, data, color = '#7c3aed' }: TrendChartProps) {
  const max = Math.max(1, ...data.map((d) => d.count))
  const total = data.reduce((sum, d) => sum + d.count, 0)

  const stepX = data.length > 1 ? (WIDTH - PADDING * 2) / (data.length - 1) : 0
  const points = data.map((d, i) => {
    const x = PADDING + i * stepX
    const y = HEIGHT - PADDING - (d.count / max) * (HEIGHT - PADDING * 2)
    return [x, y] as const
  })

  const linePath = points.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x},${y}`).join(' ')
  const areaPath = `${linePath} L${points[points.length - 1]?.[0] ?? PADDING},${HEIGHT - PADDING} L${PADDING},${HEIGHT - PADDING} Z`

  const first = data[0]?.date
  const last = data[data.length - 1]?.date

  return (
    <div className="rounded-xl border border-gray-100 bg-white p-4">
      <div className="mb-1 flex items-center justify-between">
        <h3 className="text-sm font-semibold text-gray-800">{title}</h3>
        <span className="text-xs font-medium text-gray-400">Total: {total}</span>
      </div>
      {total === 0 ? (
        <div className="flex h-[120px] items-center justify-center text-xs text-gray-400">
          Sem atividade nos últimos 30 dias.
        </div>
      ) : (
        <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} className="h-[120px] w-full" preserveAspectRatio="none">
          <path d={areaPath} fill={color} fillOpacity="0.08" stroke="none" />
          <path d={linePath} fill="none" stroke={color} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
        </svg>
      )}
      {first && last && (
        <div className="mt-1 flex justify-between text-[10px] text-gray-400">
          <span>{formatShortDate(first)}</span>
          <span>{formatShortDate(last)}</span>
        </div>
      )}
    </div>
  )
}

function formatShortDate(iso: string): string {
  const d = new Date(iso + 'T00:00:00')
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })
}
