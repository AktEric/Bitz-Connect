import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import storyService from '@/services/story.service'
import { useToast } from '@/contexts/ToastContext'
import { extractApiError } from '@/utils/api-error'
import type { CreateStoryData } from '@/types/story.types'

/**
 * Hooks de story (destaque de vaga/mentoria — contas PRO).
 * Sprint: monetization-hotelconnect-pro (Stories)
 */

export const storyKeys = {
  all: ['stories'] as const,
}

/** Feed de stories ativos, agrupado por dono. */
export function useStories() {
  return useQuery({
    queryKey: storyKeys.all,
    queryFn: () => storyService.getStories().then((r) => r.data),
  })
}

/** Publica um story (gated publish_stories). */
export function useCreateStory() {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: (data: CreateStoryData) => storyService.createStory(data),
    onSuccess: () => {
      success('Story publicado com sucesso.')
      qc.invalidateQueries({ queryKey: storyKeys.all })
    },
    onError: (err) => error(extractApiError(err)),
  })
}

/** Remove um story (dono apenas). */
export function useDeleteStory() {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: (uuid: string) => storyService.deleteStory(uuid),
    onSuccess: () => {
      success('Story removido.')
      qc.invalidateQueries({ queryKey: storyKeys.all })
    },
    onError: (err) => error(extractApiError(err)),
  })
}

/** Registra uma visualização — silencioso, efeito de fundo do viewer. */
export function useRegisterStoryView() {
  return useMutation({
    mutationFn: (uuid: string) => storyService.registerView(uuid),
  })
}

/** Alterna a curtida. Retorna { liked, likes_count } para atualização otimista local. */
export function useToggleStoryLike() {
  const { error } = useToast()
  return useMutation({
    mutationFn: (uuid: string) => storyService.toggleLike(uuid).then((r) => r.data),
    onError: (err) => error(extractApiError(err)),
  })
}

/** Compartilha (copia link + registra). */
export function useShareStory() {
  return useMutation({
    mutationFn: (uuid: string) => storyService.share(uuid).then((r) => r.data),
  })
}
