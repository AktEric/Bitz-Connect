import type { PlanTier } from './subscription.types'

/**
 * Tipos de avaliação polimórfica (§6/§11).
 */
export type ReviewableType = 'creator' | 'course' | 'mentorship'

export interface ReviewAuthor {
  uuid: string
  name: string
  avatar_url: string | null
  plan: PlanTier | null
  is_pro: boolean | null
}

export interface Review {
  uuid: string
  rating: number
  comment: string | null
  created_at: string
  author?: ReviewAuthor
}

export interface ReviewSummary {
  average: number
  count: number
}

export interface ReviewListResult {
  summary: ReviewSummary
  my_review: Review | null
  can_review: boolean
  reviews: Review[]
  meta: { current_page: number; last_page: number; total: number }
}

export interface CreateReviewData {
  rating: number
  comment?: string
}
