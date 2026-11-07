import { useEffect, useRef } from 'react'

interface GoogleId {
  initialize: (cfg: { client_id: string; callback: (r: { credential: string }) => void }) => void
  renderButton: (el: HTMLElement, opts: Record<string, unknown>) => void
}
declare global {
  interface Window {
    google?: { accounts: { id: GoogleId } }
  }
}

const SRC = 'https://accounts.google.com/gsi/client'

function loadScript(): Promise<void> {
  return new Promise((resolve, reject) => {
    if (window.google?.accounts?.id) return resolve()
    const existing = document.querySelector<HTMLScriptElement>(`script[src="${SRC}"]`)
    const s = existing ?? document.createElement('script')
    s.addEventListener('load', () => resolve(), { once: true })
    s.addEventListener('error', () => reject(new Error('Falha ao carregar o Google')), { once: true })
    if (!existing) {
      s.src = SRC
      s.async = true
      document.head.appendChild(s)
    }
  })
}

/** Botão oficial "Continuar com o Google" (Google Identity Services). */
export default function GoogleSignInButton({
  clientId,
  onCredential,
  onError,
}: {
  clientId: string
  onCredential: (credential: string) => void
  onError?: (message: string) => void
}) {
  const ref = useRef<HTMLDivElement>(null)
  const cb = useRef(onCredential)
  cb.current = onCredential

  useEffect(() => {
    let cancelled = false
    loadScript()
      .then(() => {
        if (cancelled || !ref.current || !window.google) return
        window.google.accounts.id.initialize({ client_id: clientId, callback: (r) => cb.current(r.credential) })
        window.google.accounts.id.renderButton(ref.current, {
          theme: 'outline', size: 'large', text: 'continue_with', shape: 'rectangular', locale: 'pt-BR',
          width: Math.min(360, ref.current.parentElement?.clientWidth ?? 320),
        })
      })
      .catch(() => onError?.('Não foi possível carregar o login do Google.'))
    return () => {
      cancelled = true
    }
  }, [clientId, onError])

  return <div ref={ref} className="flex min-h-[44px] justify-center" />
}
