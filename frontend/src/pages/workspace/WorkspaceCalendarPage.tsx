import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarPlus, Check, ChevronLeft, ChevronRight, Copy, Link2, MapPin, Trash2 } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { CalendarItem, CalendarItemType, RsvpStatus } from '@/types/workspace.types'
import { cn } from '@/utils/cn'
import { Modal, PageHeader, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, inputCls, labelCls, useWs } from './ui'
import { TYPE_META, TYPE_ORDER, addDays, groupByDay, itemLabel, parseYmd, ymd } from './calendarUtils'

const WEEKDAYS = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb']

function NewEventModal({ slug, day, canHoliday, onClose }: { slug: string; day: string; canHoliday: boolean; onClose: () => void }) {
  const qc = useQueryClient()
  const { data: departments } = useQuery({ queryKey: ['ws', slug, 'departments'], queryFn: () => workspaceService.departments(slug) })
  const [kind, setKind] = useState<'event' | 'holiday'>('event')
  const [title, setTitle] = useState('')
  const [start, setStart] = useState(day)
  const [end, setEnd] = useState('')
  const [allDay, setAllDay] = useState(true)
  const [t1, setT1] = useState('09:00')
  const [t2, setT2] = useState('10:00')
  const [location, setLocation] = useState('')
  const [description, setDescription] = useState('')
  const [dept, setDept] = useState('')
  const [error, setError] = useState('')

  const save = useMutation({
    mutationFn: () => workspaceService.createCalendarEvent(slug, {
      title: title.trim(), kind, starts_on: start, ends_on: end || null,
      starts_time: kind === 'event' && !allDay ? t1 : null, ends_time: kind === 'event' && !allDay ? t2 : null,
      location: location.trim() || null, description: description.trim() || null, department_uuid: kind === 'event' ? dept || null : null,
    }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'calendar'] }); onClose() },
    onError: (e) => setError(errMsg(e)),
  })

  return (
    <Modal title="Novo no calendário" onClose={onClose}>
      <div className="space-y-3">
        {canHoliday && (
          <div className="inline-flex rounded-xl bg-gray-100 p-1">
            {([['event', 'Evento'], ['holiday', 'Feriado da empresa']] as const).map(([v, l]) => (
              <button key={v} type="button" onClick={() => setKind(v)} className={cn('rounded-lg px-3 py-1.5 text-sm font-medium', kind === v ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500')}>{l}</button>
            ))}
          </div>
        )}
        <div><label className={labelCls}>Título</label><input className={inputCls} value={title} maxLength={120} onChange={(e) => setTitle(e.target.value)} placeholder={kind === 'holiday' ? 'Ex.: Aniversário da cidade' : 'Ex.: Treinamento de segurança'} /></div>
        <div className="grid gap-3 sm:grid-cols-2">
          <div><label className={labelCls}>Data</label><input type="date" className={inputCls} value={start} onChange={(e) => { setStart(e.target.value); if (end && end < e.target.value) setEnd('') }} /></div>
          <div><label className={labelCls}>Até (opcional)</label><input type="date" className={inputCls} min={start} value={end} onChange={(e) => setEnd(e.target.value)} /></div>
        </div>
        {kind === 'event' && (
          <>
            <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" className="rounded border-gray-300 text-violet-600" checked={allDay} onChange={(e) => setAllDay(e.target.checked)} /> Dia inteiro
            </label>
            {!allDay && (
              <div className="grid gap-3 sm:grid-cols-2">
                <div><label className={labelCls}>Início</label><input type="time" className={inputCls} value={t1} onChange={(e) => setT1(e.target.value)} /></div>
                <div><label className={labelCls}>Fim</label><input type="time" className={inputCls} value={t2} onChange={(e) => setT2(e.target.value)} /></div>
              </div>
            )}
            <div><label className={labelCls}>Local (opcional)</label><input className={inputCls} value={location} maxLength={120} onChange={(e) => setLocation(e.target.value)} /></div>
            <div>
              <label className={labelCls}>Quem vê</label>
              <select className={inputCls} value={dept} onChange={(e) => setDept(e.target.value)}>
                <option value="">Toda a empresa</option>
                {departments?.map((d) => <option key={d.uuid} value={d.uuid}>Setor {d.name}</option>)}
              </select>
            </div>
          </>
        )}
        <div><label className={labelCls}>Descrição (opcional)</label><textarea className={inputCls} rows={2} maxLength={1000} value={description} onChange={(e) => setDescription(e.target.value)} /></div>
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>Cancelar</button>
          <button type="button" className={btnPrimary} disabled={!title.trim() || !start || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Salvando…' : 'Salvar'}</button>
        </div>
      </div>
    </Modal>
  )
}

function BirthdayModal({ slug, me, onClose }: { slug: string; me: { birth_date: string | null; birthday_visible: boolean }; onClose: () => void }) {
  const qc = useQueryClient()
  const [date, setDate] = useState(me.birth_date ?? '')
  const [visible, setVisible] = useState(me.birthday_visible)
  const [error, setError] = useState('')
  const save = useMutation({
    mutationFn: () => workspaceService.updateMyBirthday(slug, { birth_date: date || null, birthday_visible: visible }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'calendar'] }); onClose() },
    onError: (e) => setError(errMsg(e)),
  })
  return (
    <Modal title="Meu aniversário" onClose={onClose}>
      <div className="space-y-3">
        <div><label className={labelCls}>Data de nascimento</label><input type="date" className={inputCls} max={ymd(new Date())} value={date} onChange={(e) => setDate(e.target.value)} /></div>
        <label className="flex cursor-pointer items-start gap-2 text-sm text-gray-700">
          <input type="checkbox" className="mt-0.5 rounded border-gray-300 text-violet-600" checked={visible} onChange={(e) => setVisible(e.target.checked)} />
          <span>Mostrar meu aniversário para a equipe<br /><span className="text-xs text-gray-400">Só o dia e o mês aparecem; o ano de nascimento fica visível apenas para você e para o RH.</span></span>
        </label>
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>Cancelar</button>
          <button type="button" className={btnPrimary} disabled={save.isPending} onClick={() => save.mutate()}>Salvar</button>
        </div>
      </div>
    </Modal>
  )
}

