import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarClock, Pencil, Plus, Trash2 } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { WorkSchedule } from '@/types/workspace.types'
import { Modal, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, inputCls, labelCls } from './ui'
import { fmtMinutes } from './timeUtils'

const DAYS = [
  { dow: 1, label: 'Seg' }, { dow: 2, label: 'Ter' }, { dow: 3, label: 'Qua' }, { dow: 4, label: 'Qui' },
  { dow: 5, label: 'Sex' }, { dow: 6, label: 'Sáb' }, { dow: 0, label: 'Dom' },
]

const PRESETS: { label: string; hours: Record<number, number> }[] = [
  { label: '8h seg–sex', hours: { 0: 0, 1: 8, 2: 8, 3: 8, 4: 8, 5: 8, 6: 0 } },
  { label: '6x1 (6h)', hours: { 0: 0, 1: 6, 2: 6, 3: 6, 4: 6, 5: 6, 6: 6 } },
  { label: '12x36 (plantão)', hours: { 0: 12, 1: 0, 2: 12, 3: 0, 4: 12, 5: 0, 6: 12 } },
]

function weekSummary(s: { week_minutes: Record<string, number> }) {
  return DAYS.filter((d) => (s.week_minutes[String(d.dow)] ?? 0) > 0)
    .map((d) => `${d.label} ${fmtMinutes(s.week_minutes[String(d.dow)])}`).join(' · ') || 'Sem dias de trabalho'
}

function ScheduleModal({ slug, schedule, onClose }: { slug: string; schedule: WorkSchedule | null; onClose: () => void }) {
  const qc = useQueryClient()
  const [name, setName] = useState(schedule?.name ?? '')
  const [hours, setHours] = useState<Record<number, string>>(() => {
    const init: Record<number, string> = {}
    for (const d of DAYS) init[d.dow] = String(((schedule?.week_minutes[String(d.dow)] ?? (d.dow >= 1 && d.dow <= 5 && !schedule ? 480 : 0)) / 60))
    return init
  })
  const [error, setError] = useState('')

  const save = useMutation({
    mutationFn: () => {
      const week_minutes: Record<string, number> = {}
      for (const d of DAYS) week_minutes[String(d.dow)] = Math.round((parseFloat(hours[d.dow]) || 0) * 60)
      const body = { name: name.trim(), week_minutes }
      return schedule ? workspaceService.updateSchedule(slug, schedule.uuid, body) : workspaceService.createSchedule(slug, body)
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'schedules'] }); onClose() },
    onError: (e) => setError(errMsg(e)),
  })

  return (
    <Modal title={schedule ? 'Editar jornada' : 'Nova jornada'} onClose={onClose}>
      <div className="space-y-4">
        <div>
          <label className={labelCls}>Nome</label>
          <input className={inputCls} value={name} onChange={(e) => setName(e.target.value)} maxLength={80} placeholder="Ex.: Recepção — manhã" />
        </div>
        <div>
          <span className={labelCls}>Modelos rápidos</span>
          <div className="flex flex-wrap gap-2">
            {PRESETS.map((p) => (
              <button
                key={p.label}
                type="button"
                className="rounded-full border border-gray-200 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-violet-50 hover:text-violet-700"
                onClick={() => setHours(Object.fromEntries(DAYS.map((d) => [d.dow, String(p.hours[d.dow])])))}
              >
                {p.label}
              </button>
            ))}
          </div>
        </div>
        <div>
          <span className={labelCls}>Horas por dia (0 = folga)</span>
          <div className="grid grid-cols-7 gap-1.5">
            {DAYS.map((d) => (
              <label key={d.dow} className="text-center text-[11px] font-medium text-gray-500">
                {d.label}
                <input
                  type="number" min={0} max={24} step={0.5}
                  className={`${inputCls} mt-1 !px-1 text-center`}
                  value={hours[d.dow]}
                  onChange={(e) => setHours((h) => ({ ...h, [d.dow]: e.target.value }))}
                />
              </label>
            ))}
          </div>
        </div>
        {error && <p className="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>Cancelar</button>
          <button type="button" className={btnPrimary} disabled={!name.trim() || save.isPending} onClick={() => save.mutate()}>
            {save.isPending ? 'Salvando…' : 'Salvar jornada'}
          </button>
        </div>
      </div>
    </Modal>
  )
}

