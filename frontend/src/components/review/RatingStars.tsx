import { useState } from 'react'
import { Star } from 'lucide-react'
import { cn } from '@/utils/cn'

interface RatingStarsProps {
  value: number
  size?: 'sm' | 'md' | 'lg'
  /** Se definido, as estrelas viram interativas (input de avaliação). */
  onChange?: (rating: number) => void
  className?: string
}

const SIZES = { sm: 'h-3.5 w-3.5', md: 'h-5 w-5', lg: 'h-6 w-6' }

/**
 * Estrelas de avaliação — modo exibição (readonly) ou input (§12).
 * Sprint: monetization-hotelconnect-pro (Avaliações)
 */
export function RatingStars({ value, size = 'md', onChange, className }: RatingStarsProps) {
  const [hover, setHover] = useState<number | null>(null)
  const interactive = !!onChange
  const display = hover ?? value

  return (
    <div className={cn('flex items-center gap-0.5', className)} role={interactive ? 'radiogroup' : undefined} aria-label="Avaliação">
      {[1, 2, 3, 4, 5].map((n) => (
        <button
          key={n}
          type="button"
          disabled={!interactive}
          onClick={() => onChange?.(n)}
          onMouseEnter={() => interactive && setHover(n)}
          onMouseLeave={() => interactive && setHover(null)}
          aria-label={`${n} estrela${n > 1 ? 's' : ''}`}
          className={cn(!interactive && 'cursor-default', interactive && 'cursor-pointer')}
        >
          <Star className={cn(SIZES[size], n <= display ? 'fill-amber-400 text-amber-400' : 'text-gray-200')} />
        </button>
      ))}
    </div>
  )
}
