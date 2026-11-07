import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, BadgeCheck, CalendarDays, Clock, ClipboardList, Hourglass, Newspaper, ShieldCheck, Users } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Empty, PageHeader, Panel, Pill, Spinner, errMsg, useWs } from './ui'
import { fmtMinutes } from './timeUtils'

function Stat({ label, value, hint, tone = 'default' }: { label: string; value: ReactNode; hint?: string; tone?: 'default' | 'warn' | 'good' }) {
  const color = tone === 'warn' ? 'text-amber-600' : tone === 'good' ? 'text-emerald-600' : 'text-gray-900'
  return (
    <div className="rounded-xl bg-gray-50 p-3">
      <p className={`text-2xl font-bold tabular-nums ${color}`}>{value}</p>
      <p className="text-xs font-medium text-gray-600">{label}</p>
      {hint && <p className="mt-0.5 text-[11px] text-gray-400">{hint}</p>}
    </div>
  )
}

function Card({ title, Icon, children }: { title: string; Icon: typeof Users; children: ReactNode }) {
  return (
    <Panel>
      <h2 className="mb-3 flex items-center gap-2 font-semibold text-gray-900"><Icon className="h-4 w-4 text-violet-500" /> {title}</h2>
      {children}
    </Panel>
  )
}

const TYPE_LABEL: Record<string, string> = { vacation: 'Férias', document: 'Documentos', reimbursement: 'Reembolsos', schedule: 'Escala', other: 'Outros' }

