import { useEffect, useState } from 'react'
import { RefreshCw } from 'lucide-react'

const CHECK_MS = 60_000
const bundleOf = (html: string) => html.match(/\/assets\/index-[\w-]+\.js/)?.[0] ?? null

/**
 * Avisa quando há uma versão nova do app e deixa atualizar com um clique. Compara o arquivo principal carregado
 * agora com o que o servidor está entregando. Só em produção (no Vite de desenvolvimento a página já atualiza sozinha).
 */
export default function UpdateBanner() {
  const [stale, setStale] = useState(false)

  useEffect(() => {
    if (!import.meta.env.PROD) return
    const loaded = [...document.scripts].map((s) => new URL(s.src, location.href).pathname).find((p) => /\/assets\/index-[\w-]+\.js$/.test(p))
    if (!loaded) return

    const check = async () => {
      try {
        const res = await fetch('/', { cache: 'no-store', headers: { Accept: 'text/html' } })
        const latest = bundleOf(await res.text())
        if (latest && latest !== loaded) setStale(true)
      } catch { /* sem rede: tenta de novo depois */ }
    }
    const onVisible = () => { if (document.visibilityState === 'visible') void check() }
    const timer = setInterval(check, CHECK_MS)
    document.addEventListener('visibilitychange', onVisible)
    void check()

    return () => { clearInterval(timer); document.removeEventListener('visibilitychange', onVisible) }
  }, [])

  if (!stale) return null

  return (
    <div role="status" className="fixed inset-x-3 bottom-4 z-[90] mx-auto flex max-w-md items-center gap-3 rounded-2xl bg-gray-900 px-4 py-3 text-sm text-white shadow-2xl sm:bottom-6">
      <RefreshCw className="h-4 w-4 flex-shrink-0" aria-hidden />
      <span className="flex-1">Há uma versão nova do sistema.</span>
      <button type="button" onClick={() => location.reload()} className="rounded-lg bg-white px-3 py-1.5 font-semibold text-gray-900 hover:bg-gray-100">Atualizar</button>
    </div>
  )
}
