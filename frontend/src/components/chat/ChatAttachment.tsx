import { useEffect, useState } from 'react'
import { FileText, Loader2 } from 'lucide-react'
import api from '@/services/api'

export interface AttachmentMeta { uuid: string; name: string; size: number; kind: 'image' | 'audio' | 'file'; mime: string; duration?: number | null }

export function attachmentOf(type: string, metadata: unknown): AttachmentMeta | null {
  const m = metadata as Partial<AttachmentMeta> | null
  return (type === 'image' || type === 'audio' || type === 'file') && m?.uuid ? (m as AttachmentMeta) : null
}

const fmtSize = (n: number) => (n > 1_048_576 ? `${(n / 1_048_576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`)
const fmtDur = (s?: number | null) => (s ? `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}` : '')

/** Baixa o anexo com o token do usuário (o disco é privado) e devolve uma URL local para exibir. */
function useBlobUrl(uuid: string, enabled = true) {
  const [url, setUrl] = useState<string | null>(null)
  const [failed, setFailed] = useState(false)
  useEffect(() => {
    if (!enabled) return
    let revoked: string | null = null
    let alive = true
    api.get(`/chat/attachments/${uuid}`, { responseType: 'blob' })
      .then((r) => { if (alive) { revoked = URL.createObjectURL(r.data as Blob); setUrl(revoked) } })
      .catch(() => alive && setFailed(true))
    return () => { alive = false; if (revoked) URL.revokeObjectURL(revoked) }
  }, [uuid, enabled])
  return { url, failed }
}

/** Foto, mensagem de voz ou arquivo dentro do balão de conversa. */
export default function ChatAttachment({ a, mine }: { a: AttachmentMeta; mine: boolean }) {
  const { url, failed } = useBlobUrl(a.uuid, a.kind !== 'file')

  if (a.kind === 'image') {
    return failed ? <p className="text-xs opacity-70">Não foi possível carregar a foto.</p> : url
      ? <a href={url} target="_blank" rel="noopener noreferrer"><img src={url} alt={a.name} className="max-h-56 max-w-full rounded-xl object-cover" /></a>
      : <div className="flex h-24 w-36 items-center justify-center rounded-xl bg-black/10"><Loader2 className="h-4 w-4 animate-spin" /></div>
  }
  if (a.kind === 'audio') {
    return failed ? <p className="text-xs opacity-70">Não foi possível carregar o áudio.</p> : (
      <div className="flex items-center gap-2">
        {url ? <audio controls src={url} preload="metadata" className="h-9 w-52 max-w-full" /> : <Loader2 className="h-4 w-4 animate-spin" />}
        {a.duration ? <span className="text-[11px] opacity-70">{fmtDur(a.duration)}</span> : null}
      </div>
    )
  }
  const download = async () => {
    const r = await api.get(`/chat/attachments/${a.uuid}`, { responseType: 'blob' })
    const href = URL.createObjectURL(r.data as Blob)
    const el = document.createElement('a')
    el.href = href
    el.download = a.name
    el.click()
    setTimeout(() => URL.revokeObjectURL(href), 10_000)
  }
  return (
    <button type="button" onClick={download} className={`flex max-w-full items-center gap-2 rounded-xl px-2.5 py-2 text-left ${mine ? 'bg-white/15' : 'bg-gray-100'}`}>
      <FileText className="h-6 w-6 flex-shrink-0" />
      <span className="min-w-0"><span className="block truncate text-sm font-medium">{a.name}</span><span className="block text-[11px] opacity-70">{fmtSize(a.size)} · baixar</span></span>
    </button>
  )
}
