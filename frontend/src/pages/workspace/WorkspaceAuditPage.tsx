import { useState } from 'react'
import { useInfiniteQuery } from '@tanstack/react-query'
import { Download, ScrollText } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import { Empty, PageHeader, Panel, Spinner, btnGhost, errMsg, inputCls, useWs } from './ui'

const ACTION_LABEL: Record<string, string> = {
  'payslip.created': 'Holerite anexado',
  'payslip.downloaded': 'Holerite baixado por outra pessoa',
  'payslip.deleted': 'Holerite removido',
  'request.decided': 'Solicitação analisada',
  'time.adjustment_decided': 'Ajuste de ponto analisado',
  'time.manual_entry': 'Marcação lançada manualmente',
  'time.bank_adjusted': 'Lançamento no banco de horas',
  'time.closed': 'Mês de ponto fechado',
  'time.reopened': 'Mês de ponto reaberto',
  'time.exported': 'Fechamento exportado',
  'time.settings_changed': 'Regras do ponto alteradas',
  'time.photo_viewed': 'Foto de ponto vista por outra pessoa',
  'time.device_approved': 'Dispositivo aprovado',
  'time.device_blocked': 'Dispositivo bloqueado',
  'time.device_pending': 'Dispositivo voltou para análise',
  'time.device_removed': 'Dispositivo removido',
  'role.changed': 'Papel de acesso alterado',
  'schedule.assigned': 'Jornada atribuída',
  'area.head_changed': 'Chefe de área alterado',
  'delegation.created': 'Substituição registrada',
  'delegation.removed': 'Substituição removida',
  'calendar.event_created': 'Evento criado',
  'calendar.event_deleted': 'Evento removido',
  'calendar.birthday_set': 'Data de nascimento cadastrada pelo RH',
  'security.policy_changed': 'Política de segurança alterada',
  'audit.exported': 'Auditoria exportada',
}
const label = (a: string) => ACTION_LABEL[a] ?? a

function detail(meta: Record<string, unknown> | null): string {
  if (!meta) return ''
  return Object.entries(meta)
    .filter(([, v]) => v !== null && v !== undefined && v !== '')
    .map(([k, v]) => `${k}: ${typeof v === 'object' ? JSON.stringify(v) : String(v)}`).join(' · ')
}

/** Trilha de auditoria da empresa: quem fez o quê, sobre quem, quando e de onde (dono e RH). */
export default function WorkspaceAuditPage() {
  const { slug } = useWs()
  const [action, setAction] = useState('')
  const [exporting, setExporting] = useState(false)
  const [error, setError] = useState('')

  const q = useInfiniteQuery({
    queryKey: ['ws', slug, 'audit', action],
    queryFn: ({ pageParam }) => workspaceService.audit(slug, { page: pageParam, action: action || undefined }),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.page < last.last_page ? last.page + 1 : undefined),
  })
  const items = q.data?.pages.flatMap((p) => p.items) ?? []
  const actions = q.data?.pages[0]?.actions ?? []

  const doExport = async () => {
    setExporting(true)
    setError('')
    try { await workspaceService.exportAudit(slug) } catch (e) { setError(errMsg(e)) } finally { setExporting(false) }
  }

  return (
    <div>
      <PageHeader
        title="Auditoria"
        subtitle="Registro das ações sensíveis de RH, ponto, acesso e segurança. Não pode ser editado nem apagado."
        actions={<button type="button" className={btnGhost} disabled={exporting} onClick={() => void doExport()}><Download className="h-4 w-4" /> {exporting ? 'Gerando…' : 'Exportar CSV'}</button>}
      />
      {error && <p className="mb-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{error}</p>}

      <div className="mb-3">
        <select className={`${inputCls} !w-auto min-w-[16rem]`} value={action} onChange={(e) => setAction(e.target.value)} aria-label="Filtrar por ação">
          <option value="">Todas as ações</option>
          {actions.map((a) => <option key={a} value={a}>{label(a)}</option>)}
        </select>
      </div>

      {q.isLoading ? <Spinner /> : q.isError ? <Empty text={errMsg(q.error)} /> : items.length === 0 ? <Empty text="Nenhum registro ainda." /> : (
        <Panel className="divide-y divide-gray-50 p-0">
          {items.map((l, i) => (
            <div key={`${l.created_at}-${i}`} className="flex flex-wrap items-start gap-3 px-4 py-3 text-sm">
              <ScrollText className="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-500" />
              <div className="min-w-0 flex-1">
                <p className="font-medium text-gray-900">{label(l.action)}</p>
                <p className="flex flex-wrap items-center gap-x-2 text-xs text-gray-500">
                  {l.actor && <span className="inline-flex items-center gap-1"><Avatar src={l.actor.avatar_url} name={l.actor.name} size="xs" /> {l.actor.name}</span>}
                  {l.target && <span>→ {l.target.name}</span>}
                  {l.ip && <span>· IP {l.ip}</span>}
                </p>
                {detail(l.meta) && <p className="mt-0.5 break-words text-xs text-gray-400">{detail(l.meta)}</p>}
              </div>
              <time className="text-xs text-gray-400" dateTime={l.created_at}>{new Date(l.created_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Sao_Paulo' })}</time>
            </div>
          ))}
        </Panel>
      )}
      {q.hasNextPage && <button type="button" className={`${btnGhost} mt-3 w-full`} disabled={q.isFetchingNextPage} onClick={() => void q.fetchNextPage()}>Carregar mais</button>}
    </div>
  )
}
