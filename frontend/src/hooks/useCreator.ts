import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import creatorService from '@/services/creator.service'
import { useToast } from '@/contexts/ToastContext'
import { extractApiError } from '@/utils/api-error'
import { reviewKeys } from '@/hooks/useReviews'

/**
 * Hooks da página do criador (perfil + seguir/deixar de seguir).
 * Sprint: monetization-hotelconnect-pro (Página do Criador)
 */

export const creatorKeys = {
  profile: (uuid: string) => ['creator', 'profile', uuid] as const,
}

export function useCreatorProfile(uuid: string) {
  return useQuery({
    queryKey: creatorKeys.profile(uuid),
    queryFn: () => creatorService.getCreator(uuid).then((r) => r.data),
    enabled: !!uuid,
  })
}

export function useFollowCreator(uuid: string) {
  const qc = useQueryClient()
  const { error } = useToast()
  return useMutation({
    mutationFn: () => creatorService.follow(uuid).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: creatorKeys.profile(uuid) })
      // Seguir muda a elegibilidade para avaliar o criador (can_review).
      qc.invalidateQueries({ queryKey: reviewKeys.list('creator', uuid) })
    },
    onError: (err) => error(extractApiError(err)),
  })
}

export function useUnfollowCreator(uuid: string) {
  const qc = useQueryClient()
  const { error } = useToast()
  return useMutation({
    mutationFn: () => creatorService.unfollow(uuid).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: creatorKeys.profile(uuid) })
      qc.invalidateQueries({ queryKey: reviewKeys.list('creator', uuid) })
    },
    onError: (err) => error(extractApiError(err)),
  })
}
