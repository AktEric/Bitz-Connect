import type { CalendarItem, CalendarItemType } from '@/types/workspace.types'

export const pad = (n: number) => String(n).padStart(2, '0')
export const ymd = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
export const parseYmd = (s: string) => new Date(`${s}T12:00:00`)
export const addDays = (d: Date, n: number) => { const x = new Date(d); x.setDate(x.getDate() + n); return x }

export const TYPE_META: Record<CalendarItemType, { label: string; emoji: string; chip: string; dot: string }> = {
  birthday: { label: 'Aniversários', emoji: '🎂', chip: 'bg-pink-50 text-pink-700', dot: 'bg-pink-400' },
  anniversary: { label: 'Tempo de casa', emoji: '🎉', chip: 'bg-amber-50 text-amber-700', dot: 'bg-amber-400' },
  holiday: { label: 'Feriados', emoji: '🏳️', chip: 'bg-gray-100 text-gray-700', dot: 'bg-gray-400' },
  event: { label: 'Eventos', emoji: '📅', chip: 'bg-brand-50 text-brand-700', dot: 'bg-brand-500' },
  vacation: { label: 'Férias', emoji: '🏖️', chip: 'bg-sky-50 text-sky-700', dot: 'bg-sky-400' },
}
export const TYPE_ORDER: CalendarItemType[] = ['event', 'holiday', 'birthday', 'anniversary', 'vacation']

/** Distribui cada item (inclusive os de vários dias) nos dias do intervalo visível. */
export function groupByDay(items: CalendarItem[], from: string, to: string): Record<string, CalendarItem[]> {
  const out: Record<string, CalendarItem[]> = {}
  for (const it of items) {
    const end = it.end_date ?? it.date
    for (let d = parseYmd(it.date < from ? from : it.date); ymd(d) <= (end > to ? to : end); d = addDays(d, 1)) {
      ;(out[ymd(d)] ??= []).push(it)
    }
  }
  for (const k of Object.keys(out)) {
    out[k].sort((a, b) => TYPE_ORDER.indexOf(a.type) - TYPE_ORDER.indexOf(b.type) || (a.time ?? '').localeCompare(b.time ?? '') || a.title.localeCompare(b.title))
  }
  return out
}

export const itemLabel = (it: CalendarItem) =>
  it.type === 'birthday' ? it.title : it.type === 'anniversary' ? `${it.title} · ${it.subtitle}` : it.type === 'vacation' ? `${it.title} (férias)` : it.title
