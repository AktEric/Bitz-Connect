import { Link } from 'react-router-dom'
import { MapPin, Globe } from 'lucide-react'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import type { Hotel } from '@/types/hotel.types'

interface HotelCardProps {
  hotel: Hotel
}

/**
 * Compact hotel card for list views.
 * Requirements: 3.2
 */
export default function HotelCard({ hotel }: HotelCardProps) {
  return (
    <Link
      to={`/hotels/${hotel.slug}`}
      className="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-4 transition hover:shadow-md"
    >
      {/* Logo */}
      <div className="h-14 w-14 flex-shrink-0 overflow-hidden rounded-lg bg-primary/10">
        {hotel.logo_url ? (
          <img src={hotel.logo_url} alt={hotel.name} className="h-full w-full object-cover" />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-lg font-bold text-primary">
            {hotel.name.charAt(0).toUpperCase()}
          </div>
        )}
      </div>

      {/* Info */}
      <div className="min-w-0 flex-1">
        <h3 className="flex items-center gap-1.5 truncate font-semibold text-gray-900">
          <span className="truncate">{hotel.name}</span>
          <PremiumBadge plan={hotel.plan} compact />
        </h3>

        {hotel.description && (
          <p className="mt-0.5 line-clamp-1 text-sm text-gray-500">{hotel.description}</p>
        )}

        <div className="mt-1 flex flex-wrap gap-3 text-xs text-gray-400">
          {(hotel.city || hotel.state) && (
            <span className="flex items-center gap-1">
              <MapPin className="h-3 w-3" />
              {[hotel.city, hotel.state].filter(Boolean).join(', ')}
            </span>
          )}
          {hotel.website && (
            <span className="flex items-center gap-1">
              <Globe className="h-3 w-3" />
              {hotel.website.replace(/^https?:\/\//, '')}
            </span>
          )}
        </div>
      </div>
    </Link>
  )
}
