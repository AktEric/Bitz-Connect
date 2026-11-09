import { useEffect, useState } from 'react'
import api from '@/services/api'

const cache = new Map<string, string>()

/** Baixa um arquivo privado com o token do usuário e devolve uma URL local (com cache, para figurinhas repetidas). */
export function useAuthBlob(path: string | null) {
  const [url, setUrl] = useState<string | null>(path ? cache.get(path) ?? null : null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    if (!path) return
    const hit = cache.get(path)
    if (hit) { setUrl(hit); return }
    let alive = true
    api.get(path, { responseType: 'blob' })
      .then((r) => { const u = URL.createObjectURL(r.data as Blob); cache.set(path, u); if (alive) setUrl(u) })
      .catch(() => alive && setFailed(true))
    return () => { alive = false }
  }, [path])

  return { url, failed }
}
