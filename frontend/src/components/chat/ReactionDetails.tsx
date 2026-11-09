import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { X } from 'lucide-react'
import api from '@/services/api'
import type { ApiResponse } from '@/types/api.types'
import { Avatar } from '@/components/ui/Avatar'

interface Row { person: { uuid: string; name: string; avatar_url: string | null }; emoji: string; reacted_at: string; mine: boolean }

/** "Hoje 14:35" ou "09/11 14:35". */
export function reactionTime(iso: string): string {
  const d = new Date(iso)
  const hm = d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
  return d.toDateString() === new Date().toDateString() ? `Hoje ${hm}` : `${d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })} ${hm}`
}

/**
 * Ao tocar numa reação: só quem reagiu, com o quê e a que horas (como no WhatsApp).
 * A sua própria reação aparece com "Toque para remover".
 */
export default function ReactionDetails({ messageUuid, onRemoveMine, onClose }: { messageUuid: string; onRemoveMine: () => void; onClose: () => void }) {
  const { data, isLoading } = useQuery({
    queryKey: ['chat', 'reaction-details', messageUuid],
    queryFn: async () => (await api.get<ApiResponse<{ items: Row[] }>>(`/chat/messages/${messageUuid}/reactions`)).data.data.items,
  })

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose() }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  return (
    <div className="fixed inset-0 z-[80] flex items-end justify-center bg-black/40 sm:items-center sm:p-4" onClick={onClose}>
      <div role="dialog" aria-modal="true" aria-label="Reações" className="max-h-[70vh] w-full overflow-y-auto rounded-t-2xl bg-white p-4 shadow-xl sm:max-w-xs sm:rounded-2xl" onClick={(e) => e.stopPropagation()}>
        <div className="mb-2 flex items-center justify-between">
          <h2 className="text-sm font-semibold text-gray-900">Reações</h2>
          <button type="button" onClick={onClose} className="rounded-lg p-1 text-gray-400 hover:bg-gray-100" aria-label="Fechar"><X className="h-4 w-4" /></button>
        </div>
        {isLoading ? <p className="py-6 text-center text-sm text-gray-400">Carregando…</p> : !data?.length ? <p className="py-6 text-center text-sm text-gray-400">Sem reações.</p> : (
          <ul className="divide-y divide-gray-100">
            {data.map((r) => {
              const body = (
                <>
                  <Avatar src={r.person.avatar_url} name={r.person.name} size="sm" />
                  <span className="min-w-0 flex-1 text-left">
                    <span className="block truncate text-sm font-medium text-gray-900">{r.mine ? 'Você' : r.person.name}</span>
                    <span className="block truncate text-xs text-gray-400">{r.mine ? 'Toque para remover · ' : ''}{reactionTime(r.reacted_at)}</span>
                  </span>
                  <span className="text-2xl leading-none">{r.emoji}</span>
                </>
              )
              return (
                <li key={r.person.uuid}>
                  {r.mine
                    ? <button type="button" onClick={() => { onRemoveMine(); onClose() }} className="flex w-full items-center gap-3 rounded-lg py-2 hover:bg-gray-50">{body}</button>
                    : <div className="flex items-center gap-3 py-2">{body}</div>}
                </li>
              )
            })}
          </ul>
        )}
      </div>
    </div>
  )
}
