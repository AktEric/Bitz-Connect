import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { CalendarDays, ClipboardList, GraduationCap, Newspaper, Plane, Target, Wallet, Clock, ChevronRight } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { useAuth } from '@/hooks/useAuth'
import { useTimeTeam, useTimesheet } from '@/hooks/useWorkspace'
import { Avatar } from '@/components/ui/Avatar'
import ClockCard from './ClockCard'
import { fmtMinutes } from './timeUtils'
import { Empty, Panel, Pill, fmtDate, timeAgo, useWs } from './ui'

function greeting() {
  const h = Number(new Date().toLocaleString('pt-BR', { hour: 'numeric', hour12: false, timeZone: 'America/Sao_Paulo' }))
  return h < 12 ? 'Bom dia' : h < 18 ? 'Boa tarde' : 'Boa noite'
}

/** Início do colaborador: ponto em destaque, o que fazer agora, mural e metas. Gestão aparece só para quem gere. */
export default function WorkspaceHomePage() {
  const { slug, info } = useWs()
  const { user } = useAuth()
  const lead = info.me.is_admin || info.me.is_manager
  const c = info.counts
  const month = new Date().toISOString().slice(0, 7)

  const { data: feed } = useQuery({ queryKey: ['ws', slug, 'feed', 1], queryFn: () => workspaceService.feed(slug, 1) })
  const { data: goals } = useQuery({ queryKey: ['ws', slug, 'goals', 'mine'], queryFn: () => workspaceService.goals(slug, { scope: 'mine' }) })
  const { data: sheet } = useTimesheet(slug, month)
  const { data: team } = useTimeTeam(slug, lead)

  const firstName = (user?.name ?? '').split(' ')[0]
  const working = team?.filter((t) => t.state === 'working').length ?? 0

  const actions = [
    { to: 'solicitacoes', label: 'Pedir férias', Icon: Plane, tone: 'bg-sky-50 text-sky-600' },
    { to: 'ponto', label: 'Meu espelho de ponto', Icon: Clock, tone: 'bg-violet-50 text-violet-600' },
    { to: 'recebimentos', label: 'Meu holerite', Icon: Wallet, tone: 'bg-emerald-50 text-emerald-600', badge: c.new_payslips },
    { to: 'metas', label: 'Minhas metas', Icon: Target, tone: 'bg-amber-50 text-amber-600' },
    { to: 'desenvolvimento', label: 'Meu desenvolvimento', Icon: GraduationCap, tone: 'bg-pink-50 text-pink-600' },
    { to: 'equipe', label: 'Pessoas', Icon: CalendarDays, tone: 'bg-slate-100 text-slate-600' },
  ]

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-bold text-gray-900 sm:text-3xl">{greeting()}{firstName ? `, ${firstName}` : ''}</h1>
        <p className="mt-1 text-sm text-gray-500">
          {[info.me.job_title, info.me.department, info.hotel.name].filter(Boolean).join(' · ')}
        </p>
      </div>

      <div className="grid gap-5 xl:grid-cols-3">
        <div className="space-y-5 xl:col-span-2">
          <ClockCard slug={slug} />

          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {actions.map(({ to, label, Icon, tone, badge }) => (
              <Link key={to + label} to={to} className="group relative flex items-center gap-3 rounded-2xl border border-gray-100 bg-white p-3.5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <span className={`flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl ${tone}`}><Icon className="h-5 w-5" /></span>
                <span className="text-sm font-medium text-gray-800">{label}</span>
                {!!badge && <span className="absolute right-3 top-3 h-2 w-2 rounded-full bg-violet-600" />}
              </Link>
            ))}
          </div>

          <Panel>
            <div className="mb-3 flex items-center justify-between">
              <h2 className="flex items-center gap-2 font-semibold text-gray-900"><Newspaper className="h-4 w-4 text-violet-500" /> Mural da empresa</h2>
              <Link to="mural" className="text-sm font-medium text-violet-600">Ver tudo</Link>
            </div>
            {!feed?.items.length ? (
              <Empty text="Nenhuma publicação ainda." />
            ) : (
              <ul className="divide-y divide-gray-50">
                {feed.items.slice(0, 4).map((p) => (
                  <li key={p.uuid} className="flex gap-3 py-3 first:pt-0 last:pb-0">
                    <Avatar src={p.author?.avatar_url} name={p.author?.name ?? '?'} size="sm" />
                    <div className="min-w-0">
                      <p className="text-sm">
                        <span className="font-semibold text-gray-900">{p.author?.name}</span>{' '}
                        <span className="text-xs text-gray-400">{timeAgo(p.created_at)}</span>{' '}
                        {p.pinned && <Pill tone="amber">Fixado</Pill>}
                      </p>
                      <p className="line-clamp-2 text-sm text-gray-600">{p.body}</p>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        </div>

        <div className="space-y-5">
          {lead && (
            <Panel className="border-violet-100 bg-violet-50/40">
              <h2 className="mb-3 font-semibold text-gray-900">Para você, gestor</h2>
              <Link to="solicitacoes?aba=aprovar" className="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm">
                <ClipboardList className="h-5 w-5 text-violet-600" />
                <span className="flex-1 text-sm text-gray-700"><b>{c.requests_to_review}</b> {c.requests_to_review === 1 ? 'pedido aguarda' : 'pedidos aguardam'} sua análise</span>
                <ChevronRight className="h-4 w-4 text-gray-300" />
              </Link>
              <Link to="ponto?aba=time" className="mt-2 flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm">
                <Clock className="h-5 w-5 text-emerald-600" />
                <span className="flex-1 text-sm text-gray-700"><b>{working}</b> de {team?.length ?? 0} pessoas trabalhando agora</span>
                <ChevronRight className="h-4 w-4 text-gray-300" />
              </Link>
            </Panel>
          )}

          <Panel>
            <h2 className="mb-3 font-semibold text-gray-900">Meu mês</h2>
            <div className="grid grid-cols-2 gap-3">
              <div className="rounded-xl bg-gray-50 p-3">
                <p className="text-xl font-bold text-gray-900">{sheet ? fmtMinutes(sheet.worked_minutes) : '—'}</p>
                <p className="text-xs text-gray-500">horas trabalhadas</p>
              </div>
              <div className="rounded-xl bg-gray-50 p-3">
                <p className={`text-xl font-bold ${sheet && sheet.balance_minutes < 0 ? 'text-red-600' : 'text-emerald-600'}`}>{sheet ? fmtMinutes(sheet.balance_minutes) : '—'}</p>
                <p className="text-xs text-gray-500">saldo do banco de horas</p>
              </div>
            </div>
          </Panel>

          <Panel>
            <div className="mb-3 flex items-center justify-between">
              <h2 className="flex items-center gap-2 font-semibold text-gray-900"><Target className="h-4 w-4 text-violet-500" /> Minhas metas</h2>
              <Link to="metas" className="text-sm font-medium text-violet-600">Ver</Link>
            </div>
            {!goals?.length ? (
              <p className="text-sm text-gray-400">Sem metas no momento.</p>
            ) : (
              <ul className="space-y-3">
                {goals.slice(0, 3).map((g) => (
                  <li key={g.uuid}>
                    <div className="flex justify-between text-sm"><span className="truncate text-gray-700">{g.title}</span><span className="text-gray-500">{Math.round(g.progress)}%</span></div>
                    <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100">
                      <div className="h-full rounded-full bg-violet-500" style={{ width: `${Math.min(100, g.progress)}%` }} />
                    </div>
                    <p className="mt-0.5 text-[11px] text-gray-400">até {fmtDate(g.period_end)}</p>
                  </li>
                ))}
              </ul>
            )}
          </Panel>

          {info.me.manager && (
            <Panel>
              <h2 className="mb-2 font-semibold text-gray-900">Meu gestor</h2>
              <div className="flex items-center gap-3">
                <Avatar src={info.me.manager.avatar_url} name={info.me.manager.name} size="md" />
                <div><p className="text-sm font-medium text-gray-900">{info.me.manager.name}</p><p className="text-xs text-gray-400">{info.me.manager.job_title ?? 'Gestor direto'}</p></div>
              </div>
            </Panel>
          )}
        </div>
      </div>
    </div>
  )
}