function IcsModal({ slug, onClose }: { slug: string; onClose: () => void }) {
  const qc = useQueryClient()
  const [copied, setCopied] = useState(false)
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'calendar', 'ics'], queryFn: () => workspaceService.icsLink(slug) })
  const rotate = useMutation({
    mutationFn: () => workspaceService.rotateIcsLink(slug),
    onSuccess: (r) => qc.setQueryData(['ws', slug, 'calendar', 'ics'], r),
  })
  const copy = async () => {
    if (!data) return
    try { await navigator.clipboard.writeText(data.url); setCopied(true); setTimeout(() => setCopied(false), 2000) } catch { /* sem permissão: o campo está selecionável */ }
  }
  return (
    <Modal title="Assinar no meu calendário" onClose={onClose}>
      <div className="space-y-3 text-sm text-gray-600">
        <p>Use este link para ver aniversários, feriados, eventos e férias no Google Agenda, Outlook ou Apple Calendário. Ele atualiza sozinho.</p>
        {isLoading || !data ? <Spinner /> : (
          <div className="flex gap-2">
            <input className={inputCls} readOnly value={data.url} onFocus={(e) => e.currentTarget.select()} aria-label="Link do calendário" />
            <button type="button" className={btnGhost} onClick={() => void copy()}>{copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}{copied ? 'Copiado' : 'Copiar'}</button>
          </div>
        )}
        <ul className="list-disc space-y-1 pl-5 text-xs text-gray-500">
          <li><b>Google Agenda:</b> Outras agendas → “+” → De URL → cole o link.</li>
          <li><b>Outlook:</b> Adicionar calendário → Assinar na Web → cole o link.</li>
          <li><b>Apple:</b> Arquivo → Nova assinatura de calendário.</li>
        </ul>
        <p className="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800">Quem tem o link vê o mesmo que você. Não compartilhe; se vazar, gere um novo e o anterior deixa de funcionar.</p>
        <button type="button" className={btnGhost} disabled={rotate.isPending} onClick={() => { if (window.confirm('Gerar um novo link? O atual deixa de funcionar nos seus calendários.')) rotate.mutate() }}>
          <Link2 className="h-4 w-4" /> Gerar novo link
        </button>
      </div>
    </Modal>
  )
}

