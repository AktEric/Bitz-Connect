import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, Download, Lock, LockOpen } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { fmtMinutes } from './timeUtils'
import { Empty, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, fmtDate, fmtMonth } from './ui'

function Detail({ slug, month, isOwner }: { slug: string; month: string; isOwner: boolean }) {
  const qc = useQueryClient()
  const [error, setError] = useState('')
  const { data, isLoading, isError, error: loadError } = useQuery({
    queryKey: ['ws', slug, 'time', 'closing', month],
    queryFn: () => workspaceService.timeClosing(slug, month),
  })
  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['ws', slug, 'time', 'closing'] })
    qc.invalidateQueries({ queryKey: ['ws', slug, 'time', 'closings'] })
  }
  const close = useMutation({ mutationFn: () => workspaceService.closeMonth(slug, month), onSuccess: () => { setError(''); refresh() }, onError: (e) => setError(errMsg(e)) })
  const reopen = useMutation({ mutationFn: () => workspaceService.reopenMonth(slug, month), onSuccess: () => { setError(''); refresh() }, onError: (e) => setError(errMsg(e)) })
  const [exporting, setExporting] = useState(false)

  if (isLoading) return <Spinner />
  if (isError || !data) return <Empty text={errMsg(loadError)} />

  const closed = data.status === 'closed'
  const pending = data.pending_adjustments ?? 0

  const doExport = async () => {
    setExporting(true)
    setError('')
    try { await workspaceService.exportClosing(slug, month) } catch (e) { setError(errMsg(e)) } finally { setExporting(false) }
  }

  return (
    <Panel className="p-0">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4">
        <div>
          <h2 className="flex items-center gap-2 font-semibold capitalize text-gray-900">
            {fmtMonth(month)} {closed ? <Pill tone="green">Fechado</Pill> : <Pill tone="amber">Em aberto</Pill>}
          </h2>
          {closed && <p className="text-xs text-gray-500">Fechado em {fmtDate(data.closed_at)}{data.closed_by ? ` por ${data.closed_by.name}` : ''}. Edições de ponto neste mês estão travadas.</p>}
        </div>
        <div className="flex flex-wrap gap-2">
          {closed ? (
            <>
              <button type="button" className={btnGhost} disabled={exporting} onClick={doExport}><Download className="h-4 w-4" /> Exportar CSV</button>
              {isOwner && (
                <button
                  type="button" className={btnGhost} disabled={reopen.isPending}
                  onClick={() => { if (window.confirm('Reabrir este mês libera edições de ponto e invalida o retrato enviado à folha. Continuar?')) reopen.mutate() }}
                >
                  <LockOpen className="h-4 w-4" /> Reabrir
                </button>
              )}
            </>
          ) : (
            <button
              type="button" className={btnPrimary} disabled={close.isPending || pending > 0}
              onClick={() => { if (window.confirm(`Fechar ${fmtMonth(month)}? As horas serão congeladas e o ponto do mês ficará travado.`)) close.mutate() }}
            >
              <Lock className="h-4 w-4" /> {close.isPending ? 'Fechando…' : 'Fechar mês'}
            </button>
          )}
        </div>
      </div>

      {pending > 0 && !closed && (
        <p className="flex items-center gap-2 border-b border-amber-100 bg-amber-50 px-4 py-2 text-sm text-amber-800">
          <AlertTriangle className="h-4 w-4" /> {pending} pedido(s) de ajuste pendente(s) neste mês. Aprove ou recuse em “Ponto do time” antes de fechar.
        </p>
      )}
      {error && <p className="border-b border-red-100 bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{error}</p>}

      <div className="overflow-x-auto">
        <table className="w-full min-w-[640px] text-sm">
          <thead className="bg-gray-50 text-left text-xs text-gray-500">
            <tr>
              <th className="px-4 py-2 font-medium">Pessoa</th>
              <th className="px-2 py-2 text-right font-medium">Dias</th>
              <th className="px-2 py-2 text-right font-medium">Faltas</th>
              <th className="px-2 py-2 text-right font-medium">Trabalhadas</th>
              <th className="px-2 py-2 text-right font-medium">Esperadas</th>
              <th className="px-2 py-2 text-right font-medium">Saldo do mês</th>
              <th className="px-4 py-2 text-right font-medium">Banco</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-50">
            {data.rows.map((r) => (
              <tr key={r.user_uuid}>
                <td className="px-4 py-2">
                  <span className="block font-medium text-gray-900">{r.name}</span>
                  <span className="block text-xs text-gray-400">{[r.job_title, r.department].filter(Boolean).join(' · ') || '—'}</span>
                </td>
                <td className="px-2 py-2 text-right tabular-nums">{r.days_worked}</td>
                <td className={`px-2 py-2 text-right tabular-nums ${r.absent_days ? 'font-semibold text-red-600' : 'text-gray-400'}`}>{r.absent_days}</td>
                <td className="px-2 py-2 text-right tabular-nums">{fmtMinutes(r.worked_minutes)}</td>
                <td className="px-2 py-2 text-right tabular-nums text-gray-500">{fmtMinutes(r.expected_minutes)}</td>
                <td className={`px-2 py-2 text-right font-semibold tabular-nums ${r.balance_minutes < 0 ? 'text-red-600' : 'text-emerald-600'}`}>{fmtMinutes(r.balance_minutes)}</td>
                <td className={`px-4 py-2 text-right tabular-nums ${r.bank_minutes < 0 ? 'text-red-600' : 'text-emerald-600'}`}>{fmtMinutes(r.bank_minutes)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Panel>
  )
}

/** Fechamento mensal do ponto para a folha: escolha o mês, confira, feche e exporte. */
export default function ClosingView({ slug, isOwner }: { slug: string; isOwner: boolean }) {
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'time', 'closings'], queryFn: () => workspaceService.timeClosings(slug) })
  const [picked, setPicked] = useState<string | null>(null)

  if (isLoading || !data) return <Spinner />
  const month = picked ?? data.find((m) => m.status === 'open')?.month ?? data[0]?.month

  return (
    <div className="grid gap-5 lg:grid-cols-5">
      <Panel className="p-0 lg:col-span-1">
        <ul className="divide-y divide-gray-50">
          {data.map((m) => (
            <li key={m.month}>
              <button
                type="button" onClick={() => setPicked(m.month)}
                className={`flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm hover:bg-gray-50 ${month === m.month ? 'bg-violet-50/60' : ''}`}
              >
                <span className="capitalize text-gray-800">{fmtMonth(m.month)}</span>
                {m.status === 'closed' ? <Lock className="h-4 w-4 text-emerald-600" aria-label="Fechado" /> : <span className="h-2 w-2 rounded-full bg-amber-400" aria-label="Em aberto" />}
              </button>
            </li>
          ))}
        </ul>
      </Panel>
      <div className="lg:col-span-4">
        {month ? <Detail slug={slug} month={month} isOwner={isOwner} key={month} /> : <Empty text="Nenhum mês disponível." />}
      </div>
    </div>
  )
}
