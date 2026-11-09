import { useEffect, useState } from 'react'
import { Camera, Info, MapPin, Receipt } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { TimeEntryItem } from '@/types/workspace.types'
import { TYPE_LABEL } from './timeUtils'
import { Modal, Pill, errMsg } from './ui'
import ReceiptModal from './ReceiptModal'

function PhotoModal({ slug, uuid, onClose }: { slug: string; uuid: string; onClose: () => void }) {
  const [url, setUrl] = useState<string | null>(null)
  const [error, setError] = useState('')

  useEffect(() => {
    let revoke: string | null = null
    let alive = true
    workspaceService.entryPhotoUrl(slug, uuid)
      .then((u) => { revoke = u; if (alive) setUrl(u) })
      .catch((e) => alive && setError(errMsg(e)))
    return () => { alive = false; if (revoke) URL.revokeObjectURL(revoke) }
  }, [slug, uuid])

  return (
    <Modal title="Foto da marcação" onClose={onClose}>
      {error ? <p className="text-sm text-red-600">{error}</p> : url ? <img src={url} alt="Foto tirada na marcação do ponto" className="w-full rounded-2xl" /> : <p className="py-10 text-center text-sm text-gray-400">Carregando…</p>}
      <p className="mt-3 text-[11px] text-gray-400">Visualizações por outras pessoas ficam registradas na auditoria.</p>
    </Modal>
  )
}

const SOURCE_LABEL: Record<string, string> = { web: 'Navegador', mobile: 'Celular', manual: 'Lançamento manual' }

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex gap-3 border-b border-gray-50 py-2 text-sm last:border-0">
      <dt className="w-32 flex-shrink-0 text-gray-500">{label}</dt>
      <dd className="min-w-0 flex-1 break-words text-gray-900">{children}</dd>
    </div>
  )
}

/** Tudo o que se sabe sobre uma marcação: hora, aparelho, rede, IP e local. */
function DetailsModal({ slug, entry, onClose }: { slug: string; entry: TimeEntryItem; onClose: () => void }) {
  const [receipt, setReceipt] = useState(false)
  const alerts = [
    entry.outside_fence && 'fora do local de trabalho',
    entry.outside_network && 'fora das redes autorizadas',
    entry.new_device && 'primeira marcação neste aparelho',
  ].filter(Boolean) as string[]

  return (
    <Modal title="Detalhes da marcação" onClose={onClose}>
      <dl>
        <Row label="Marcação">{TYPE_LABEL[entry.type]} às {entry.local_time ?? new Date(entry.punched_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Sao_Paulo' })}</Row>
        <Row label="Origem">{SOURCE_LABEL[entry.source] ?? entry.source}{entry.note ? ` — ${entry.note}` : ''}</Row>
        <Row label="Dispositivo">{entry.device_name ?? <span className="text-gray-400">Sem cadastro</span>}</Row>
        <Row label="Navegador">{entry.device_label ?? <span className="text-gray-400">—</span>}</Row>
        <Row label="IP">{entry.ip ?? <span className="text-gray-400">—</span>}</Row>
        <Row label="Rede">{entry.network_name ?? <span className="text-gray-400">{entry.outside_network ? 'Fora das redes autorizadas' : 'Sem rede cadastrada'}</span>}</Row>
        <Row label="Local">
          {entry.lat != null && entry.lng != null ? (
            <a className="text-brand-700 underline" target="_blank" rel="noopener noreferrer" href={`https://www.openstreetmap.org/?mlat=${entry.lat}&mlon=${entry.lng}#map=18/${entry.lat}/${entry.lng}`}>
              Ver no mapa{entry.accuracy_m ? ` (±${entry.accuracy_m} m)` : ''}
            </a>
          ) : <span className="text-gray-400">Não informado</span>}
        </Row>
      </dl>
      {alerts.length > 0 && (
        <p className="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">Atenção: {alerts.join('; ')}.</p>
      )}
      {entry.nsr != null && (
        <button type="button" onClick={() => setReceipt(true)} className="mt-3 inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
          <Receipt size={15} /> Ver comprovante (NSR {entry.nsr})
        </button>
      )}
      <p className="mt-3 text-[11px] text-gray-400">Dados visíveis apenas para a própria pessoa, o gestor e o RH.</p>
      {receipt && <ReceiptModal slug={slug} uuid={entry.uuid} onClose={() => setReceipt(false)} />}
    </Modal>
  )
}

/** Marcações de um dia com localização (mapa), foto, alertas e detalhes (aparelho, IP, rede). */
export default function EntryChips({ slug, entries }: { slug: string; entries: TimeEntryItem[] }) {
  const [photo, setPhoto] = useState<string | null>(null)
  const [details, setDetails] = useState<TimeEntryItem | null>(null)

  if (!entries.length) return <span className="text-gray-300">Sem marcações</span>

  return (
    <>
      <span className="inline-flex flex-wrap items-center gap-x-3 gap-y-1">
        {entries.map((e) => (
          <span key={e.uuid} className="inline-flex items-center gap-1">
            {e.local_time} {TYPE_LABEL[e.type].split(' ')[0].toLowerCase()}{e.source === 'manual' ? '*' : ''}
            {e.lat != null && e.lng != null && (
              <a
                href={`https://www.openstreetmap.org/?mlat=${e.lat}&mlon=${e.lng}#map=18/${e.lat}/${e.lng}`}
                target="_blank" rel="noopener noreferrer"
                className="text-gray-400 hover:text-brand-600" title={`Ver local${e.accuracy_m ? ` (±${e.accuracy_m} m)` : ''}`} aria-label="Ver local da marcação no mapa"
              >
                <MapPin className="h-3.5 w-3.5" />
              </a>
            )}
            {e.has_photo && (
              <button type="button" className="text-gray-400 hover:text-brand-600" title="Ver foto" aria-label="Ver foto da marcação" onClick={() => setPhoto(e.uuid)}>
                <Camera className="h-3.5 w-3.5" />
              </button>
            )}
            {(e.device_label || e.ip) && (
              <button type="button" className="text-gray-400 hover:text-brand-600" title="Detalhes: aparelho, IP e rede" aria-label="Ver detalhes da marcação" onClick={() => setDetails(e)}>
                <Info className="h-3.5 w-3.5" />
              </button>
            )}
            {e.outside_fence && <Pill tone="amber">fora do local</Pill>}
            {e.outside_network && <Pill tone="amber">fora da rede</Pill>}
            {e.new_device && <Pill tone="blue">aparelho novo</Pill>}
          </span>
        ))}
      </span>
      {photo && <PhotoModal slug={slug} uuid={photo} onClose={() => setPhoto(null)} />}
      {details && <DetailsModal slug={slug} entry={details} onClose={() => setDetails(null)} />}
    </>
  )
}
