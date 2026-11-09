import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { useTimeBankTeam, useTimeTeam, useTimesheet } from '@/hooks/useWorkspace'
import BankCard from './BankCard'
import ClosingView from './ClosingView'
import EntryChips from './EntryChips'
import { Avatar } from '@/components/ui/Avatar'
import type { TimeState, TimeType } from '@/types/workspace.types'
import ClockCard from './ClockCard'
import { TYPE_LABEL, fmtMinutes } from './timeUtils'
import { btnGhost, Empty, Modal, PageHeader, Panel, Pill, Spinner, Tabs, btnPrimary, errMsg, inputCls, labelCls, useWs } from './ui'

const WEEKDAY = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb']
const STATE_PILL: Record<TimeState, { tone: 'green' | 'amber' | 'gray'; label: string }> = {
  working: { tone: 'green', label: 'Trabalhando' },
  break: { tone: 'amber', label: 'Em pausa' },
  out: { tone: 'gray', label: 'Fora' },
}

function shiftMonth(ym: string, delta: number) {
  const [y, m] = ym.split('-').map(Number)
  const d = new Date(y, m - 1 + delta, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function monthTitle(ym: string) {
  const [y, m] = ym.split('-').map(Number)
  return new Date(y, m - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
}

function Timesheet({ slug, userUuid, canAdjust }: { slug: string; userUuid?: string; canAdjust?: boolean }) {
  const [month, setMonth] = useState(() => new Date().toISOString().slice(0, 7))
  const { data, isLoading } = useTimesheet(slug, month, userUuid)
  const qc = useQueryClient()
  const [adjust, setAdjust] = useState(false)
  const [type, setType] = useState<TimeType>('in')
  const [when, setWhen] = useState('')
  const [note, setNote] = useState('')
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  const save = async () => {
    if (!userUuid) return
    setSaving(true)
    setError('')
    try {
      await workspaceService.manualEntry(slug, { user_uuid: userUuid, type, punched_at: when, note })
      await qc.invalidateQueries({ queryKey: ['ws', slug, 'time'] })
      setAdjust(false)
      setNote('')
    } catch (e) {
      setError(errMsg(e))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Panel className="p-0">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4">
        <div className="flex items-center gap-1">
          <button type="button" onClick={() => setMonth(shiftMonth(month, -1))} className="rounded-lg p-1.5 hover:bg-gray-100" aria-label="Mês anterior"><ChevronLeft className="h-4 w-4" /></button>
          <h2 className="min-w-[9rem] text-center font-semibold capitalize text-gray-900">{monthTitle(month)}</h2>
          <button type="button" onClick={() => setMonth(shiftMonth(month, 1))} className="rounded-lg p-1.5 hover:bg-gray-100" aria-label="Próximo mês"><ChevronRight className="h-4 w-4" /></button>
        </div>
        {data && (
          <div className="flex gap-4 text-sm">
            <span className="text-gray-500">Trabalhadas <b className="text-gray-900">{fmtMinutes(data.worked_minutes)}</b></span>
            <span className="text-gray-500">Saldo <b className={data.balance_minutes < 0 ? 'text-red-600' : 'text-emerald-600'}>{fmtMinutes(data.balance_minutes)}</b></span>
          </div>
        )}
        {canAdjust && <button type="button" className={btnPrimary} onClick={() => setAdjust(true)}>Lançar marcação</button>}
      </div>

      {isLoading ? <Spinner /> : (
        <ul className="divide-y divide-gray-50">
          {data?.days.filter((d) => d.entries.length || d.expected_minutes || d.holiday).map((d) => {
            const day = new Date(d.date + 'T12:00:00')
            return (
              <li key={d.date} className="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2.5 text-sm">
                <span className="w-16 font-medium text-gray-900">{String(day.getDate()).padStart(2, '0')} <span className="text-xs font-normal uppercase text-gray-400">{WEEKDAY[d.weekday]}</span></span>
                <span className="min-w-0 flex-1 text-gray-600">
                  {d.holiday && <Pill tone="gray">{d.holiday}</Pill>}{' '}
                  {(!d.holiday || d.entries.length > 0) && <EntryChips slug={slug} entries={d.entries} />}
                </span>
                <span className="font-medium tabular-nums text-gray-900">{fmtMinutes(d.worked_minutes)}</span>
              </li>
            )
          })}
          {!data?.days.some((d) => d.entries.length) && <li className="px-4 py-8 text-center text-sm text-gray-400">Sem marcações neste mês.</li>}
        </ul>
      )}
      {data?.days.some((d) => d.entries.some((e) => e.source === 'manual')) && (
        <p className="border-t border-gray-100 px-4 py-2 text-xs text-gray-400">* lançamento manual, com justificativa registrada.</p>
      )}

      {adjust && (
        <Modal title="Lançar marcação manual" onClose={() => setAdjust(false)}>
          <div className="space-y-3">
            <div>
              <label className={labelCls}>Tipo</label>
              <select className={inputCls} value={type} onChange={(e) => setType(e.target.value as TimeType)}>
                {(Object.keys(TYPE_LABEL) as TimeType[]).map((t) => <option key={t} value={t}>{TYPE_LABEL[t]}</option>)}
              </select>
            </div>
            <div>
              <label className={labelCls}>Data e hora</label>
              <input type="datetime-local" className={inputCls} value={when} onChange={(e) => setWhen(e.target.value)} />
            </div>
            <div>
              <label className={labelCls}>Justificativa (obrigatória)</label>
              <input className={inputCls} value={note} onChange={(e) => setNote(e.target.value)} maxLength={255} />
            </div>
            {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
            <button type="button" className={btnPrimary + ' w-full'} disabled={saving || !when || !note} onClick={save}>Salvar lançamento</button>
          </div>
        </Modal>
      )}
    </Panel>
  )
}

const ADJ_PILL = { pending: { tone: 'amber', label: 'Pendente' }, approved: { tone: 'green', label: 'Aprovado' }, rejected: { tone: 'red', label: 'Recusado' } } as const

function MyAdjustments({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const { data } = useQuery({ queryKey: ['ws', slug, 'time', 'adj', 'mine'], queryFn: () => workspaceService.timeAdjustments(slug, 'mine') })
  const [open, setOpen] = useState(false)
  const [type, setType] = useState<TimeType>('in')
  const [when, setWhen] = useState('')
  const [reason, setReason] = useState('')
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  const send = async () => {
    setSaving(true)
    setError('')
    try {
      await workspaceService.requestAdjustment(slug, { type, requested_at: when, reason })
      await qc.invalidateQueries({ queryKey: ['ws', slug, 'time', 'adj'] })
      setOpen(false)
      setReason('')
      setWhen('')
    } catch (e) {
      setError(errMsg(e))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Panel className="mt-5">
      <div className="mb-3 flex items-center justify-between">
        <h2 className="font-semibold text-gray-900">Esqueceu de bater o ponto?</h2>
        <button type="button" className={btnGhost} onClick={() => setOpen(true)}>Pedir ajuste</button>
      </div>
      {!data?.length ? (
        <p className="text-sm text-gray-400">Seus pedidos de ajuste aparecem aqui e seu gestor é avisado para aprovar.</p>
      ) : (
        <ul className="divide-y divide-gray-50">
          {data.slice(0, 5).map((a) => (
            <li key={a.uuid} className="flex items-center gap-3 py-2 text-sm">
              <span className="flex-1 text-gray-700">{TYPE_LABEL[a.type]} em {new Date(a.requested_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Sao_Paulo' })}</span>
              <Pill tone={ADJ_PILL[a.status].tone}>{ADJ_PILL[a.status].label}</Pill>
            </li>
          ))}
        </ul>
      )}
      {open && (
        <Modal title="Pedir ajuste de ponto" onClose={() => setOpen(false)}>
          <div className="space-y-3">
            <div>
              <label className={labelCls}>Marcação esquecida</label>
              <select className={inputCls} value={type} onChange={(e) => setType(e.target.value as TimeType)}>
                {(Object.keys(TYPE_LABEL) as TimeType[]).map((t) => <option key={t} value={t}>{TYPE_LABEL[t]}</option>)}
              </select>
            </div>
            <div>
              <label className={labelCls}>Data e hora que deveria constar</label>
              <input type="datetime-local" className={inputCls} value={when} onChange={(e) => setWhen(e.target.value)} />
            </div>
            <div>
              <label className={labelCls}>Motivo</label>
              <input className={inputCls} value={reason} onChange={(e) => setReason(e.target.value)} maxLength={255} />
            </div>
            {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
            <button type="button" className={btnPrimary + ' w-full'} disabled={saving || !when || !reason} onClick={send}>Enviar pedido</button>
          </div>
        </Modal>
      )}
    </Panel>
  )
}

function ReviewAdjustments({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const { data } = useQuery({ queryKey: ['ws', slug, 'time', 'adj', 'review'], queryFn: () => workspaceService.timeAdjustments(slug, 'review') })
  const [error, setError] = useState('')
  const [notes, setNotes] = useState<Record<string, string>>({})
  if (!data?.length) return null

  const decide = async (uuid: string, decision: 'approved' | 'rejected') => {
    setError('')
    try {
      await workspaceService.decideAdjustment(slug, uuid, decision, notes[uuid]?.trim())
      await qc.invalidateQueries({ queryKey: ['ws', slug, 'time'] })
    } catch (e) {
      setError(errMsg(e))
    }
  }

  return (
    <Panel className="mb-5 border-amber-100 bg-amber-50/40">
      <h2 className="mb-3 font-semibold text-gray-900">Ajustes de ponto para aprovar</h2>
      <ul className="space-y-2">
        {data.map((a) => (
          <li key={a.uuid} className="flex flex-wrap items-center gap-3 rounded-xl bg-white p-3 text-sm shadow-sm">
            <Avatar src={a.person?.avatar_url} name={a.person?.name ?? '?'} size="sm" />
            <span className="min-w-0 flex-1">
              <b className="text-gray-900">{a.person?.name}</b> · {TYPE_LABEL[a.type]} em {new Date(a.requested_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Sao_Paulo' })}
              <span className="block text-xs text-gray-500">{a.reason}</span>
            </span>
            <input
              className={inputCls + ' sm:max-w-xs'}
              placeholder="Justificativa (obrigatória)"
              aria-label="Justificativa da decisão"
              maxLength={255}
              value={notes[a.uuid] ?? ''}
              onChange={(e) => setNotes((n) => ({ ...n, [a.uuid]: e.target.value }))}
            />
            <button type="button" className={btnGhost} disabled={(notes[a.uuid]?.trim().length ?? 0) < 5} onClick={() => decide(a.uuid, 'rejected')}>Recusar</button>
            <button type="button" className={btnPrimary} disabled={(notes[a.uuid]?.trim().length ?? 0) < 5} onClick={() => decide(a.uuid, 'approved')}>Aprovar</button>
          </li>
        ))}
      </ul>
      {error && <p className="mt-2 text-sm text-red-600" role="alert">{error}</p>}
    </Panel>
  )
}

function TeamView({ slug, isAdmin }: { slug: string; isAdmin: boolean }) {
  const { data, isLoading } = useTimeTeam(slug, true)
  const { data: bankRows } = useTimeBankTeam(slug, isAdmin)
  const bankOf = (uuid: string) => bankRows?.find((b) => b.person?.uuid === uuid)?.balance_minutes
  const [selected, setSelected] = useState<string | null>(null)
  if (isLoading) return <Spinner />
  if (!data?.length) return <Empty text="Nenhuma pessoa no seu time ainda." />

  return (
    <div>
    <ReviewAdjustments slug={slug} />
    <div className="grid gap-5 lg:grid-cols-5">
      <Panel className="p-0 lg:col-span-2">
        <ul className="divide-y divide-gray-50">
          {data.map((r) => {
            const uuid = r.person?.uuid ?? ''
            const pill = STATE_PILL[r.state]
            return (
              <li key={uuid}>
                <button type="button" onClick={() => setSelected(uuid)} className={`flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 ${selected === uuid ? 'bg-brand-50/60' : ''}`}>
                  <Avatar src={r.person?.avatar_url} name={r.person?.name ?? '?'} size="sm" />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium text-gray-900">{r.person?.name}</span>
                    <span className="block text-xs text-gray-400">
                      hoje {fmtMinutes(r.worked_minutes)}
                      {isAdmin && bankOf(uuid) !== undefined && (
                        <> · banco <b className={bankOf(uuid)! < 0 ? 'text-red-600' : 'text-emerald-600'}>{fmtMinutes(bankOf(uuid)!)}</b></>
                      )}
                    </span>
                  </span>
                  <Pill tone={pill.tone}>{pill.label}</Pill>
                </button>
              </li>
            )
          })}
        </ul>
      </Panel>
      <div className="lg:col-span-3">
        {selected ? <><BankCard slug={slug} userUuid={selected} canPost={isAdmin} key={`bank-${selected}`} /><div className="mt-5"><Timesheet slug={slug} userUuid={selected} canAdjust key={selected} /></div></> : <Empty text={isAdmin ? 'Selecione uma pessoa para ver o espelho de ponto.' : 'Selecione alguém do seu time.'} />}
      </div>
    </div>
    </div>
  )
}

export default function WorkspaceTimePage() {
  const { slug, info } = useWs()
  const lead = info.me.is_admin || info.me.is_manager
  const [params, setParams] = useSearchParams()
  const tab = info.me.is_admin && params.get('aba') === 'fechamento' ? 'fechamento' : lead && params.get('aba') === 'time' ? 'time' : 'me'

  return (
    <div>
      <PageHeader title="Ponto" subtitle="Registre sua jornada e acompanhe suas horas." />
      {lead && (
        <Tabs
          value={tab}
          onChange={(v) => setParams(v === 'me' ? {} : { aba: v })}
          options={[
            { value: 'me', label: 'Meu ponto' },
            { value: 'time', label: 'Ponto do time' },
            ...(info.me.is_admin ? [{ value: 'fechamento' as const, label: 'Fechamento' }] : []),
          ]}
        />
      )}
      {tab === 'me' ? (
        <div className="grid gap-5 lg:grid-cols-5">
          <div className="lg:col-span-2"><ClockCard slug={slug} compact /><BankCard slug={slug} /><MyAdjustments slug={slug} /></div>
          <div className="lg:col-span-3"><Timesheet slug={slug} /></div>
        </div>
      ) : tab === 'fechamento' ? (
        <ClosingView slug={slug} isOwner={info.me.access_role === 'owner'} />
      ) : (
        <TeamView slug={slug} isAdmin={info.me.is_admin} />
      )}
    </div>
  )
}
