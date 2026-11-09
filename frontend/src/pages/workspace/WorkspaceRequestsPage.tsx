import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { RequestStatus, RequestType, TicketCategory, WorkspaceRequest } from '@/types/workspace.types'
import { Empty, Modal, PageHeader, Panel, Pill, Spinner, Tabs, btnGhost, btnPrimary, errMsg, fmtDate, fmtMoney, inputCls, labelCls, timeAgo, useWs } from './ui'

const TYPE_LABEL: Record<RequestType, string> = {
  vacation: 'Férias', document: 'Documento', reimbursement: 'Reembolso', schedule: 'Escala / horário', other: 'Outro', ticket: 'Chamado ao RH',
}
const CATEGORY_LABEL: Record<TicketCategory, string> = { time: 'Ponto e banco de horas', payroll: 'Folha e holerite', benefits: 'Benefícios', documents: 'Documentos', other: 'Outro assunto' }
const STATUS: Record<RequestStatus, { label: string; tone: 'amber' | 'green' | 'red' | 'gray' | 'blue' }> = {
  pending: { label: 'Pendente', tone: 'amber' }, in_progress: { label: 'Em atendimento', tone: 'blue' }, resolved: { label: 'Resolvido', tone: 'green' }, approved: { label: 'Aprovada', tone: 'green' },
  rejected: { label: 'Recusada', tone: 'red' }, cancelled: { label: 'Cancelada', tone: 'gray' },
}

