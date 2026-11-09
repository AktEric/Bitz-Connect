import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, ArrowRight, Plus, X } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { ApprovalRole, RequestFlowConfig } from '@/types/workspace.types'
import { Empty, PageHeader, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, useWs } from './ui'

const PRESETS: { label: string; steps: ApprovalRole[] }[] = [
  { label: 'Só o gestor', steps: ['manager'] },
  { label: 'Gestor + RH', steps: ['manager', 'hr'] },
  { label: 'Gestor + Financeiro', steps: ['manager', 'finance'] },
  { label: 'Gestor + Diretoria', steps: ['manager', 'director'] },
]

function FlowCard({ slug, item, roles }: { slug: string; item: RequestFlowConfig['types'][number]; roles: RequestFlowConfig['roles'] }) {
  const qc = useQueryClient()
  const [steps, setSteps] = useState<ApprovalRole[]>(item.steps)
  const [msg, setMsg] = useState('')
  const label = (k: ApprovalRole) => roles.find((r) => r.key === k)?.label ?? k
  const dirty = steps.join() !== item.steps.join()
  const available = roles.filter((r) => r.key !== 'manager' && !steps.includes(r.key))
  const save = useMutation({
    mutationFn: () => workspaceService.saveRequestFlow(slug, item.type, steps),
    onSuccess: (d) => { qc.setQueryData(['ws', slug, 'request-flows'], d); setMsg('Fluxo salvo. Vale para as próximas solicitações.') },
    onError: (e) => setMsg(errMsg(e)),
  })
  const move = (i: number, d: -1 | 1) => setSteps((s) => { const n = [...s]; const j = i + d; if (j < 1 || j >= n.length) return s; [n[i], n[j]] = [n[j], n[i]]; return n })

  return (
    <Panel>
      <div className="mb-3 flex flex-wrap items-center gap-2">
        <h2 className="font-semibold text-gray-900">{item.label}</h2>
        {item.is_default && <Pill tone="gray">Padrão</Pill>}
      </div>

      <ol className="flex flex-wrap items-center gap-2" aria-label={`Etapas de ${item.label}`}>
        {steps.map((s, i) => (
          <li key={s} className="flex items-center gap-2">
            {i > 0 && <ArrowRight className="h-4 w-4 text-gray-300" aria-hidden />}
            <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 py-1 pl-3 pr-1 text-sm font-medium text-brand-700">
              <span className="text-xs text-brand-400">{i + 1}.</span> {label(s)}
              {i > 0 && (
                <span className="ml-1 flex items-center">
                  <button type="button" onClick={() => move(i, -1)} disabled={i === 1} className="rounded p-0.5 hover:bg-brand-100 disabled:opacity-30" aria-label={`Mover ${label(s)} para antes`}><ArrowLeft className="h-3.5 w-3.5" /></button>
                  <button type="button" onClick={() => move(i, 1)} disabled={i === steps.length - 1} className="rounded p-0.5 hover:bg-brand-100 disabled:opacity-30" aria-label={`Mover ${label(s)} para depois`}><ArrowRight className="h-3.5 w-3.5" /></button>
                  <button type="button" onClick={() => setSteps((x) => x.filter((r) => r !== s))} className="rounded p-0.5 hover:bg-brand-100" aria-label={`Remover etapa ${label(s)}`}><X className="h-3.5 w-3.5" /></button>
                </span>
              )}
            </span>
          </li>
        ))}
        {available.map((r) => (
          <li key={r.key}><button type="button" onClick={() => setSteps((x) => [...x, r.key])} className="inline-flex items-center gap-1 rounded-full border border-dashed border-gray-300 px-3 py-1 text-sm text-gray-500 hover:border-brand-400 hover:text-brand-600"><Plus className="h-3.5 w-3.5" /> {r.label}</button></li>
        ))}
      </ol>

      <div className="mt-3 flex flex-wrap items-center gap-2">
        <span className="text-xs text-gray-400">Atalhos:</span>
        {PRESETS.map((p) => <button key={p.label} type="button" onClick={() => setSteps(p.steps)} className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200">{p.label}</button>)}
        <div className="ml-auto flex items-center gap-3">
          {msg && <span className="text-sm text-gray-500" role="status">{msg}</span>}
          <button type="button" className={btnPrimary} disabled={!dirty || save.isPending} onClick={() => save.mutate()}>Salvar</button>
        </div>
      </div>
    </Panel>
  )
}

/** Dono e RH definem por onde cada tipo de solicitação passa até ser aprovada. */
export default function WorkspaceRequestFlowsPage() {
  const { slug } = useWs()
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'request-flows'], queryFn: () => workspaceService.requestFlows(slug) })

  return (
    <div>
      <PageHeader
        title="Fluxos de aprovação"
        subtitle="Escolha quem precisa aprovar cada tipo de solicitação, em ordem. O gestor é sempre a primeira etapa."
        actions={<Link to={`/w/${slug}/solicitacoes`} className={btnGhost}><ArrowLeft className="h-4 w-4" /> Solicitações</Link>}
      />
      <p className="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">As mudanças valem para as <b>próximas</b> solicitações. As que já estão em andamento continuam no fluxo com que foram criadas. Se ninguém na empresa tiver o papel de uma etapa, o dono assume; sem dono, a etapa é pulada.</p>
      {isLoading ? <Spinner /> : !data ? <Empty text="Não foi possível carregar os fluxos." /> : (
        <div className="space-y-3">{data.types.map((t) => <FlowCard key={t.type + t.steps.join()} slug={slug} item={t} roles={data.roles} />)}</div>
      )}
      <p className="mt-4 text-xs text-gray-400">Chamados ao RH não passam por fluxo: vão direto para o RH.</p>
    </div>
  )
}
