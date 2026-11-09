import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ClipboardList, Plane, Target, Wallet, Clock, ChevronRight } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { useAuth } from '@/hooks/useAuth'
import HomeCalendarCard from './HomeCalendarCard'
import OnboardingCard from './OnboardingCard'
import { useTimeBank, useTimeTeam, useTimesheet } from '@/hooks/useWorkspace'
import ClockCard from './ClockCard'
import { AbsencesCard, BirthdaysCard, JobsCard, NewHiresCard } from './HomeHighlights'
import { FeedStream } from './WorkspaceFeedPage'
import { fmtMinutes } from './timeUtils'
import PersonLink, { PersonAvatarLink } from './PersonLink'
import { useCan } from './permissions'
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
  const canReview = useCan('requests.review')
  const canSeeTeamClock = useCan('time.team')
  const canOnboard = useCan('onboarding.view')
  const c = info.counts
  const month = new Date().toISOString().slice(0, 7)

  const { data: goals } = useQuery({ queryKey: ['ws', slug, 'goals', 'mine'], queryFn: () => workspaceService.goals(slug, { scope: 'mine' }) })
  const { data: sheet } = useTimesheet(slug, month)
  const { data: bank } = useTimeBank(slug)
  const { data: team } = useTimeTeam(slug, canSeeTeamClock)

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
      <div className="flex flex-wrap items-end justify-between gap-3 xl:hidden">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 sm:text-3xl">{greeting()}{firstName ? `, ${firstName}` : ''}</h1>
          <p className="mt-1 text-sm text-gray-500">{[info.me.job_title, info.me.department, info.hotel.name].filter(Boolean).join(' · ')}</p>
        </div>
        <nav className="flex flex-wrap gap-2" aria-label="Atalhos">
          {actions.map(({ to, label, Icon, badge }) => (
            <Link key={to + label} to={to} className="relative inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 transition hover:border-brand-300 hover:text-brand-700">
              <Icon className="h-4 w-4" /> {label}
              {!!badge && <span className="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-brand-600" />}
            </Link>
          ))}
        </nav>
      </div>

      {/* Três colunas como no Voors: meu espaço | mural | destaques da empresa */}
      <div className="grid grid-cols-1 gap-6 xl:grid-cols-[17rem_minmax(0,1fr)_20rem]">
        <aside className="space-y-5 xl:order-1" aria-label="Meu espaço">
          <Panel className="!p-4">
            <div className="flex items-center gap-3">
              <PersonAvatarLink person={{ uuid: user?.uuid ?? '', name: user?.name ?? '', avatar_url: user?.avatar_url }} size="xl" className="ring-2 ring-brand-100" />
              <div className="min-w-0">
                <p className="text-xs text-gray-400">{greeting()},</p>
                <PersonLink person={user?.uuid ? { uuid: user.uuid, name: user.name } : null} className="block truncate text-base font-semibold text-gray-900">
                  {firstName}
                </PersonLink>
                {info.me.job_title && <p className="truncate text-xs font-medium text-brand-700">{info.me.job_title}</p>}
                <p className="truncate text-xs text-gray-500">{info.me.department ?? info.hotel.name}</p>
              </div>
            </div>
            <Link to="perfil" className="mt-4 flex items-center justify-center gap-1 rounded-full bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-100">
              Ver meu perfil e carreira <ChevronRight className="h-3.5 w-3.5" />
            </Link>
          </Panel>

          <ClockCard slug={slug} compact />
          <OnboardingCard slug={slug} enabled={canOnboard} />

          {canReview && (
            <Panel className="border-brand-100 bg-brand-50/40">
              <h2 className="mb-3 font-semibold text-gray-900">Para você, gestor</h2>
              <Link to="solicitacoes?aba=aprovar" className="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm">
                <ClipboardList className="h-5 w-5 text-brand-600" />
                <span className="flex-1 text-sm text-gray-700"><b>{c.requests_to_review}</b> {c.requests_to_review === 1 ? 'pedido aguarda' : 'pedidos aguardam'} sua análise</span>
                <ChevronRight className="h-4 w-4 text-gray-300" />
              </Link>
              {canSeeTeamClock && (
                <Link to="ponto?aba=time" className="mt-2 flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm">
                  <Clock className="h-5 w-5 text-emerald-600" />
                  <span className="flex-1 text-sm text-gray-700"><b>{working}</b> de {team?.length ?? 0} pessoas trabalhando agora</span>
                  <ChevronRight className="h-4 w-4 text-gray-300" />
                </Link>
              )}
            </Panel>
          )}

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
              <h3 className="flex items-center gap-1.5 text-sm font-medium text-gray-700"><Target className="h-4 w-4 text-brand-500" /> Minhas metas</h3>
              <Link to="metas" className="text-xs font-medium text-brand-600">Ver todas</Link>
            </div>
            {!goals?.length ? (
              <p className="mt-1 text-sm text-gray-400">Sem metas no momento.</p>
            ) : (
              <ul className="mt-2 space-y-2.5">
                {goals.slice(0, 2).map((g) => (
                  <li key={g.uuid}>
                    <div className="flex justify-between text-sm"><span className="truncate text-gray-700">{g.title}</span><span className="text-gray-500">{Math.round(g.progress)}%</span></div>
                    <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100">
                      <div className="h-full rounded-full bg-brand-500" style={{ width: `${Math.min(100, g.progress)}%` }} />
                    </div>
                    <p className="mt-0.5 text-[11px] text-gray-400">até {fmtDate(g.period_end)}</p>
                  </li>
                ))}
              </ul>
            )}

            {info.me.manager && (
              <div className="mt-4 flex items-center gap-3 border-t border-gray-100 pt-3">
                <PersonAvatarLink person={info.me.manager} size="sm" />
                <div className="min-w-0">
                  <PersonLink person={info.me.manager} className="block truncate text-sm font-medium text-gray-900" />
                  <p className="text-xs text-gray-400">Meu gestor{info.me.manager.job_title ? ` · ${info.me.manager.job_title}` : ''}</p>
                </div>
              </div>
            )}
          </Panel>
        </aside>

        <div className="space-y-5 xl:order-2">
          <BirthdaysCard slug={slug} />
          <FeedStream compact />
        </div>

        <aside className="space-y-5 xl:order-3" aria-label="Destaques da empresa">
          <AbsencesCard slug={slug} />
          <NewHiresCard slug={slug} />
          <JobsCard slug={slug} />
          <HomeCalendarCard slug={slug} />
        </aside>
      </div>
    </div>
  )
}
