import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ClipboardList, Plane, Target, Wallet, Clock, ChevronRight } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { useAuth } from '@/hooks/useAuth'
import HomeCalendarCard from './HomeCalendarCard'
import OnboardingCard from './OnboardingCard'
import { useTimeBank, useTimeTeam, useTimesheet } from '@/hooks/useWorkspace'
import { Avatar } from '@/components/ui/Avatar'
import ClockCard from './ClockCard'
import { AbsencesCard, BirthdaysCard, JobsCard, NewHiresCard } from './HomeHighlights'
import { FeedStream } from './WorkspaceFeedPage'
import { fmtMinutes } from './timeUtils'
import { Panel, fmtDate, useWs } from './ui'

function greeting() {
  const h = Number(new Date().toLocaleString('pt-BR', { hour: 'numeric', hour12: false, timeZone: 'America/Sao_Paulo' }))
  return h < 12 ? 'Bom dia' : h < 18 ? 'Boa tarde' : 'Boa noite'
}

/**
 * Início do colaborador: mural no centro (com os aniversariantes do dia) e, ao lado, ponto, destaques da empresa
 * (ausências, novos colaboradores, vagas) e o que é pessoal. Gestão aparece só para quem gere.
 */
export default function WorkspaceHomePage() {
  const { slug, info } = useWs()
  const { user } = useAuth()
  const lead = info.me.is_admin || info.me.is_manager
  const c = info.counts
  const month = new Date().toISOString().slice(0, 7)

  const { data: goals } = useQuery({ queryKey: ['ws', slug, 'goals', 'mine'], queryFn: () => workspaceService.goals(slug, { scope: 'mine' }) })
  const { data: sheet } = useTimesheet(slug, month)
  const { data: bank } = useTimeBank(slug)
  const { data: team } = useTimeTeam(slug, lead)

  const firstName = (user?.name ?? '').split(' ')[0]
  const working = team?.filter((t) => t.state === 'working').length ?? 0

  // só o que se faz com frequência; o resto está no menu lateral
  const actions = [
    { to: 'solicitacoes', label: 'Pedir férias', Icon: Plane, badge: 0 },
    { to: 'recebimentos', label: 'Meu holerite', Icon: Wallet, badge: c.new_payslips },
    { to: 'metas', label: 'Minhas metas', Icon: Target, badge: 0 },
  ]

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 sm:text-3xl">{greeting()}{firstName ? `, ${firstName}` : ''}</h1>
          <p className="mt-1 text-sm text-gray-500">
            {[info.me.job_title, info.me.department, info.hotel.name].filter(Boolean).join(' · ')}
          </p>
        </div>
        <nav className="flex flex-wrap gap-2 lg:hidden" aria-label="Atalhos">
          {actions.map(({ to, label, Icon, badge }) => (
            <Link key={to + label} to={to} className="relative inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 transition hover:border-violet-300 hover:text-violet-700">
              <Icon className="h-4 w-4" /> {label}
              {!!badge && <span className="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-violet-600" />}
            </Link>
          ))}
        </nav>
      </div>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div className="space-y-5">
          <BirthdaysCard slug={slug} />
          <FeedStream compact />
        </div>

        <div className="space-y-5">
          <ClockCard slug={slug} compact />
          <OnboardingCard slug={slug} enabled={info.me.is_admin} />
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

          <AbsencesCard slug={slug} />
          <NewHiresCard slug={slug} />
          <JobsCard slug={slug} />

          <HomeCalendarCard slug={slug} />

          {/* o que é só da pessoa: mês, metas e gestor num cartão só */}
          <Panel>
            <h2 className="mb-3 font-semibold text-gray-900">Meu resumo</h2>
            <div className="grid grid-cols-2 gap-3">
              <Link to="ponto" className="rounded-xl bg-gray-50 p-3 hover:bg-gray-100">
                <p className="text-xl font-bold text-gray-900">{sheet ? fmtMinutes(sheet.worked_minutes) : '—'}</p>
                <p className="text-xs text-gray-500">horas no mês</p>
              </Link>
              <Link to="ponto" className="rounded-xl bg-gray-50 p-3 hover:bg-gray-100">
                <p className={`text-xl font-bold ${bank && bank.balance_minutes < 0 ? 'text-red-600' : 'text-emerald-600'}`}>{bank ? fmtMinutes(bank.balance_minutes) : '—'}</p>
                <p className="text-xs text-gray-500">banco de horas</p>
              </Link>
            </div>

            <div className="mt-4 flex items-center justify-between">
              <h3 className="flex items-center gap-1.5 text-sm font-medium text-gray-700"><Target className="h-4 w-4 text-violet-500" /> Minhas metas</h3>
              <Link to="metas" className="text-xs font-medium text-violet-600">Ver todas</Link>
            </div>
            {!goals?.length ? (
              <p className="mt-1 text-sm text-gray-400">Sem metas no momento.</p>
            ) : (
              <ul className="mt-2 space-y-2.5">
                {goals.slice(0, 2).map((g) => (
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

            {info.me.manager && (
              <div className="mt-4 flex items-center gap-3 border-t border-gray-100 pt-3">
                <Avatar src={info.me.manager.avatar_url} name={info.me.manager.name} size="sm" />
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-gray-900">{info.me.manager.name}</p>
                  <p className="text-xs text-gray-400">Meu gestor{info.me.manager.job_title ? ` · ${info.me.manager.job_title}` : ''}</p>
                </div>
              </div>
            )}
          </Panel>
        </div>
      </div>
    </div>
  )
}
