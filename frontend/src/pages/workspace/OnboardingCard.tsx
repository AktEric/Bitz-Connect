import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronRight, Rocket, X } from 'lucide-react'
import workspaceService from '@/services/workspace.service'

/** Convite na Home para dono e RH terminarem a implantação; some quando completa ou ao dispensar. */
export default function OnboardingCard({ slug, enabled }: { slug: string; enabled: boolean }) {
  const qc = useQueryClient()
  const { data } = useQuery({ queryKey: ['ws', slug, 'onboarding'], queryFn: () => workspaceService.onboarding(slug), enabled, staleTime: 60_000 })
  const dismiss = useMutation({ mutationFn: () => workspaceService.dismissOnboarding(slug), onSuccess: () => qc.invalidateQueries({ queryKey: ['ws', slug, 'onboarding'] }) })

  if (!enabled || !data || data.dismissed || data.percent === 100) return null
  const next = data.steps.filter((s) => !s.done).slice(0, 3)

  return (
    <section className="rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50 to-white p-4 shadow-sm" aria-label="Primeiros passos">
      <div className="mb-2 flex items-start justify-between gap-2">
        <h2 className="flex items-center gap-2 font-semibold text-gray-900"><Rocket className="h-4 w-4 text-brand-600" /> Comece por aqui</h2>
        <button type="button" onClick={() => dismiss.mutate()} className="rounded-lg p-1 text-gray-400 hover:bg-white hover:text-gray-600" aria-label="Esconder primeiros passos"><X className="h-4 w-4" /></button>
      </div>
      <div className="mb-3 flex items-center gap-2">
        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-brand-100"><div className="h-full rounded-full bg-brand-600" style={{ width: `${data.percent}%` }} /></div>
        <span className="text-xs tabular-nums text-gray-500">{data.done}/{data.total}</span>
      </div>
      <ul className="space-y-1.5">
        {next.map((s) => (
          <li key={s.key}>
            <Link to={s.to} className="flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm text-gray-700 shadow-sm hover:bg-brand-50">
              <span className="min-w-0 flex-1 truncate">{s.title}</span> <ChevronRight className="h-4 w-4 text-gray-300" />
            </Link>
          </li>
        ))}
      </ul>
      <Link to="comecar" className="mt-3 inline-block text-xs font-medium text-brand-700 hover:underline">Ver todos os passos</Link>
    </section>
  )
}