function EventCard({ slug, item }: { slug: string; item: CalendarItem }) {
  const qc = useQueryClient()
  const ev = item.event!
  const refresh = () => qc.invalidateQueries({ queryKey: ['ws', slug, 'calendar'] })
  const rsvp = useMutation({ mutationFn: (s: RsvpStatus | null) => workspaceService.rsvpEvent(slug, ev.uuid, s), onSuccess: refresh })
  const del = useMutation({ mutationFn: () => workspaceService.deleteCalendarEvent(slug, ev.uuid), onSuccess: refresh })
  const opts: [RsvpStatus, string][] = [['going', 'Vou'], ['maybe', 'Talvez'], ['no', 'Não vou']]

  return (
    <div className="mt-2 rounded-xl bg-violet-50/60 px-3 py-2">
      {ev.description && <p className="mb-2 whitespace-pre-wrap text-sm text-gray-600">{ev.description}</p>}
      {ev.kind === 'event' && (
        <div className="flex flex-wrap items-center gap-1.5">
          {opts.map(([s, l]) => (
            <button
              key={s} type="button" aria-pressed={ev.rsvp.mine === s} disabled={rsvp.isPending}
              onClick={() => rsvp.mutate(ev.rsvp.mine === s ? null : s)}
              className={cn('rounded-full border px-3 py-1 text-xs font-medium', ev.rsvp.mine === s ? 'border-violet-600 bg-violet-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50')}
            >
              {l}
            </button>
          ))}
          <span className="ml-1 text-xs text-gray-500">{ev.rsvp.going} {ev.rsvp.going === 1 ? 'confirmado' : 'confirmados'}{ev.rsvp.maybe ? ` · ${ev.rsvp.maybe} talvez` : ''}</span>
        </div>
      )}
      {ev.can_edit && (
        <button type="button" className="mt-2 inline-flex items-center gap-1 text-xs text-gray-400 hover:text-red-600" onClick={() => { if (window.confirm('Remover este item do calendário?')) del.mutate() }}>
          <Trash2 className="h-3.5 w-3.5" /> Remover
        </button>
      )}
    </div>
  )
}

