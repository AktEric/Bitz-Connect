import { Link } from 'react-router-dom'
import { useCan } from './permissions'
import { useQuery } from '@tanstack/react-query'
import { Check, ChevronRight, Rocket } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Empty, PageHeader, Panel, Spinner, btnPrimary, errMsg, useWs } from './ui'

/** Primeiros passos da empresa: o que já está pronto e o que falta (deduzido dos dados reais). */
export default function WorkspaceOnboardingPage() {
  const { slug } = useWs()
  const canOnboard = useCan('onboarding.view')
  const { data, isLoading, isError, error } = useQuery({ queryKey: ['ws', slug, 'onboarding'], queryFn: () => workspaceService.onboarding(slug), enabled: canOnboard })

  if (!canOnboard) return <Empty text="Os primeiros passos são exclusivos do dono e do RH." />
  if (isLoading) return <Spinner />
  if (isError || !data) return <Empty text={errMsg(error)} />

  return (
    <div>
      <PageHeader title="Primeiros passos" subtitle="Deixe o Workspace pronto para a sua equipe. Cada item é marcado sozinho quando você faz." />
      <Panel className="mb-4">
        <div className="mb-2 flex items-center justify-between text-sm">
          <span className="flex items-center gap-2 font-semibold text-gray-900"><Rocket className="h-4 w-4 text-brand-500" /> {data.done} de {data.total} concluídos</span>
          <span className="tabular-nums text-gray-500">{data.percent}%</span>
        </div>
        <div className="h-2 overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-valuenow={data.percent} aria-valuemin={0} aria-valuemax={100}>
          <div className="h-full rounded-full bg-brand-600 transition-all" style={{ width: `${data.percent}%` }} />
        </div>
        {data.percent === 100 && <p className="mt-3 text-sm text-emerald-700">Tudo pronto! A sua empresa já pode usar o Workspace por completo.</p>}
      </Panel>

      <ol className="space-y-2">
        {data.steps.map((s, i) => (
          <li key={s.key}>
            <Panel className="flex flex-wrap items-center gap-3">
              <span className={`flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-sm font-semibold ${s.done ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'}`} aria-hidden>
                {s.done ? <Check className="h-4 w-4" /> : i + 1}
              </span>
              <div className="min-w-0 flex-1">
                <p className={`font-medium ${s.done ? 'text-gray-500 line-through' : 'text-gray-900'}`}>{s.title}</p>
                <p className="text-xs text-gray-500">{s.hint}</p>
              </div>
              <Link to={`/w/${slug}/${s.to}`} className={s.done ? 'inline-flex items-center gap-1 text-sm font-medium text-brand-600' : btnPrimary}>
                {s.done ? 'Revisar' : s.cta} <ChevronRight className="h-4 w-4" />
              </Link>
            </Panel>
          </li>
        ))}
      </ol>
    </div>
  )
}