function NewRequestModal({ slug, onClose }: { slug: string; onClose: () => void }) {
  const qc = useQueryClient()
  const [type, setType] = useState<RequestType>('vacation')
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [startsOn, setStartsOn] = useState('')
  const [endsOn, setEndsOn] = useState('')
  const [amount, setAmount] = useState('')
  const [category, setCategory] = useState<TicketCategory>('time')
  const [error, setError] = useState('')

  const create = useMutation({
    mutationFn: () => workspaceService.createRequest(slug, {
      type, title: title.trim(), description: description.trim() || null,
      starts_on: type === 'vacation' || type === 'schedule' ? startsOn || null : null,
      ends_on: type === 'vacation' || type === 'schedule' ? endsOn || null : null,
      category: type === 'ticket' ? category : null,
      amount_cents: type === 'reimbursement' && amount ? Math.round(Number(amount.replace(',', '.')) * 100) : null,
    }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'requests'] }); qc.invalidateQueries({ queryKey: ['workspace', slug] }); onClose() },
    onError: (e) => setError(errMsg(e)),
  })

  return (
    <Modal title="Nova solicitação" onClose={onClose}>
      <div className="space-y-3">
        <div>
          <label className={labelCls}>Tipo</label>
          <select className={inputCls} value={type} onChange={(e) => setType(e.target.value as RequestType)}>
            {Object.entries(TYPE_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
        </div>
        <div><label className={labelCls}>Assunto</label><input className={inputCls} value={title} onChange={(e) => setTitle(e.target.value)} maxLength={160} placeholder={type === 'vacation' ? 'Ex.: Férias de janeiro' : ''} /></div>
        {(type === 'vacation' || type === 'schedule') && (
          <div className="grid grid-cols-2 gap-3">
            <div><label className={labelCls}>De</label><input type="date" className={inputCls} value={startsOn} onChange={(e) => setStartsOn(e.target.value)} /></div>
            <div><label className={labelCls}>Até</label><input type="date" className={inputCls} value={endsOn} onChange={(e) => setEndsOn(e.target.value)} /></div>
          </div>
        )}
        {type === 'ticket' && (
          <div>
            <label className={labelCls}>Assunto do chamado</label>
            <select className={inputCls} value={category} onChange={(e) => setCategory(e.target.value as TicketCategory)}>
              {Object.entries(CATEGORY_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
            </select>
            <p className="mt-1 text-xs text-gray-400">Vai direto para o RH (seu gestor não vê) e tem prazo de resposta de 48h.</p>
          </div>
        )}
        {type === 'reimbursement' && (
          <div><label className={labelCls}>Valor (R$)</label><input className={inputCls} inputMode="decimal" value={amount} onChange={(e) => setAmount(e.target.value)} placeholder="0,00" /></div>
        )}
        <div><label className={labelCls}>Detalhes</label><textarea className={inputCls} rows={3} maxLength={3000} value={description} onChange={(e) => setDescription(e.target.value)} /></div>
        {error && <p className="text-sm text-red-600">{error}</p>}
        <div className="flex justify-end gap-2">
          <button className={btnGhost} onClick={onClose}>Cancelar</button>
          <button className={btnPrimary} disabled={!title.trim() || create.isPending} onClick={() => create.mutate()}>Enviar</button>
        </div>
      </div>
    </Modal>
  )
}

function RequestDetail({ slug, uuid, canDecide, canResolve, onClose }: { slug: string; uuid: string; canDecide: boolean; canResolve: boolean; onClose: () => void }) {
  const qc = useQueryClient()
  const key = ['ws', slug, 'request', uuid]
  const { data: r, isLoading } = useQuery({ queryKey: key, queryFn: () => workspaceService.request(slug, uuid) })
  const [note, setNote] = useState('')
  const [comment, setComment] = useState('')
  const [error, setError] = useState('')
  const refresh = () => { qc.invalidateQueries({ queryKey: key }); qc.invalidateQueries({ queryKey: ['ws', slug, 'requests'] }); qc.invalidateQueries({ queryKey: ['workspace', slug] }) }
  const decide = useMutation({ mutationFn: (d: 'approved' | 'rejected') => workspaceService.decideRequest(slug, uuid, d, note.trim() || undefined), onSuccess: () => { setError(''); refresh() }, onError: (e) => setError(errMsg(e)) })
  const cancel = useMutation({ mutationFn: () => workspaceService.cancelRequest(slug, uuid), onSuccess: refresh })
  const assign = useMutation({ mutationFn: () => workspaceService.assignRequest(slug, uuid), onSuccess: () => { setError(''); refresh() }, onError: (e) => setError(errMsg(e)) })
  const resolve = useMutation({ mutationFn: () => workspaceService.resolveRequest(slug, uuid, note.trim()), onSuccess: () => { setError(''); setNote(''); refresh() }, onError: (e) => setError(errMsg(e)) })
  const send = useMutation({ mutationFn: () => workspaceService.commentRequest(slug, uuid, comment.trim()), onSuccess: () => { setComment(''); refresh() } })

  return (
    <Modal title="Solicitação" onClose={onClose}>
      {isLoading || !r ? <Spinner /> : (
        <div className="space-y-3 text-sm">
          <div className="flex items-center gap-3">
            <Avatar src={r.requester?.avatar_url} name={r.requester?.name ?? '?'} size="md" />
            <div><p className="font-semibold text-gray-900">{r.requester?.name}</p><p className="text-xs text-gray-400">{TYPE_LABEL[r.type]} · {timeAgo(r.created_at)}</p></div>
            <span className="ml-auto"><Pill tone={STATUS[r.status].tone}>{STATUS[r.status].label}</Pill></span>
          </div>
          <p className="text-base font-medium text-gray-900">{r.title}</p>
          {r.type === 'ticket' && (
            <p className="flex flex-wrap items-center gap-2 text-gray-600">
              {r.category && <Pill tone="brand">{CATEGORY_LABEL[r.category]}</Pill>}
              {r.assigned_to && <span>Atendido por <b>{r.assigned_to.name}</b></span>}
              {r.due_at && (r.status === 'pending' || r.status === 'in_progress') && <Pill tone={r.overdue ? 'red' : 'gray'}>{r.overdue ? 'Prazo vencido' : `Prazo: ${fmtDate(r.due_at)}`}</Pill>}
            </p>
          )}
          {r.starts_on && <p className="text-gray-600">Período: {fmtDate(r.starts_on)} a {fmtDate(r.ends_on)}</p>}
          {r.amount_cents != null && <p className="text-gray-600">Valor: {fmtMoney(r.amount_cents)}</p>}
          {r.description && <p className="whitespace-pre-wrap text-gray-600">{r.description}</p>}
          {r.status !== 'pending' && r.status !== 'in_progress' && r.status !== 'cancelled' && (
            <p className="rounded-xl bg-gray-50 p-3 text-gray-600">{r.type === 'ticket' ? 'Resolvido' : 'Analisada'} por {r.decided_by?.name ?? '—'} em {fmtDate(r.decided_at)}{r.decision_note ? ` — ${r.decision_note}` : ''}</p>
          )}

          <div className="border-t border-gray-100 pt-3">
            <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Conversa</p>
            <ul className="mb-2 space-y-1.5">
              {r.comments?.map((c) => <li key={c.uuid} className="rounded-xl bg-gray-50 px-3 py-1.5"><span className="font-medium text-gray-900">{c.author?.name}:</span> <span className="text-gray-600">{c.body}</span></li>)}
              {!r.comments?.length && <li className="text-gray-400">Sem mensagens.</li>}
            </ul>
            <div className="flex gap-2">
              <input className={inputCls} value={comment} onChange={(e) => setComment(e.target.value)} placeholder="Escreva uma mensagem…" maxLength={1000} />
              <button className={btnGhost} disabled={!comment.trim() || send.isPending} onClick={() => send.mutate()}>Enviar</button>
            </div>
          </div>

          {r.type === 'ticket' && (r.status === 'pending' || r.status === 'in_progress') && canResolve && !r.is_mine && (
            <div className="border-t border-gray-100 pt-3">
              {r.status === 'pending' && <button className={`${btnGhost} mb-3 w-full`} disabled={assign.isPending} onClick={() => assign.mutate()}>Assumir este chamado</button>}
              <label className={labelCls}>Justificativa da resolução (obrigatória)</label>
              <textarea className={inputCls} rows={2} value={note} onChange={(e) => setNote(e.target.value)} maxLength={1000} placeholder="O que foi feito para resolver" />
              {error && <p className="mt-2 text-red-600" role="alert">{error}</p>}
              <button className={`${btnPrimary} mt-3 w-full`} disabled={resolve.isPending || note.trim().length < 5} onClick={() => resolve.mutate()}>Marcar como resolvido</button>
            </div>
          )}
          {r.type !== 'ticket' && r.status === 'pending' && canDecide && !r.is_mine && (
            <div className="border-t border-gray-100 pt-3">
              <label className={labelCls}>Observação (obrigatória ao recusar)</label>
              <input className={inputCls} value={note} onChange={(e) => setNote(e.target.value)} maxLength={1000} />
              {error && <p className="mt-2 text-red-600">{error}</p>}
              <div className="mt-3 flex gap-2">
                <button className={`${btnPrimary} flex-1`} disabled={decide.isPending} onClick={() => decide.mutate('approved')}>Aprovar</button>
                <button className={`${btnGhost} flex-1 !border-red-200 !text-red-600`} disabled={decide.isPending} onClick={() => decide.mutate('rejected')}>Recusar</button>
              </div>
            </div>
          )}
          {r.status === 'pending' && r.is_mine && (
            <button className={`${btnGhost} w-full`} disabled={cancel.isPending} onClick={() => cancel.mutate()}>Cancelar solicitação</button>
          )}
        </div>
      )}
    </Modal>
  )
}

export default function WorkspaceRequestsPage() {
  const { slug, info } = useWs()
  const canReview = info.me.is_admin || info.me.is_manager
  const [params] = useSearchParams()
  const [scope, setScope] = useState<'mine' | 'review' | 'all'>(canReview && params.get('aba') === 'aprovar' ? 'review' : 'mine')
  const [creating, setCreating] = useState(false)
  const [openId, setOpenId] = useState<string | null>(null)

  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'requests', scope], queryFn: () => workspaceService.requests(slug, { scope }) })

  const options: { value: 'mine' | 'review' | 'all'; label: string }[] = [{ value: 'mine', label: 'Minhas' }]
  if (canReview) options.unshift({ value: 'review', label: 'Para analisar' })
  if (info.me.is_admin) options.push({ value: 'all', label: 'Todas' })

  return (
    <div>
      <PageHeader
        title="Solicitações"
        subtitle="Férias, documentos, reembolsos, chamados ao RH e pedidos ao seu gestor."
        actions={<button className={btnPrimary} onClick={() => setCreating(true)}><Plus className="h-4 w-4" /> Nova</button>}
      />
      {options.length > 1 && <Tabs value={scope} onChange={setScope} options={options} />}

      {isLoading ? <Spinner /> : !data?.items.length ? <Empty text="Nenhuma solicitação por aqui." /> : (
        <div className="space-y-2">
          {data.items.map((r: WorkspaceRequest) => (
            <Panel key={r.uuid} className="!p-3">
              <button type="button" className="flex w-full items-center gap-3 text-left" onClick={() => setOpenId(r.uuid)}>
                <Avatar src={r.requester?.avatar_url} name={r.requester?.name ?? '?'} size="md" />
                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium text-gray-900">{r.title}</p>
                  <p className="truncate text-xs text-gray-400">{r.requester?.name} · {TYPE_LABEL[r.type]} · {timeAgo(r.created_at)}</p>
                </div>
                {r.overdue && <Pill tone="red">Prazo vencido</Pill>}
                <Pill tone={STATUS[r.status].tone}>{STATUS[r.status].label}</Pill>
              </button>
            </Panel>
          ))}
        </div>
      )}

      {creating && <NewRequestModal slug={slug} onClose={() => setCreating(false)} />}
      {openId && <RequestDetail slug={slug} uuid={openId} canDecide={canReview} canResolve={info.me.is_admin} onClose={() => setOpenId(null)} />}
    </div>
  )
}