export default function WorkspaceSchedulesSection({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const [editing, setEditing] = useState<WorkSchedule | 'new' | null>(null)
  const [error, setError] = useState('')
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'schedules'], queryFn: () => workspaceService.schedules(slug) })

  const assign = useMutation({
    mutationFn: ({ user, schedule }: { user: string; schedule: string | null }) => workspaceService.assignSchedule(slug, user, schedule),
    onSuccess: () => { setError(''); qc.invalidateQueries({ queryKey: ['ws', slug, 'schedules'] }) },
    onError: (e) => setError(errMsg(e)),
  })
  const remove = useMutation({
    mutationFn: (uuid: string) => workspaceService.deleteSchedule(slug, uuid),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['ws', slug, 'schedules'] }),
    onError: (e) => setError(errMsg(e)),
  })

  if (isLoading || !data) return <Spinner />

  return (
    <section className="mb-8">
      <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <CalendarClock className="h-5 w-5 text-violet-600" />
          <h2 className="text-lg font-semibold text-gray-900">Jornadas de trabalho</h2>
        </div>
        <button type="button" className={btnPrimary} onClick={() => setEditing('new')}><Plus className="h-4 w-4" /> Nova jornada</button>
      </div>
      <p className="mb-3 text-sm text-gray-500">
        Define quantas horas cada pessoa deve cumprir por dia. Quem não tem jornada usa o padrão: 8h de segunda a sexta.
      </p>
      {error && <p className="mb-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

      <div className="mb-4 grid gap-2 sm:grid-cols-2">
        {data.schedules.length === 0 && (
          <div className="rounded-xl border border-dashed border-gray-200 bg-white p-4 text-sm text-gray-400 sm:col-span-2">
            Nenhuma jornada criada. Todos usam o padrão.
          </div>
        )}
        {data.schedules.map((s) => (
          <div key={s.uuid} className="rounded-xl border border-gray-100 bg-white p-3">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <div className="truncate text-sm font-semibold text-gray-900">{s.name}</div>
                <div className="text-xs text-gray-500">{weekSummary(s)}</div>
                <div className="mt-1 flex items-center gap-2 text-xs text-gray-400">
                  <span>{fmtMinutes(s.weekly_total)} por semana</span>
                  <Pill tone="gray">{s.members_count} {s.members_count === 1 ? 'pessoa' : 'pessoas'}</Pill>
                </div>
              </div>
              <div className="flex gap-1">
                <button type="button" className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100" aria-label={`Editar ${s.name}`} onClick={() => setEditing(s)}><Pencil className="h-4 w-4" /></button>
                <button
                  type="button" className="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label={`Remover ${s.name}`}
                  onClick={() => { if (window.confirm(`Remover a jornada “${s.name}”? Quem estava nela volta ao padrão.`)) remove.mutate(s.uuid) }}
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>

      <Panel className="divide-y divide-gray-100 p-0">
        {data.members.map((m) => (
          <div key={m.user.uuid} className="flex flex-wrap items-center gap-3 px-4 py-3">
            <Avatar src={m.user.avatar_url} name={m.user.name} size="md" />
            <div className="min-w-0 flex-1">
              <div className="truncate text-sm font-medium text-gray-900">{m.user.name}</div>
              <div className="truncate text-xs text-gray-500">{m.user.job_title ?? '—'}</div>
            </div>
            <select
              className={`${inputCls} !w-auto max-w-[14rem]`}
              value={m.schedule_uuid ?? ''}
              disabled={assign.isPending}
              aria-label={`Jornada de ${m.user.name}`}
              onChange={(e) => assign.mutate({ user: m.user.uuid, schedule: e.target.value || null })}
            >
              <option value="">Padrão (8h seg–sex)</option>
              {data.schedules.map((s) => <option key={s.uuid} value={s.uuid}>{s.name}</option>)}
            </select>
          </div>
        ))}
      </Panel>

      {editing && <ScheduleModal slug={slug} schedule={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
    </section>
  )
}