/** Painel da diretoria: números agregados da empresa (nada aqui identifica o saldo ou a falta de uma pessoa). */
export default function WorkspaceDashboardPage() {
  const { slug, info } = useWs()
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['ws', slug, 'dashboard'],
    queryFn: () => workspaceService.dashboard(slug),
    enabled: !!info.me.can_see_dashboard,
    staleTime: 60_000,
  })

  if (!info.me.can_see_dashboard) return <Empty text="O painel da diretoria é exclusivo do dono, do RH e da diretoria." />
  if (isLoading) return <Spinner />
  if (isError || !data) return <Empty text={errMsg(error)} />

  const { people, today, time, requests, engagement, security, departments } = data
  const maxHead = Math.max(1, ...departments.map((d) => d.headcount))
  const alerts = [
    time.pending_adjustments > 0 && `${time.pending_adjustments} pedido(s) de ajuste de ponto aguardando`,
    time.pending_devices > 0 && `${time.pending_devices} dispositivo(s) de ponto aguardando aprovação`,
    time.people_with_heavy_debt > 0 && `${time.people_with_heavy_debt} pessoa(s) com banco de horas muito negativo (10h ou mais)`,
    security.privileged_without_2fa > 0 && `${security.privileged_without_2fa} perfil(is) privilegiado(s) sem verificação em duas etapas`,
  ].filter(Boolean) as string[]

  return (
    <div>
      <PageHeader title="Painel da diretoria" subtitle={`Visão geral da empresa. Atualizado às ${new Date(data.generated_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Sao_Paulo' })}.`} />

      {alerts.length > 0 && (
        <div className="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
          <p className="mb-1 flex items-center gap-2 font-semibold"><AlertTriangle className="h-4 w-4" /> Pontos de atenção</p>
          <ul className="list-disc space-y-0.5 pl-6">{alerts.map((a) => <li key={a}>{a}</li>)}</ul>
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        <Card title="Pessoas" Icon={Users}>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Stat label="Colaboradores" value={people.headcount} />
            <Stat label="Admissões (90 dias)" value={people.hires_90d} />
            <Stat label="Tempo médio de casa" value={people.avg_tenure_years === null ? '—' : `${people.avg_tenure_years} a`} />
            <Stat label="Aniversários no mês" value={people.birthdays_month} />
          </div>
        </Card>

        <Card title="Hoje" Icon={Clock}>
          <div className="grid grid-cols-3 gap-3">
            <Stat label="Marcaram ponto" value={today.present} />
            <Stat label="Trabalhando agora" value={today.working_now} tone="good" />
            <Stat label="De férias" value={today.on_vacation} />
          </div>
          {today.holiday && <p className="mt-3 text-sm text-gray-600"><Pill tone="gray">Feriado</Pill> {today.holiday}</p>}
        </Card>

        <Card title="Ponto e banco de horas" Icon={Hourglass}>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <Stat label="A receber (saldo positivo)" value={fmtMinutes(time.bank_to_receive_minutes)} tone="good" hint="soma da empresa" />
            <Stat label="A compensar (saldo negativo)" value={fmtMinutes(time.bank_to_compensate_minutes)} tone={time.bank_to_compensate_minutes > 0 ? 'warn' : 'default'} hint="soma da empresa" />
            <Stat label="Ajustes pendentes" value={time.pending_adjustments} tone={time.pending_adjustments ? 'warn' : 'default'} />
            <Stat label="Fora do local (7 dias)" value={time.outside_fence_7d} />
            <Stat label="Fora da rede (7 dias)" value={time.outside_network_7d} />
            <Stat label="Aparelhos novos (7 dias)" value={time.new_devices_7d} />
          </div>
        </Card>

        <Card title="Solicitações" Icon={ClipboardList}>
          <div className="grid grid-cols-3 gap-3">
            <Stat label="Pendentes" value={requests.pending} tone={requests.pending ? 'warn' : 'default'} />
            <Stat label="Tempo médio de resposta" value={requests.avg_decision_hours === null ? '—' : `${requests.avg_decision_hours} h`} hint="últimos 90 dias" />
            <Stat label="Taxa de aprovação" value={requests.approval_rate === null ? '—' : `${requests.approval_rate}%`} hint="últimos 90 dias" />
          </div>
          {Object.keys(requests.pending_by_type).length > 0 && (
            <p className="mt-3 flex flex-wrap gap-2 text-xs text-gray-600">{Object.entries(requests.pending_by_type).map(([t, n]) => <Pill key={t} tone="amber">{TYPE_LABEL[t] ?? t}: {n}</Pill>)}</p>
          )}
        </Card>

        <Card title="Comunicação (30 dias)" Icon={Newspaper}>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Stat label="Publicações" value={engagement.posts_30d} />
            <Stat label="Reações" value={engagement.reactions_30d} />
            <Stat label="Comentários" value={engagement.comments_30d} />
            <Stat label="Eventos próximos" value={engagement.upcoming_events} hint="30 dias" />
          </div>
          {engagement.recent_acks.length > 0 && (
            <div className="mt-3 space-y-2">
              <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400"><BadgeCheck className="h-3.5 w-3.5" /> Últimos comunicados com confirmação</p>
              {engagement.recent_acks.map((a, i) => (
                <div key={i}>
                  <div className="flex justify-between text-xs text-gray-600"><span className="truncate pr-2">{a.title}</span><span className="tabular-nums">{a.confirmed}/{a.total}</span></div>
                  <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100"><div className="h-full rounded-full bg-emerald-500" style={{ width: `${a.total ? Math.round((a.confirmed / a.total) * 100) : 0}%` }} /></div>
                </div>
              ))}
            </div>
          )}
        </Card>

        <Card title="Segurança" Icon={ShieldCheck}>
          <div className="grid grid-cols-3 gap-3">
            <Stat label="Com verificação em duas etapas" value={security.two_factor_adoption === null ? '—' : `${security.two_factor_adoption}%`} tone={(security.two_factor_adoption ?? 0) >= 80 ? 'good' : 'default'} />
            <Stat label="Privilegiados sem 2FA" value={security.privileged_without_2fa} tone={security.privileged_without_2fa ? 'warn' : 'good'} />
            <Stat label="2FA obrigatório" value={security.require_2fa ? 'Sim' : 'Não'} />
          </div>
        </Card>
      </div>

      <Card title="Por setor" Icon={CalendarDays}>
        <ul className="space-y-3">
          {departments.map((d) => (
            <li key={d.name}>
              <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                <span className="font-medium text-gray-900">{d.name}</span>
                <span className="text-xs text-gray-500">
                  {d.headcount} {d.headcount === 1 ? 'pessoa' : 'pessoas'}
                  {d.bank_minutes !== null
                    ? <> · banco <b className={d.bank_minutes < 0 ? 'text-red-600' : 'text-emerald-600'}>{fmtMinutes(d.bank_minutes)}</b></>
                    : <span className="text-gray-300"> · banco oculto (menos de {data.min_group} pessoas)</span>}
                </span>
              </div>
              <div className="mt-1 h-2 overflow-hidden rounded-full bg-gray-100"><div className="h-full rounded-full bg-violet-500" style={{ width: `${Math.round((d.headcount / maxHead) * 100)}%` }} /></div>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  )
}
