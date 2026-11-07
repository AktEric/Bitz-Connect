import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { Hourglass, Plus } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { useTimeBank } from '@/hooks/useWorkspace'
import { fmtMinutes } from './timeUtils'
import { Modal, Panel, Spinner, btnGhost, btnPrimary, errMsg, fmtDate, inputCls, labelCls } from './ui'

function PostModal({ slug, userUuid, name, onClose }: { slug: string; userUuid: string; name: string; onClose: () => void }) {
  const qc = useQueryClient()
  const [sign, setSign] = useState<1 | -1>(1)
  const [hours, setHours] = useState('1')
  const [reason, setReason] = useState('')
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)

  const minutes = Math.round((parseFloat(hours.replace(',', '.')) || 0) * 60) * sign

  const save = async () => {
    setSaving(true)
    setError('')
    try {
      await workspaceService.postBankAdjustment(slug, { user_uuid: userUuid, minutes, reason: reason.trim() })
      await qc.invalidateQueries({ queryKey: ['ws', slug, 'time', 'bank'] })
      onClose()
    } catch (e) {
      setError(errMsg(e))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal title={`Lançar no banco de horas de ${name}`} onClose={onClose}>
      <div className="space-y-3">
        <div className="inline-flex rounded-xl bg-gray-100 p-1">
          {([[1, 'Crédito'], [-1, 'Débito']] as const).map(([v, label]) => (
            <button
              key={v} type="button" onClick={() => setSign(v)}
              className={`rounded-lg px-4 py-1.5 text-sm font-medium ${sign === v ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'}`}
            >
              {label}
            </button>
          ))}
        </div>
        <div>
          <label className={labelCls}>Horas (ex.: 1,5)</label>
          <input className={inputCls} inputMode="decimal" value={hours} onChange={(e) => setHours(e.target.value)} />
        </div>
        <div>
          <label className={labelCls}>Motivo</label>
          <input className={inputCls} value={reason} onChange={(e) => setReason(e.target.value)} maxLength={255} placeholder="Ex.: hora extra do evento, folga compensada" />
        </div>
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>Cancelar</button>
          <button type="button" className={btnPrimary} disabled={saving || !minutes || !reason.trim()} onClick={save}>
            {saving ? 'Salvando…' : `Lançar ${minutes >= 0 ? '+' : ''}${fmtMinutes(minutes)}`}
          </button>
        </div>
      </div>
    </Modal>
  )
}

/** Saldo acumulado do banco de horas, com a origem do saldo e os lançamentos do RH. */
export default function BankCard({ slug, userUuid, canPost }: { slug: string; userUuid?: string; canPost?: boolean }) {
  const { data, isLoading } = useTimeBank(slug, userUuid)
  const [posting, setPosting] = useState(false)

  if (isLoading || !data) return <Panel className="mt-5"><Spinner /></Panel>
  const negative = data.balance_minutes < 0

  return (
    <Panel className="mt-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="flex items-center gap-2 font-semibold text-gray-900"><Hourglass className="h-4 w-4 text-violet-500" /> Banco de horas{userUuid && data.user ? ` · ${data.user.name}` : ''}</h2>
          <p className={`mt-2 text-3xl font-bold tabular-nums ${negative ? 'text-red-600' : 'text-emerald-600'}`}>{data.balance_minutes > 0 ? '+' : ''}{fmtMinutes(data.balance_minutes)}</p>
          <p className="text-xs text-gray-500">{negative ? 'a compensar' : 'a receber em folga ou pagamento'}</p>
        </div>
        {canPost && userUuid && data.user && (
          <button type="button" className={btnGhost} onClick={() => setPosting(true)}><Plus className="h-4 w-4" /> Lançar</button>
        )}
      </div>

      <dl className="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
        <div className="rounded-xl bg-gray-50 p-2"><dt className="text-gray-500">Trabalhadas</dt><dd className="font-semibold text-gray-900">{fmtMinutes(data.worked_minutes)}</dd></div>
        <div className="rounded-xl bg-gray-50 p-2"><dt className="text-gray-500">Esperadas</dt><dd className="font-semibold text-gray-900">{fmtMinutes(data.expected_minutes)}</dd></div>
        <div className="rounded-xl bg-gray-50 p-2"><dt className="text-gray-500">Lançamentos</dt><dd className="font-semibold text-gray-900">{data.adjustments_minutes > 0 ? '+' : ''}{fmtMinutes(data.adjustments_minutes)}</dd></div>
      </dl>
      <p className="mt-2 text-[11px] text-gray-400">
        {data.since ? `Conta desde ${fmtDate(data.since)}, só dias encerrados; férias aprovadas não geram débito.` : 'O saldo começa a contar a partir da primeira marcação de ponto.'}
      </p>

      {data.adjustments.length > 0 && (
        <ul className="mt-3 divide-y divide-gray-50 border-t border-gray-100">
          {data.adjustments.slice(0, 5).map((a) => (
            <li key={a.uuid} className="flex items-center gap-3 py-2 text-sm">
              <span className="min-w-0 flex-1">
                <span className="block truncate text-gray-700">{a.reason}</span>
                <span className="block text-xs text-gray-400">{fmtDate(a.created_at)}{a.author ? ` · ${a.author.name}` : ''}</span>
              </span>
              <b className={a.minutes < 0 ? 'text-red-600' : 'text-emerald-600'}>{a.minutes > 0 ? '+' : ''}{fmtMinutes(a.minutes)}</b>
            </li>
          ))}
        </ul>
      )}

      {posting && userUuid && data.user && <PostModal slug={slug} userUuid={userUuid} name={data.user.name} onClose={() => setPosting(false)} />}
    </Panel>
  )
}
