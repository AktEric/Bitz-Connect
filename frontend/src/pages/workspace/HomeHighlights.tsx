import { useState, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, ArrowRight, Briefcase, ChevronLeft, ChevronRight, MessageCircle, PartyPopper } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import { cn } from '@/utils/cn'
import type { HighlightPerson } from '@/types/workspace.types'
import { Panel, fmtDate, useWs } from './ui'

export function useHomeHighlights(slug: string) {
  return useQuery({ queryKey: ['ws', slug, 'home', 'highlights'], queryFn: () => workspaceService.homeHighlights(slug), staleTime: 5 * 60_000 })
}

/** Cartão com um item por vez e setas ‹ ›, como os destaques da coluna direita da Início. */
function Carousel<T>({ title, items, render, footer }: { title: string; items: T[]; render: (item: T) => ReactNode; footer?: ReactNode }) {
  const [i, setI] = useState(0)
  const n = items.length
  if (!n) return null
  const at = Math.min(i, n - 1)
  const go = (d: number) => setI((at + d + n) % n)

  return (
    <Panel className="p-5">
      <h2 className="mb-4 text-center font-semibold text-gray-900">{title}</h2>
      <div className="flex items-center gap-2" aria-roledescription="carrossel" aria-label={title}>
        {n > 1 && (
          <button type="button" onClick={() => go(-1)} aria-label="Anterior" className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-gray-700 text-white hover:bg-gray-900">
            <ChevronLeft className="h-4 w-4" />
          </button>
        )}
        <div className="min-w-0 flex-1" aria-live="polite" aria-atomic>{render(items[at])}</div>
        {n > 1 && (
          <button type="button" onClick={() => go(1)} aria-label="Próximo" className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-gray-700 text-white hover:bg-gray-900">
            <ChevronRight className="h-4 w-4" />
          </button>
        )}
      </div>
      {n > 1 && (
        <div className="mt-4 flex justify-center gap-1.5" aria-hidden>
          {items.map((_, k) => <span key={k} className={cn('h-1.5 rounded-full transition-all', k === at ? 'w-4 bg-violet-600' : 'w-1.5 bg-gray-200')} />)}
        </div>
      )}
      {footer}
    </Panel>
  )
}

function PersonBlock({ person, tag, children }: { person: HighlightPerson; tag?: string; children: ReactNode }) {
  return (
    <div className="flex flex-col items-center text-center">
      <div className="relative">
        <Avatar src={person.avatar_url} name={person.name} size="xl" className="ring-4 ring-violet-50" />
        {tag && <span className="absolute -bottom-1.5 left-1/2 -translate-x-1/2 rounded-md bg-emerald-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">{tag}</span>}
      </div>
      <p className="mt-3 font-semibold text-gray-900">{person.name}</p>
      {children}
    </div>
  )
}

const daysUntil = (ymd: string) => Math.round((new Date(ymd + 'T12:00:00').getTime() - new Date(new Date().toDateString() + ' 12:00').getTime()) / 86_400_000)

export function AbsencesCard({ slug }: { slug: string }) {
  const { data } = useHomeHighlights(slug)

  return (
    <Carousel
      title="Ausências"
      items={data?.absences ?? []}
      render={(a) => {
        const d = daysUntil(a.starts_on)
        return (
          <PersonBlock person={a.person} tag={a.label}>
            <p className="text-xs text-gray-500">{a.ongoing ? 'Ausente agora' : d === 1 ? 'Sai amanhã' : `Sai em ${d} dias`}</p>
            <div className="mt-2 space-y-0.5 text-sm text-gray-600">
              <p className="flex items-center justify-center gap-1.5"><ArrowRight className="h-3.5 w-3.5 text-gray-400" /> {fmtDate(a.starts_on)}</p>
              <p className="flex items-center justify-center gap-1.5"><ArrowLeft className="h-3.5 w-3.5 text-gray-400" /> {fmtDate(a.ends_on)}</p>
            </div>
          </PersonBlock>
        )
      }}
    />
  )
}

