import { useEffect, useState } from 'react'
import { Camera, MapPin } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { TimeEntryItem } from '@/types/workspace.types'
import { TYPE_LABEL } from './timeUtils'
import { Modal, Pill, errMsg } from './ui'

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

/** Marcações de um dia com localização (mapa), alerta de cerca e foto. */
export default function EntryChips({ slug, entries }: { slug: string; entries: TimeEntryItem[] }) {
  const [photo, setPhoto] = useState<string | null>(null)

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
                className="text-gray-400 hover:text-violet-600" title={`Ver local${e.accuracy_m ? ` (±${e.accuracy_m} m)` : ''}`} aria-label="Ver local da marcação no mapa"
              >
                <MapPin className="h-3.5 w-3.5" />
              </a>
            )}
            {e.has_photo && (
              <button type="button" className="text-gray-400 hover:text-violet-600" title="Ver foto" aria-label="Ver foto da marcação" onClick={() => setPhoto(e.uuid)}>
                <Camera className="h-3.5 w-3.5" />
              </button>
            )}
            {e.outside_fence && <Pill tone="amber">fora do local</Pill>}
          </span>
        ))}
      </span>
      {photo && <PhotoModal slug={slug} uuid={photo} onClose={() => setPhoto(null)} />}
    </>
  )
}
