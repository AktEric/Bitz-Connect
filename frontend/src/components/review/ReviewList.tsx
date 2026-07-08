import { Link } from 'react-router-dom'
import { Star } from 'lucide-react'
import { useReviews } from '@/hooks/useReviews'
import { RatingStars } from './RatingStars'
import { ReviewForm } from './ReviewForm'
import { Avatar } from '@/components/ui/Avatar'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import { EmptyState } from '@/components/ui/EmptyState'
import { formatRelative } from '@/utils/format'
import type { ReviewableType } from '@/types/review.types'

interface ReviewListProps {
  type: ReviewableType
  uuid: string
}

/**
 * Bloco de avaliações reutilizável (§6/§11): resumo, formulário (quando
 * elegível) e a lista. A elegibilidade (can_review) vem sempre do backend.
 * Sprint: monetization-hotelconnect-pro (Avaliações)
 */
export function ReviewList({ type, uuid }: ReviewListProps) {
  const { data, isLoading } = useReviews(type, uuid)

  if (isLoading || !data) {
    return <p className="text-sm text-gray-400">Carregando avaliações…</p>
  }

  const { summary, my_review, can_review, reviews } = data

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <RatingStars value={Math.round(summary.average)} />
        <span className="text-sm font-medium text-gray-700">
          {summary.average > 0 ? summary.average.toFixed(1) : '—'}
        </span>
        <span className="text-xs text-gray-400">
          ({summary.count} avaliaç{summary.count === 1 ? 'ão' : 'ões'})
        </span>
      </div>

      {can_review && <ReviewForm type={type} uuid={uuid} existing={my_review} />}

      {reviews.length === 0 ? (
        <EmptyState title="Nenhuma avaliação ainda" description="Seja o primeiro a avaliar." icon={<Star className="h-7 w-7" />} />
      ) : (
        <div className="space-y-3">
          {reviews.map((r) => (
            <div key={r.uuid} className="rounded-xl border border-gray-100 bg-white p-3">
              <div className="flex items-start gap-2.5">
                {r.author && (
                  <Link to={`/users/${r.author.uuid}`} className="flex-shrink-0">
                    <Avatar src={r.author.avatar_url} name={r.author.name} size="sm" />
                  </Link>
                )}
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-1">
                    {r.author && (
                      <Link to={`/users/${r.author.uuid}`} className="text-sm font-medium text-gray-900 hover:underline">
                        {r.author.name}
                      </Link>
                    )}
                    <PremiumBadge plan={r.author?.plan} compact />
                    <span className="text-xs text-gray-400">· {formatRelative(r.created_at)}</span>
                  </div>
                  <RatingStars value={r.rating} size="sm" className="mt-0.5" />
                  {r.comment && <p className="mt-1 text-sm text-gray-600">{r.comment}</p>}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
