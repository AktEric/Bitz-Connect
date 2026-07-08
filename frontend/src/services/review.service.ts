import api from './api'
import type { ApiResponse } from '@/types/api.types'
import type { ReviewableType, Review, ReviewListResult, CreateReviewData } from '@/types/review.types'

/**
 * Serviço de avaliações polimórficas (§6/§11).
 * Sprint: monetization-hotelconnect-pro (Avaliações)
 */
const reviewService = {
  /** GET /reviews/:type/:uuid — lista + resumo + minha avaliação + elegibilidade */
  async getReviews(type: ReviewableType, uuid: string): Promise<ApiResponse<ReviewListResult>> {
    const res = await api.get<ApiResponse<ReviewListResult>>(`/reviews/${type}/${uuid}`)
    return res.data
  },

  /** POST /reviews/:type/:uuid — cria ou atualiza minha avaliação */
  async submitReview(type: ReviewableType, uuid: string, data: CreateReviewData): Promise<ApiResponse<Review>> {
    const res = await api.post<ApiResponse<Review>>(`/reviews/${type}/${uuid}`, data)
    return res.data
  },
}

export default reviewService
