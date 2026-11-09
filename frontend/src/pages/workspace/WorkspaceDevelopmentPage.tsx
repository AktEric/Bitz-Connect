import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { PlanItemStatus, PlanItemType } from '@/types/workspace.types'
import { Empty, Modal, PageHeader, Panel, Pill, Spinner, Tabs, btnGhost, btnPrimary, errMsg, fmtDate, inputCls, labelCls, timeAgo, useWs } from './ui'

const TYPE: Record<PlanItemType, string> = { course: 'Curso', mentoring: 'Mentoria', project: 'Projeto', reading: 'Leitura', other: 'Outro' }
const STATUS: { value: PlanItemStatus; label: string }[] = [{ value: 'todo', label: 'A fazer' }, { value: 'doing', label: 'Em andamento' }, { value: 'done', label: 'Concluído' }]

function Bar({ value }: { value: number }) {
  return <div className="h-1.5 overflow-hidden rounded-full bg-gray-100"><div className="h-full rounded-full bg-brand-500" style={{ width: `${value}%` }} /></div>
}

function NewPlanModal({ slug, onClose }: { slug: string; onClose: () => void }) {
  const qc = useQueryClient()
  const { data: members } = useQuery({ queryKey: ['ws', slug, 'members'], queryFn: () => workspaceService.members(slug) })
  const [owner, setOwner] = useState('')
  const [title, setTitle] = useState('')
  const [objective, setObjective] = useState('')
  const [end, setEnd] = useState('')
  const [error, setError] = useState('')
  const create = useMutation({
    mutationFn: () => workspaceService.createPlan(slug, { owner_uuid: owner, title: title.trim(), objective: objective.trim() || null, period_start: new Date().toISOString().slice(0, 10), period_end: end || null }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'plans'] }); onClose() },
    onError: (e) => setError(errMsg(e)),
  })
  return (
    <Modal title="Novo PDI" onClose={onClose}>
      <div className="space-y-3">
        <div>
          <label className={labelCls}>Pessoa</label>
          <select className={inputCls} value={owner} onChange={(e) => setOwner(e.target.value)}>
            <option value="">Selecione…</option>
            {members?.map((m) => <option key={m.uuid} value={m.uuid}>{m.name}</option>)}
          </select>
        </div>
        <div><label className={labelCls}>Título</label><input className={inputCls} value={title} onChange={(e) => setTitle(e.target.value)} maxLength={160} placeholder="Ex.: Crescer para liderança" /></div>
        <div><label className={labelCls}>Objetivo</label><textarea className={inputCls} rows={3} value={objective} onChange={(e) => setObjective(e.target.value)} maxLength={3000} /></div>
        <div><label className={labelCls}>Prazo final</label><input type="date" className={inputCls} value={end} onChange={(e) => setEnd(e.target.value)} /></div>
        {error && <p className="text-sm text-red-600">{error}</p>}
        <div className="flex justify-end gap-2">
          <button className={btnGhost} onClick={onClose}>Cancelar</button>
          <button className={btnPrimary} disabled={!owner || !title.trim() || create.isPending} onClick={() => create.mutate()}>Criar</button>
        </div>
      </div>
    </Modal>
  )
}

