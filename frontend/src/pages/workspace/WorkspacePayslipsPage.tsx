import { useRef, useState } from 'react'
import { useCan } from './permissions'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, FileText, Trash2, Upload } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { Payslip } from '@/types/workspace.types'
import { Empty, Modal, PageHeader, Panel, Pill, Spinner, Tabs, btnGhost, btnPrimary, errMsg, fmtMoney, fmtMonth, inputCls, labelCls, useWs } from './ui'

const KIND: Record<Payslip['kind'], string> = { payslip: 'Holerite', receipt: 'Comprovante', bonus: 'Bônus', other: 'Outro' }

function UploadModal({ slug, onClose }: { slug: string; onClose: () => void }) {
  const qc = useQueryClient()

  const fileRef = useRef<HTMLInputElement>(null)
  const { data: members } = useQuery({ queryKey: ['ws', slug, 'members'], queryFn: () => workspaceService.members(slug) })
  const [user, setUser] = useState('')
  const [kind, setKind] = useState<Payslip['kind']>('payslip')
  const [title, setTitle] = useState('')
  const [month, setMonth] = useState('')
  const [amount, setAmount] = useState('')
  const [error, setError] = useState('')

  const send = useMutation({
    mutationFn: () => {
      const file = fileRef.current?.files?.[0]
      if (!file) throw new Error('Selecione um arquivo.')
      const fd = new FormData()
      fd.append('user_uuid', user)
      fd.append('kind', kind)
      fd.append('title', title.trim())
      if (month) fd.append('reference_month', month)
      if (amount) fd.append('amount', amount.replace(',', '.'))
      fd.append('file', file)
      return workspaceService.uploadPayslip(slug, fd)
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'payslips'] }); onClose() },
    onError: (e) => setError(e instanceof Error && !('response' in e) ? e.message : errMsg(e)),
  })

  return (
    <Modal title="Anexar documento" onClose={onClose}>
      <div className="space-y-3">
        <div>
          <label className={labelCls}>Colaborador</label>
          <select className={inputCls} value={user} onChange={(e) => setUser(e.target.value)}>
            <option value="">Selecione…</option>
            {members?.map((m) => <option key={m.uuid} value={m.uuid}>{m.name}</option>)}
          </select>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className={labelCls}>Tipo</label>
            <select className={inputCls} value={kind} onChange={(e) => setKind(e.target.value as Payslip['kind'])}>
              {Object.entries(KIND).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
            </select>
          </div>
          <div><label className={labelCls}>Competência</label><input type="month" className={inputCls} value={month} onChange={(e) => setMonth(e.target.value)} /></div>
        </div>
        <div><label className={labelCls}>Título</label><input className={inputCls} value={title} onChange={(e) => setTitle(e.target.value)} maxLength={160} placeholder="Ex.: Holerite outubro/2026" /></div>
        <div><label className={labelCls}>Valor líquido (opcional)</label><input className={inputCls} inputMode="decimal" value={amount} onChange={(e) => setAmount(e.target.value)} placeholder="0,00" /></div>
        <div><label className={labelCls}>Arquivo (PDF, JPG ou PNG — até 10 MB)</label><input ref={fileRef} type="file" accept=".pdf,.jpg,.jpeg,.png" className="block w-full text-sm" /></div>
        <p className="text-xs text-gray-400">O documento fica em armazenamento privado: só o colaborador e o RH conseguem abrir.</p>
        {error && <p className="text-sm text-red-600">{error}</p>}
        <div className="flex justify-end gap-2">
          <button className={btnGhost} onClick={onClose}>Cancelar</button>
          <button className={btnPrimary} disabled={!user || !title.trim() || send.isPending} onClick={() => send.mutate()}>Anexar</button>
        </div>
      </div>
    </Modal>
  )
}

export default function WorkspacePayslipsPage() {
  const { slug } = useWs()
  const qc = useQueryClient()
  const isAdmin = useCan('payslips.manage')
  const [scope, setScope] = useState<'mine' | 'all'>('mine')
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['ws', slug, 'payslips', scope],
    queryFn: () => workspaceService.payslips(slug, { all: scope === 'all' }),
  })
  const remove = useMutation({
    mutationFn: (uuid: string) => workspaceService.deletePayslip(slug, uuid),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['ws', slug, 'payslips'] }),
  })

  const download = async (p: Payslip) => {
    setError('')
    try {
      await workspaceService.downloadPayslip(slug, p)
      qc.invalidateQueries({ queryKey: ['ws', slug, 'payslips'] })
      qc.invalidateQueries({ queryKey: ['workspace', slug] })
    } catch (e) {
      setError(errMsg(e))
    }
  }

  return (
    <div>
      <PageHeader
        title="Recebimentos"
        subtitle="Holerites, comprovantes e bônus — documentos privados."
        actions={isAdmin ? <button className={btnPrimary} onClick={() => setUploading(true)}><Upload className="h-4 w-4" /> Anexar</button> : undefined}
      />
      {isAdmin && <Tabs value={scope} onChange={setScope} options={[{ value: 'mine', label: 'Meus' }, { value: 'all', label: 'Todos da empresa' }]} />}
      {error && <p className="mb-3 text-sm text-red-600">{error}</p>}

      {isLoading ? <Spinner /> : !data?.items.length ? <Empty text="Nenhum documento disponível." /> : (
        <div className="space-y-2">
          {data.items.map((p) => (
            <Panel key={p.uuid} className="flex items-center gap-3 !p-3">
              <FileText className="h-8 w-8 flex-shrink-0 text-brand-400" />
              <div className="min-w-0 flex-1">
                <p className="truncate font-medium text-gray-900">{p.title}</p>
                <p className="truncate text-xs text-gray-400">
                  {KIND[p.kind]} · {fmtMonth(p.reference_month)}{scope === 'all' && p.owner ? ` · ${p.owner.name}` : ''}{p.amount_cents != null ? ` · ${fmtMoney(p.amount_cents)}` : ''}
                </p>
              </div>
              {!p.viewed && scope === 'mine' && <Pill tone="brand">Novo</Pill>}
              {scope === 'all' && <Pill tone={p.viewed ? 'green' : 'gray'}>{p.viewed ? 'Visto' : 'Não visto'}</Pill>}
              <button className={btnGhost} onClick={() => download(p)}><Download className="h-4 w-4" /> Baixar</button>
              {isAdmin && (
                <button className="text-gray-400 hover:text-red-500" aria-label="Remover" onClick={() => { if (confirm('Remover este documento definitivamente?')) remove.mutate(p.uuid) }}><Trash2 className="h-4 w-4" /></button>
              )}
            </Panel>
          ))}
        </div>
      )}

      {uploading && <UploadModal slug={slug} onClose={() => setUploading(false)} />}
    </div>
  )
}
