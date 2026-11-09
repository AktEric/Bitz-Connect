import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Search, X } from 'lucide-react'
import api from '@/services/api'
import type { ApiResponse } from '@/types/api.types'
import { cn } from '@/utils/cn'
import { STICKERS } from './stickers'

export interface GifItem { id: string; title: string; url: string; preview: string }
export type RichPayload = { type: 'sticker'; metadata: { id: string } } | { type: 'gif'; metadata: { url: string; preview: string; title: string } }

function useGifs(q: string, enabled: boolean) {
  const [debounced, setDebounced] = useState(q)
  useEffect(() => { const t = setTimeout(() => setDebounced(q), 350); return () => clearTimeout(t) }, [q])
  return useQuery({
    queryKey: ['chat', 'gifs', debounced],
    enabled,
    staleTime: 10 * 60_000,
    queryFn: async () => (await api.get<ApiResponse<{ enabled: boolean; items: GifItem[] }>>('/chat/gifs', { params: { q: debounced || undefined } })).data.data,
  })
}

/** Figurinhas prontas e busca de GIFs (os GIFs só aparecem se o servidor tiver a chave da Giphy). */
export default function StickerGifPanel({ onPick, onClose }: { onPick: (p: RichPayload) => void; onClose: () => void }) {
  const [tab, setTab] = useState<'stickers' | 'gifs'>('stickers')
  const [q, setQ] = useState('')
  const gifs = useGifs(q, tab === 'gifs')

  return (
    <div className="absolute bottom-full left-2 right-2 z-10 mb-1 rounded-xl border border-gray-100 bg-white p-2 shadow-xl" role="dialog" aria-label="Figurinhas e GIFs">
      <div className="mb-2 flex items-center gap-1 border-b border-gray-100 pb-1.5">
        {([['stickers', 'Figurinhas'], ['gifs', 'GIFs']] as const).map(([v, label]) => (
          <button key={v} type="button" onClick={() => setTab(v)} className={cn('rounded-full px-3 py-1 text-sm font-medium', tab === v ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100')}>{label}</button>
        ))}
        <button type="button" onClick={onClose} className="ml-auto rounded-lg p-1 text-gray-400 hover:bg-gray-100" aria-label="Fechar"><X className="h-4 w-4" /></button>
      </div>

      {tab === 'stickers' ? (
        <div className="grid max-h-56 grid-cols-3 gap-2 overflow-y-auto">
          {STICKERS.map((s) => (
            <button key={s.id} type="button" onClick={() => onPick({ type: 'sticker', metadata: { id: s.id } })}
              className={cn('flex aspect-square flex-col items-center justify-center rounded-2xl bg-gradient-to-br p-1 transition hover:scale-105', s.bg)} aria-label={s.label}>
              <span className="text-3xl leading-none">{s.emoji}</span>
              <span className="mt-1 text-[10px] font-semibold text-gray-700">{s.label}</span>
            </button>
          ))}
        </div>
      ) : gifs.data && !gifs.data.enabled ? (
        <p className="px-2 py-6 text-center text-sm text-gray-500">Os GIFs ainda não estão ativados nesta empresa. O responsável pelo sistema precisa informar a chave da Giphy.</p>
      ) : (
        <>
          <div className="relative mb-2">
            <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar GIFs" className="w-full rounded-full border-0 bg-gray-100 py-2 pl-8 pr-3 text-sm focus:ring-1 focus:ring-brand-300" />
          </div>
          {gifs.isLoading ? <p className="py-6 text-center text-sm text-gray-400">Carregando…</p> : gifs.isError ? <p className="py-6 text-center text-sm text-red-600">Não foi possível buscar GIFs agora.</p> : (
            <div className="grid max-h-56 grid-cols-2 gap-1.5 overflow-y-auto">
              {gifs.data?.items.map((g) => (
                <button key={g.id} type="button" onClick={() => onPick({ type: 'gif', metadata: { url: g.url, preview: g.preview, title: g.title } })} className="overflow-hidden rounded-lg bg-gray-100 hover:opacity-90">
                  <img src={g.preview} alt={g.title} loading="lazy" className="h-24 w-full object-cover" />
                </button>
              ))}
              {gifs.data && !gifs.data.items.length && <p className="col-span-2 py-6 text-center text-sm text-gray-400">Nenhum GIF encontrado.</p>}
            </div>
          )}
          <p className="mt-1 text-right text-[10px] text-gray-400">Powered by GIPHY</p>
        </>
      )}
    </div>
  )
}