function PlanDetail({ slug, uuid, onClose }: { slug: string; uuid: string; onClose: () => void }) {
  const qc = useQueryClient()
  const key = ['ws', slug, 'plan', uuid]
  const { data: plan, isLoading } = useQuery({ queryKey: key, queryFn: () => workspaceService.plan(slug, uuid) })
  const [itemTitle, setItemTitle] = useState('')
  const [itemType, setItemType] = useState<PlanItemType>('course')
  const [itemDue, setItemDue] = useState('')
  const [note, setNote] = useState('')
  const refresh = () => { qc.invalidateQueries({ queryKey: key }); qc.invalidateQueries({ queryKey: ['ws', slug, 'plans'] }) }

  const addItem = useMutation({ mutationFn: () => workspaceService.addPlanItem(slug, uuid, { title: itemTitle.trim(), type: itemType, due_on: itemDue || null }), onSuccess: () => { setItemTitle(''); setItemDue(''); refresh() } })
  const setStatus = useMutation({ mutationFn: (v: { item: string; status: PlanItemStatus }) => workspaceService.updatePlanItem(slug, uuid, v.item, { status: v.status }), onSuccess: refresh })
  const delItem = useMutation({ mutationFn: (item: string) => workspaceService.deletePlanItem(slug, uuid, item), onSuccess: refresh })
  const addNote = useMutation({ mutationFn: () => workspaceService.addPlanNote(slug, uuid, note.trim()), onSuccess: () => { setNote(''); refresh() } })

  return (
    <Modal title={plan?.title ?? 'PDI'} onClose={onClose}>
      {isLoading || !plan ? <Spinner /> : (
        <div className="space-y-4 text-sm">
          <div className="flex items-center gap-2 text-gray-500"><Avatar src={plan.owner?.avatar_url} name={plan.owner?.name ?? '?'} size="xs" />{plan.owner?.name}<span className="ml-auto">{plan.progress}% concluído</span></div>
          <Bar value={plan.progress} />
          {plan.objective && <p className="whitespace-pre-wrap text-gray-600">{plan.objective}</p>}
          {plan.period_end && <p className="text-xs text-gray-400">Prazo: {fmtDate(plan.period_end)}</p>}

          <div>
            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Ações</p>
            <ul className="space-y-2">
              {plan.items?.map((i) => (
                <li key={i.uuid} className="flex items-center gap-2 rounded-xl bg-gray-50 px-3 py-2">
                  <div className="min-w-0 flex-1">
                    <p className={`truncate ${i.status === 'done' ? 'text-gray-400 line-through' : 'text-gray-800'}`}>{i.title}</p>
                    <p className="text-xs text-gray-400">{TYPE[i.type]}{i.due_on ? ` · até ${fmtDate(i.due_on)}` : ''}</p>
                  </div>
                  <select className="rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs" value={i.status} onChange={(e) => setStatus.mutate({ item: i.uuid, status: e.target.value as PlanItemStatus })} aria-label="Status">
                    {STATUS.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                  </select>
                  <button className="text-gray-300 hover:text-red-500" aria-label="Remover ação" onClick={() => delItem.mutate(i.uuid)}><Trash2 className="h-4 w-4" /></button>
                </li>
              ))}
              {!plan.items?.length && <li className="text-gray-400">Nenhuma ação ainda.</li>}
            </ul>
            <div className="mt-2 flex flex-wrap gap-2">
              <input className={`${inputCls} min-w-[140px] flex-1`} value={itemTitle} onChange={(e) => setItemTitle(e.target.value)} placeholder="Nova ação" maxLength={160} />
              <select className={`${inputCls} !w-auto`} value={itemType} onChange={(e) => setItemType(e.target.value as PlanItemType)}>{Object.entries(TYPE).map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
              <input type="date" className={`${inputCls} !w-auto`} value={itemDue} onChange={(e) => setItemDue(e.target.value)} aria-label="Prazo" />
              <button className={btnGhost} disabled={!itemTitle.trim() || addItem.isPending} onClick={() => addItem.mutate()}><Plus className="h-4 w-4" /></button>
            </div>
          </div>

          <div>
            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Acompanhamento</p>
            <ul className="mb-2 space-y-1.5">
              {plan.notes?.map((n) => <li key={n.uuid} className="rounded-xl bg-gray-50 px-3 py-2"><span className="font-medium text-gray-900">{n.author?.name}</span> <span className="text-xs text-gray-400">{timeAgo(n.created_at)}</span><p className="text-gray-600">{n.body}</p></li>)}
              {!plan.notes?.length && <li className="text-gray-400">Sem anotações.</li>}
            </ul>
            <div className="flex gap-2">
              <input className={inputCls} value={note} onChange={(e) => setNote(e.target.value)} placeholder="Registrar conversa ou evolução…" maxLength={2000} />
              <button className={btnGhost} disabled={!note.trim() || addNote.isPending} onClick={() => addNote.mutate()}>Registrar</button>
            </div>
          </div>
        </div>
      )}
    </Modal>
  )
}

export default function WorkspaceDevelopmentPage() {
  const { slug, info } = useWs()
  const canManage = info.me.is_admin || info.me.is_manager
  const [scope, setScope] = useState<'mine' | 'team'>('mine')
  const [creating, setCreating] = useState(false)
  const [openId, setOpenId] = useState<string | null>(null)
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'plans', scope], queryFn: () => workspaceService.plans(slug, scope) })

  return (
    <div>
      <PageHeader
        title="Desenvolvimento"
        subtitle="Plano de Desenvolvimento Individual (PDI): objetivos, ações e acompanhamento."
        actions={canManage ? <button className={btnPrimary} onClick={() => setCreating(true)}><Plus className="h-4 w-4" /> Novo PDI</button> : undefined}
      />
      {canManage && <Tabs value={scope} onChange={setScope} options={[{ value: 'mine', label: 'Meus' }, { value: 'team', label: 'Equipe' }]} />}

      {isLoading ? <Spinner /> : !data?.length ? <Empty text="Nenhum PDI por aqui ainda." /> : (
        <div className="grid gap-3 md:grid-cols-2">
          {data.map((p) => (
            <Panel key={p.uuid}>
              <button type="button" className="w-full text-left" onClick={() => setOpenId(p.uuid)}>
                <div className="flex items-start justify-between gap-2">
                  <p className="font-semibold text-gray-900">{p.title}</p>
                  {p.status !== 'active' && <Pill tone="gray">{p.status === 'completed' ? 'Concluído' : 'Arquivado'}</Pill>}
                </div>
                <p className="mt-0.5 flex items-center gap-1.5 text-xs text-gray-400"><Avatar src={p.owner?.avatar_url} name={p.owner?.name ?? '?'} size="xs" />{p.owner?.name}</p>
                <div className="mt-3 flex justify-between text-xs text-gray-500"><span>{p.items_done}/{p.items_total} ações</span><span>{p.progress}%</span></div>
                <div className="mt-1"><Bar value={p.progress} /></div>
              </button>
            </Panel>
          ))}
        </div>
      )}

      {creating && <NewPlanModal slug={slug} onClose={() => setCreating(false)} />}
      {openId && <PlanDetail slug={slug} uuid={openId} onClose={() => setOpenId(null)} />}
    </div>
  )
}
