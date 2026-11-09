import { Link } from 'react-router-dom'
import { Handshake, Clock, Users, Video, MapPin, Star } from 'lucide-react'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import type { Mentorship } from '@/types/mentorship.types'

interface MentorshipCardProps {
  mentorship: Mentorship
}

/**
 * Card de mentoria reutilizável (§12).
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
export function MentorshipCard({ mentorship }: MentorshipCardProps) {
  return (
    <Link
      to={`/mentorships/${mentorship.uuid}`}
      className="flex flex-col rounded-xl border border-gray-100 bg-white p-4 transition-shadow hover:shadow-md"
    >
      <div className="flex items-center gap-2 text-primary">
        <Handshake className="h-5 w-5" />
        {mentorship.category && <span className="rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-medium text-brand-700">{mentorship.category}</span>}
      </div>

      <h3 className="mt-2 line-clamp-2 text-sm font-semibold text-gray-900">{mentorship.title}</h3>
      {mentorship.description && <p className="mt-1 line-clamp-2 text-xs text-gray-500">{mentorship.description}</p>}

      {mentorship.mentor && (
        <div className="mt-2 flex items-center gap-1 text-xs text-gray-500">
          <span className="truncate">{mentorship.mentor.name}</span>
          <PremiumBadge plan={mentorship.mentor.plan} compact />
        </div>
      )}

      <div className="mt-3 flex items-center gap-3 text-[11px] text-gray-400">
        <span className="flex items-center gap-0.5"><Clock className="h-3 w-3" />{mentorship.duration_minutes} min</span>
        <span className="flex items-center gap-0.5">
          {mentorship.session_type === 'online' ? <Video className="h-3 w-3" /> : <MapPin className="h-3 w-3" />}
          {mentorship.session_type === 'online' ? 'Online' : 'Presencial'}
        </span>
        <span className="flex items-center gap-0.5"><Users className="h-3 w-3" />{mentorship.bookings_count}</span>
        {mentorship.reviews_count > 0 && (
          <span className="flex items-center gap-0.5"><Star className="h-3 w-3 fill-amber-400 text-amber-400" />{mentorship.rating_avg}</span>
        )}
      </div>

      <div className="mt-2 text-sm font-semibold text-gray-900">
        {mentorship.price_cents > 0
          ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(mentorship.price_cents / 100)
          : 'Gratuita'}
      </div>
    </Link>
  )
}
