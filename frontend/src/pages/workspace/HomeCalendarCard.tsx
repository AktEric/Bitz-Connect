import { useMemo } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { CalendarDays } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import { Panel } from './ui'
import { TYPE_META, addDays, parseYmd, ymd } from './calendarUtils'

const DAY_LABEL = (date: string, today: string) => {
  if (date === today) return 'Hoje'
  if (date === ymd(addDays(parseYmd(today), 1))) return 'Amanhã'
  return parseYmd(date).toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' })
}

/** Aniversariantes, tempo de casa e próximos eventos/feriados dos próximos dias. */
export default function HomeCalendarCard({ slug }: { slug: string }) {
  const today = ymd(new Date())
  const to = ymd(addDays(new Date(), 21))
  const { data } = useQuery({ queryKey: ['ws', slug, 'calendar', 'home', today], queryFn: () => workspaceService.calendar(slug, today, to), staleTime: 300_000 })

  const { people, next } = useMemo(() => {
    const items = data?.items ?? []
    return {
      people: items.filter((i) => (i.type === 'birthday' || i.type === 'anniversary') && i.date <= ymd(addDays(new Date(), 14))).slice(0, 5),
      next: items.filter((i) => i.type === 'event' || i.type === 'holiday').slice(0, 3),
    }
  }, [data])

  return (
    <Panel>
      <div className="mb-3 flex items-center justify-between">
        <h2 className="flex items-center gap-2 font-semibold text-gray-900"><CalendarDays className="h-4 w-4 text-brand-500" /> Calendário</h2>
        <Link to="calendario" className="text-sm font-medium text-brand-600">Abrir</Link>
      </div>

      {people.length === 0 && next.length === 0 ? (
        <p className="text-sm text-gray-400">Nada marcado para os próximos dias.</p>
      ) : (
        <ul className="space-y-2.5">
          {people.map((p, i) => (
            <li key={`p${i}`} className="flex items-center gap-2.5">
              <Avatar src={p.person?.avatar_url} name={p.person?.name ?? p.title} size="xs" />
              <p className="min-w-0 flex-1 truncate text-sm text-gray-700">
                <span aria-hidden>{TYPE_META[p.type].emoji}</span> <b className="font-medium text-gray-900">{p.title}</b>
                <span className="text-gray-400"> · {p.type === 'birthday' ? 'aniversário' : p.subtitle}</span>
              </p>
              <span className={`text-xs ${p.date === today ? 'font-semibold text-pink-600' : 'text-gray-400'}`}>{DAY_LABEL(p.date, today)}</span>
            </li>
          ))}
          {next.map((e, i) => (
            <li key={`e${i}`} className="flex items-center gap-2.5">
              <span className="flex h-6 w-6 items-center justify-center rounded-full bg-brand-50 text-xs" aria-hidden>{TYPE_META[e.type].emoji}</span>
              <p className="min-w-0 flex-1 truncate text-sm text-gray-700">{e.title}{e.time && <span className="text-gray-400"> · {e.time}</span>}</p>
              <span className="text-xs text-gray-400">{DAY_LABEL(e.date, today)}</span>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  )
}
