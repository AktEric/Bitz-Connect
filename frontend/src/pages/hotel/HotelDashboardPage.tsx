import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { Eye, Users, Briefcase, FileText, TrendingUp, ClipboardList, LayoutGrid, Sparkles, BarChart3, Download } from 'lucide-react'
import { useHotelCan } from '@/hooks/useSubscription'
import { useCompanyAnalytics } from '@/hooks/useAnalytics'
import { AnalyticsCard } from '@/components/analytics/AnalyticsCard'
import { TrendChart } from '@/components/analytics/TrendChart'
import { JobsBreakdownChart } from '@/components/analytics/JobsBreakdownChart'
import { Button } from '@/components/ui/Button'
import { SkeletonCard } from '@/components/ui/Skeleton'
import analyticsService from '@/services/analytics.service'
import { useToast } from '@/contexts/ToastContext'

/** Ícone por métrica (fallback: BarChart3). */
const ICONS: Record<string, React.ReactNode> = {
  profile_views:   <Eye className="h-4 w-4" />,
  followers:       <Users className="h-4 w-4" />,
  active_jobs:     <Briefcase className="h-4 w-4" />,
  total_jobs:      <Briefcase className="h-4 w-4" />,
  applications:    <ClipboardList className="h-4 w-4" />,
  posts:           <FileText className="h-4 w-4" />,
  conversion_rate: <TrendingUp className="h-4 w-4" />,
}

/**
 * Dashboard empresarial (§7/§8). Gated pela camada central de entitlements:
 * só hotéis no plano PRO Empresa veem as métricas; os demais veem o convite
 * de upgrade (§13).
 * Sprint: monetization-hotelconnect-pro (Empresa PRO)
 */
export default function HotelDashboardPage() {
  const { slug = '' } = useParams<{ slug: string }>()
  const navigate = useNavigate()
  const canAnalytics = useHotelCan(slug, 'company_analytics')
  const { data, isLoading } = useCompanyAnalytics(slug, canAnalytics)
  const { success, error } = useToast()
  const [exporting, setExporting] = useState(false)

  async function handleExport() {
    setExporting(true)
    try {
      const blob = await analyticsService.exportReport(slug)
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `relatorio-${slug}-${new Date().toISOString().slice(0, 10)}.csv`
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
      success('Relatório baixado com sucesso.')
    } catch {
      error('Não foi possível gerar o relatório. Tente novamente.')
    } finally {
      setExporting(false)
    }
  }

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <BarChart3 className="h-6 w-6 text-primary" />
          <h1 className="text-2xl font-semibold">Dashboard</h1>
        </div>
        {canAnalytics && data && (
          <Button
            variant="outline"
            icon={<Download className="h-4 w-4" />}
            onClick={handleExport}
            disabled={exporting}
          >
            {exporting ? 'Gerando…' : 'Exportar relatório'}
          </Button>
        )}
      </div>

      {!canAnalytics ? (
        <div className="rounded-2xl border border-amber-200 bg-amber-50/50 p-8 text-center">
          <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
            <Sparkles className="h-3.5 w-3.5" /> ConnectHotel PRO — Empresa
          </span>
          <h2 className="mt-3 text-lg font-semibold text-gray-900">Analytics é um recurso PRO Empresa</h2>
          <p className="mx-auto mt-1 max-w-md text-sm text-gray-500">
            Acompanhe visualizações do perfil, candidaturas, taxa de conversão e mais.
            Assine o plano Empresa para desbloquear o dashboard completo.
          </p>
          <Button className="mt-4" icon={<Sparkles className="h-4 w-4" />} onClick={() => navigate('/plans')}>
            Conhecer ConnectHotel PRO
          </Button>
        </div>
      ) : isLoading || !data ? (
        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
          {Array.from({ length: 6 }).map((_, i) => <SkeletonCard key={i} />)}
        </div>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            {data.metrics.map((m) => (
              <AnalyticsCard key={m.key} metric={m} icon={ICONS[m.key] ?? <LayoutGrid className="h-4 w-4" />} />
            ))}
          </div>

          <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <TrendChart title="Candidaturas (últimos 30 dias)" data={data.applications_trend} color="#7c3aed" />
            <TrendChart title="Novos seguidores (últimos 30 dias)" data={data.followers_trend} color="#0ea5e9" />
          </div>

          <div className="mt-4">
            <JobsBreakdownChart jobs={data.jobs_breakdown} />
          </div>

          <p className="mt-4 text-xs text-gray-400">
            Novos indicadores (cliques e visualizações de vagas) chegam em breve.
          </p>
        </>
      )}
    </div>
  )
}
