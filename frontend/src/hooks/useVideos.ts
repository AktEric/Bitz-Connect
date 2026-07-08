import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import videoService from '@/services/video.service'
import { useToast } from '@/contexts/ToastContext'
import { extractApiError } from '@/utils/api-error'
import type { CreateVideoData } from '@/types/video.types'

/**
 * Hooks de vídeo.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */

export const videoKeys = {
  all: ['videos'] as const,
  list: (params: { page?: number; category?: string }) => ['videos', 'list', params] as const,
  detail: (uuid: string) => ['videos', 'detail', uuid] as const,
  creator: (userUuid: string) => ['videos', 'creator', userUuid] as const,
  comments: (uuid: string) => ['videos', 'comments', uuid] as const,
}

/** Feed de vídeos (paginado, filtro por categoria). */
export function useVideos(params: { page?: number; category?: string } = {}) {
  return useQuery({
    queryKey: videoKeys.list(params),
    queryFn: () => videoService.getVideos(params),
  })
}

/** Detalhe de um vídeo. */
export function useVideo(uuid: string) {
  return useQuery({
    queryKey: videoKeys.detail(uuid),
    queryFn: () => videoService.getVideo(uuid).then((r) => r.data),
    enabled: !!uuid,
  })
}

/** Vídeos de um criador. */
export function useCreatorVideos(userUuid: string, page = 1) {
  return useQuery({
    queryKey: [...videoKeys.creator(userUuid), page],
    queryFn: () => videoService.getCreatorVideos(userUuid, page),
    enabled: !!userUuid,
  })
}

/** Publica um vídeo (Consultor PRO). */
export function useCreateVideo() {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: (data: CreateVideoData) => videoService.createVideo(data),
    onSuccess: () => {
      success('Vídeo publicado com sucesso.')
      qc.invalidateQueries({ queryKey: videoKeys.all })
    },
    onError: (err) => error(extractApiError(err)),
  })
}

/**
 * Registra uma visualização. Silencioso (sem toast) — é um efeito de fundo
 * disparado pelo player ao dar play.
 */
export function useTrackView() {
  return useMutation({
    mutationFn: (uuid: string) => videoService.registerView(uuid),
  })
}

/**
 * Alterna a curtida. Retorna { liked, likes_count } para o componente atualizar
 * seu estado local (evita recarregar o feed e perder a posição de scroll).
 */
export function useToggleLike() {
  const { error } = useToast()
  return useMutation({
    mutationFn: (uuid: string) => videoService.toggleLike(uuid).then((r) => r.data),
    onError: (err) => error(extractApiError(err)),
  })
}

/** Comentários de um vídeo (paginado). */
export function useVideoComments(uuid: string, enabled = true) {
  return useQuery({
    queryKey: videoKeys.comments(uuid),
    queryFn: () => videoService.getComments(uuid).then((r) => r),
    enabled: enabled && !!uuid,
  })
}

/** Adiciona um comentário. */
export function useAddComment(uuid: string) {
  const qc = useQueryClient()
  const { error } = useToast()
  return useMutation({
    mutationFn: (content: string) => videoService.addComment(uuid, content).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: videoKeys.comments(uuid) }),
    onError: (err) => error(extractApiError(err)),
  })
}

/** Remove um comentário. */
export function useDeleteComment(uuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (commentUuid: string) => videoService.deleteComment(uuid, commentUuid),
    onSuccess: () => {
      success('Comentário removido.')
      qc.invalidateQueries({ queryKey: videoKeys.comments(uuid) })
    },
    onError: (err) => error(extractApiError(err)),
  })
}

/** Compartilha (copia link + registra). */
export function useShareVideo() {
  return useMutation({
    mutationFn: (uuid: string) => videoService.share(uuid).then((r) => r.data),
  })
}