export function NewHiresCard({ slug }: { slug: string }) {
  const { openChat } = useWs()
  const { data } = useHomeHighlights(slug)

  return (
    <Carousel
      title="Novos colaboradores"
      items={data?.new_hires ?? []}
      render={(h) => (
        <PersonBlock person={h.person}>
          <p className="text-sm text-gray-600">{[h.person.job_title, h.department].filter(Boolean).join(' · ') || 'Colaborador'}</p>
          <p className="mt-1 text-xs text-gray-400">Admissão em {fmtDate(h.hired_at)}</p>
          <button
            type="button" onClick={() => openChat(h.person)}
            className="mt-3 inline-flex items-center gap-1.5 rounded-full border border-violet-200 px-3 py-1.5 text-xs font-medium text-violet-700 hover:bg-violet-50"
          >
            <MessageCircle className="h-3.5 w-3.5" /> Dar boas-vindas
          </button>
        </PersonBlock>
      )}
    />
  )
}

export function JobsCard({ slug }: { slug: string }) {
  const { data } = useHomeHighlights(slug)

  return (
    <Carousel
      title="Vagas abertas"
      items={data?.jobs ?? []}
      render={(j) => (
        <div className="flex flex-col items-center text-center">
          <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-50 text-violet-600"><Briefcase className="h-6 w-6" /></span>
          <p className="mt-3 font-semibold text-gray-900">{j.title}</p>
          <p className="text-sm text-gray-600">{[j.department, j.contract_type].filter(Boolean).join(' · ')}</p>
          {j.closes_on && <p className="mt-1 text-xs text-gray-400">Inscrições até {fmtDate(j.closes_on)}</p>}
        </div>
      )}
      footer={<Link to="vagas" className="mt-4 block text-center text-sm font-medium text-violet-600 hover:underline">Ver todas as vagas</Link>}
    />
  )
}

/** Confete decorativo (posições fixas, para não "pular" entre renderizações). */
const CONFETTI = [
  ['left-[8%] top-[10%]', 'bg-violet-500', 'rotate-12'], ['right-[10%] top-[8%]', 'bg-amber-400', '-rotate-12'],
  ['left-[14%] top-[36%]', 'bg-pink-400', 'rotate-45'], ['right-[8%] top-[32%]', 'bg-sky-400', 'rotate-12'],
  ['left-[48%] top-[3%]', 'bg-emerald-400', '-rotate-45'], ['right-[18%] top-[20%]', 'bg-violet-400', 'rotate-45'],
] as const

/** Aniversariantes do dia: cartões com foto e cargo, e um atalho para dar parabéns no chat. */
export function BirthdaysCard({ slug }: { slug: string }) {
  const { openChat } = useWs()
  const { data } = useHomeHighlights(slug)
  const people = data?.birthdays_today ?? []
  if (!people.length) return null

  return (
    <Panel className="p-5">
      <h2 className="mb-4 flex items-center gap-2 font-semibold text-gray-900"><PartyPopper className="h-5 w-5 text-pink-500" /> Aniversariantes do dia</h2>
      <ul className="flex gap-4 overflow-x-auto pb-1">
        {people.map((p) => (
          <li key={p.uuid} className="relative w-52 flex-shrink-0 overflow-hidden rounded-2xl bg-gradient-to-b from-violet-50 to-fuchsia-50 shadow-sm">
            {CONFETTI.map(([pos, color, rot], k) => <span key={k} className={cn('absolute h-2 w-2 rounded-[2px]', pos, color, rot)} aria-hidden />)}
            <div className="relative flex flex-col items-center px-4 pb-4 pt-6 text-center">
              <Avatar src={p.avatar_url} name={p.name} size="2xl" className="ring-4 ring-white shadow-md" />
              <p className="mt-3 font-semibold text-gray-900">{p.name}</p>
              <p className="min-h-[1.25rem] text-xs text-violet-700">{p.job_title}</p>
            </div>
            <div className="relative bg-amber-100 px-4 pb-4 pt-5">
              {/* onda que separa a foto do rodapé */}
              <svg viewBox="0 0 200 16" preserveAspectRatio="none" className="absolute inset-x-0 -top-px h-4 w-full text-fuchsia-50" aria-hidden>
                <path d="M0 0h200v6c-25 10-50 10-75 2S75-2 50 6 0 14 0 8z" fill="currentColor" />
              </svg>
              <button
                type="button" onClick={() => openChat(p)}
                className="flex w-full items-center justify-center gap-1.5 rounded-full bg-white px-3 py-2 text-sm font-medium text-gray-800 shadow-sm hover:text-violet-700"
              >
                🎉 Dar parabéns
              </button>
            </div>
          </li>
        ))}
      </ul>
    </Panel>
  )
}
