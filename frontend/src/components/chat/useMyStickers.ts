import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import chatService from '@/services/chat.service'

export const stickerKeys = { mine: ['chat', 'stickers'] as const }

/** Coleção de figurinhas próprias: lista e conjunto de uuids (para saber se já salvei uma recebida). */
export function useMyStickersList() {
  return useQuery({ queryKey: stickerKeys.mine, queryFn: () => chatService.myStickers(), staleTime: 60_000 })
}

export function useMyStickers(): Set<string> {
  const { data } = useMyStickersList()
  return useMemo(() => new Set((data?.items ?? []).map((i) => i.uuid)), [data])
}
