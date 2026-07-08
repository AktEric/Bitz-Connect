import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import reviewService from '@/services/review.service'
import { useToast } from '@/contexts/ToastContext'
import { extractApiError } from '@/utils/api-error'
import type { ReviewableType, CreateReviewData } from '@/types/review.types'

/**
 * Hooks de avaliações (§6/§11).
 * Sprint: monetization-hotelconnect-pro (Avaliações)
 */
export const reviewKeys = {
  list: (type: ReviewableType, uuid: string) => ['reviews', type, uuid] as const,
}

export function useReviews(type: ReviewableType, uuid: string, enabled = true) {
  return useQuery({
    queryKey: reviewKeys.list(type, uuid),
    queryFn: () => reviewService.getReviews(type, uuid).then((r) => r.data),
    enabled: enabled && !!uuid,
  })
}

export function useSubmitReview(type: ReviewableType, uuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (data: CreateReviewData) => reviewService.submitReview(type, uuid, data).then((r) => r.data),
    onSuccess: () => {
      success('Avaliação registrada.')
      qc.invalidateQueries({ queryKey: reviewKeys.list(type, uuid) })
    },
    onError: (err) => error(extractApiError(err)),
  })
}
