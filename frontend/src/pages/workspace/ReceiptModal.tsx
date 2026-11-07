import { useQuery } from '@tanstack/react-query'
import { Printer } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { TYPE_LABEL } from './timeUtils'
import { Modal, btnGhost, errMsg } from './ui'

const SOURCE_LABEL: Record<string, string> = { web: 'Navegador', mobile: 'Celular', manual: 'Lançamento manual' }

/** Comprovante de marcação de ponto, pronto para imprimir ou salvar em PDF. */
export default function ReceiptModal({ slug, uuid, onClose }: { slug: string; uuid: string; onClose: () => void }) {
  const { data, error, isLoading } = useQuery({ queryKey: ['ws', slug, 'receipt', uuid], queryFn: () => workspaceService.timeReceipt(slug, uuid) })

  return (
    <Modal title="Comprovante de marcação" onClose={onClose}>
      {isLoading && <p className="py-8 text-center text-sm text-gray-400">Carregando…</p>}
      {error && <p className="text-sm text-red-600">{errMsg(error)}</p>}
      {data && (
        <>
          <div className="print-area rounded-xl border border-gray-200 p-4 text-sm">
            <p className="text-base font-semibold text-gray-900">{data.company.name}</p>
            {data.company.cnpj && <p className="text-gray-500">CNPJ {data.company.cnpj}</p>}
            <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-400">Comprovante de registro de ponto</p>
            <dl className="mt-1">
              {([
                ['Colaborador', data.person.name ?? '—'],
                ['Marcação', TYPE_LABEL[data.type]],
                ['Data', data.local_date],
                ['Hora', `${data.local_time} (horário de Brasília)`],
                ['Origem', SOURCE_LABEL[data.source] ?? data.source],
                ['Dispositivo', data.device ?? '—'],
                ['Rede', data.network ?? '—'],
                ['NSR', data.nsr != null ? String(data.nsr) : '—'],
              ] as const).map(([k, v]) => (
                <div key={k} className="flex gap-3 border-b border-gray-100 py-1.5 last:border-0">
                  <dt className="w-28 flex-shrink-0 text-gray-500">{k}</dt>
                  <dd className="min-w-0 flex-1 break-words font-medium text-gray-900">{v}</dd>
                </div>
              ))}
            </dl>
            <p className="mt-3 text-xs text-gray-500">Código de verificação (SHA-256):</p>
            <p className="break-all font-mono text-[11px] text-gray-700">{data.hash ?? '—'}</p>
            <p className="mt-3 text-[11px] text-gray-400">Registro encadeado: qualquer alteração posterior no banco é detectada pela verificação de integridade. Este comprovante é gerencial e não substitui o REP/AFD exigido pela Portaria 671.</p>
          </div>
          <div className="no-print mt-4 flex justify-end">
            <button type="button" className={btnGhost} onClick={() => window.print()}><Printer size={15} /> Imprimir / salvar PDF</button>
          </div>
        </>
      )}
    </Modal>
  )
}
