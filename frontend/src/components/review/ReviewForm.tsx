import { useState } from 'react'
import { RatingStars } from './RatingStars'
import { Button } from '@/components/ui/Button'
import { useSubmitReview } from '@/hooks/useReviews'
import type { ReviewableType, Review } from '@/types/review.types'

interface ReviewFormProps {
  type: ReviewableType
  uuid: string
  existing?: Review | null
}

/**
 * Formulário de avaliação (criar ou editar a própria). Só deve ser renderizado
 * quando o backend confirma elegibilidade (can_review) — ver ReviewList.
 * Sprint: monetization-hotelconnect-pro (Avaliações)
 */
export function ReviewForm({ type, uuid, existing }: ReviewFormProps) {
  const submit = useSubmitReview(type, uuid)
  const [rating, setRating] = useState(existing?.rating ?? 0)
  const [comment, setComment] = useState(existing?.comment ?? '')

  return (
    <form
      onSubmit={(e) => { e.preventDefault(); if (rating > 0) submit.mutate({ rating, comment: comment.trim() || undefined }) }}
      className="rounded-xl border border-gray-100 bg-white p-4"
    >
      <p className="mb-2 text-sm font-medium text-gray-700">{existing ? 'Sua avaliação' : 'Deixe sua avaliação'}</p>
      <RatingStars value={rating} onChange={setRating} size="lg" />
      <textarea
        value={comment}
        onChange={(e) => setComment(e.target.value)}
        rows={2}
        maxLength={1000}
        placeholder="Conte como foi sua experiência (opcional)"
        className="mt-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
      />
      <div className="mt-2 flex justify-end">
        <Button type="submit" size="sm" loading={submit.isPending} disabled={rating === 0}>
          {existing ? 'Atualizar avaliação' : 'Enviar avaliação'}
        </Button>
      </div>
    </form>
  )
}