function DayList({ slug, day, items }: { slug: string; day: string; items: CalendarItem[] }) {
  const d = parseYmd(day)
  return (
    <Panel>
      <h2 className="mb-3 font-semibold capitalize text-gray-900">
        {d.toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' })}
        {day === ymd(new Date()) && <Pill tone="violet"> hoje</Pill>}
      </h2>
      {items.length === 0 ? <p className="text-sm text-gray-400">Nada marcado para este dia.</p> : (
        <ul className="space-y-3">
          {items.map((it, i) => (
            <li key={`${it.type}-${it.date}-${it.title}-${i}`}>
              <div className="flex items-start gap-2.5">
                <span className="text-lg leading-6" aria-hidden>{TYPE_META[it.type].emoji}</span>
                {it.person && <Avatar src={it.person.avatar_url} name={it.person.name} size="xs" />}
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-gray-900">
                    {it.type === 'birthday' ? `Aniversário de ${it.title}` : it.title}
                    {it.hidden_from_team && <span className="ml-2 text-[11px] font-normal text-gray-400">(só você e o RH veem)</span>}
                  </p>
                  <p className="flex flex-wrap items-center gap-x-2 text-xs text-gray-500">
                    {it.time && <span>{it.time}</span>}
                    {it.subtitle && <span className="flex items-center gap-1">{it.event?.location && <MapPin className="h-3 w-3" />}{it.subtitle}</span>}
                    {it.end_date && <span>até {parseYmd(it.end_date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })}</span>}
                  </p>
                  {it.event && <EventCard slug={slug} item={it} />}
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  )
}

export default function WorkspaceCalendarPage() {
  const { slug } = useWs()
  const today = ymd(new Date())
  const [cursor, setCursor] = useState(() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1) })
  const [selected, setSelected] = useState(today)
  const [hidden, setHidden] = useState<Set<CalendarItemType>>(new Set())
  const [modal, setModal] = useState<'event' | 'birthday' | 'ics' | null>(null)

  // Grade de 6 semanas (domingo a sábado) que cobre o mês, para mostrar também os dias vizinhos.
  const gridStart = useMemo(() => addDays(cursor, -cursor.getDay()), [cursor])
  const days = useMemo(() => Array.from({ length: 42 }, (_, i) => addDays(gridStart, i)), [gridStart])
  const from = ymd(days[0])
  const to = ymd(days[41])

  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'calendar', from, to], queryFn: () => workspaceService.calendar(slug, from, to) })
  const items = useMemo(() => (data?.items ?? []).filter((i) => !hidden.has(i.type)), [data, hidden])
  const byDay = useMemo(() => groupByDay(items, from, to), [items, from, to])

  const move = (n: number) => { const c = new Date(cursor.getFullYear(), cursor.getMonth() + n, 1); setCursor(c); setSelected(ymd(c)) }
  const goToday = () => { const d = new Date(); setCursor(new Date(d.getFullYear(), d.getMonth(), 1)); setSelected(today) }
  const toggle = (t: CalendarItemType) => setHidden((h) => { const n = new Set(h); if (n.has(t)) n.delete(t); else n.add(t); return n })

  return (
    <div>
      <PageHeader
        title="Calendário"
        subtitle="Aniversários, tempo de casa, feriados, férias e eventos da empresa."
        actions={(
          <div className="flex flex-wrap gap-2">
            <button type="button" className={btnGhost} onClick={() => setModal('birthday')}>🎂 Meu aniversário</button>
            <button type="button" className={btnGhost} onClick={() => setModal('ics')}><Link2 className="h-4 w-4" /> Assinar</button>
            {data?.can_create && <button type="button" className={btnPrimary} onClick={() => setModal('event')}><CalendarPlus className="h-4 w-4" /> Novo</button>}
          </div>
        )}
      />

      <div className="mb-3 flex flex-wrap items-center gap-2">
        <div className="flex items-center gap-1">
          <button type="button" onClick={() => move(-1)} className="rounded-lg p-1.5 hover:bg-gray-100" aria-label="Mês anterior"><ChevronLeft className="h-5 w-5" /></button>
          <h2 className="min-w-[10rem] text-center text-lg font-semibold capitalize text-gray-900">{cursor.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })}</h2>
          <button type="button" onClick={() => move(1)} className="rounded-lg p-1.5 hover:bg-gray-100" aria-label="Próximo mês"><ChevronRight className="h-5 w-5" /></button>
          <button type="button" className={`${btnGhost} ml-1 !px-3 !py-1`} onClick={goToday}>Hoje</button>
        </div>
        <div className="ml-auto flex flex-wrap gap-1.5" role="group" aria-label="Filtros">
          {TYPE_ORDER.map((t) => (
            <button
              key={t} type="button" aria-pressed={!hidden.has(t)} onClick={() => toggle(t)}
              className={cn('inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors', hidden.has(t) ? 'border-gray-200 bg-white text-gray-400 line-through' : 'border-transparent ' + TYPE_META[t].chip)}
            >
              <span className={cn('h-2 w-2 rounded-full', TYPE_META[t].dot)} /> {TYPE_META[t].label}
            </button>
          ))}
        </div>
      </div>

      {isLoading ? <Spinner /> : (
        <div className="grid gap-4 lg:grid-cols-3">
          <Panel className="p-2 lg:col-span-2">
            <div className="grid grid-cols-7 border-b border-gray-100 pb-1 text-center text-[11px] font-medium uppercase text-gray-400">
              {WEEKDAYS.map((w) => <div key={w}>{w}</div>)}
            </div>
            <div className="grid grid-cols-7">
              {days.map((d) => {
                const key = ymd(d)
                const list = byDay[key] ?? []
                const inMonth = d.getMonth() === cursor.getMonth()
                return (
                  <button
                    key={key} type="button" onClick={() => setSelected(key)} aria-label={`${d.getDate()} de ${d.toLocaleDateString('pt-BR', { month: 'long' })}${list.length ? `, ${list.length} item(ns)` : ''}`}
                    className={cn('flex min-h-[3.5rem] flex-col items-stretch gap-0.5 border-b border-r border-gray-50 p-1 text-left align-top transition-colors hover:bg-violet-50/50 sm:min-h-[5.5rem]', !inMonth && 'bg-gray-50/60', selected === key && 'bg-violet-50 ring-2 ring-inset ring-violet-300')}
                  >
                    <span className={cn('flex h-6 w-6 items-center justify-center rounded-full text-xs', key === today ? 'bg-violet-600 font-semibold text-white' : inMonth ? 'text-gray-800' : 'text-gray-300')}>{d.getDate()}</span>
                    <span className="hidden flex-col gap-0.5 sm:flex">
                      {list.slice(0, 3).map((it, i) => (
                        <span key={i} className={cn('truncate rounded px-1 text-[10px] leading-4', TYPE_META[it.type].chip)}>{TYPE_META[it.type].emoji} {itemLabel(it)}</span>
                      ))}
                      {list.length > 3 && <span className="px-1 text-[10px] text-gray-400">+{list.length - 3}</span>}
                    </span>
                    <span className="mt-auto flex flex-wrap gap-0.5 sm:hidden">
                      {list.slice(0, 4).map((it, i) => <span key={i} className={cn('h-1.5 w-1.5 rounded-full', TYPE_META[it.type].dot)} />)}
                    </span>
                  </button>
                )
              })}
            </div>
          </Panel>
          <DayList slug={slug} day={selected} items={byDay[selected] ?? []} />
        </div>
      )}

      {modal === 'event' && data && <NewEventModal slug={slug} day={selected} canHoliday={data.can_holiday} onClose={() => setModal(null)} />}
      {modal === 'birthday' && data && <BirthdayModal slug={slug} me={data.me} onClose={() => setModal(null)} />}
      {modal === 'ics' && <IcsModal slug={slug} onClose={() => setModal(null)} />}
    </div>
  )
}
