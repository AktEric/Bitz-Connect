import { MapPin, Users, Briefcase, UserPlus } from 'lucide-react'
import { Avatar } from '@/components/ui/Avatar'

interface CompanyCardProps {
  slug: string
  name: string
  logoUrl?: string | null
  segment?: string | null
  city?: string | null
  followersCount?: number
  jobsCount?: number
  onFollow?: () => void
}

/**
 * Card previewing a hotel's profile, shown inside HoverCard.
 * Requirements: 11.2
 */
export function CompanyCard({
  name,
  logoUrl,
  segment,
  city,
  followersCount,
  jobsCount,
  onFollow,
}: CompanyCardProps) {
  return (
    <div className="w-72 rounded-2xl border border-gray-100 bg-white p-5 shadow-lg">
      <div className="flex items-start gap-3">
        <Avatar src={logoUrl} name={name} size="lg" />
        <div className="flex-1 min-w-0">
          <p className="font-semibold text-gray-900 truncate">{name}</p>
          {segment && <p className="text-xs text-gray-500 truncate mt-0.5">{segment}</p>}
          {city && (
            <p className="flex items-center gap-1 text-xs text-gray-400 mt-1">
              <MapPin className="h-3 w-3" />
              {city}
            </p>
          )}
        </div>
      </div>

      <div className="mt-3 flex items-center gap-4 text-xs text-gray-500">
        {followersCount !== undefined && (
          <span className="flex items-center gap-1">
            <Users className="h-3.5 w-3.5" />
            <strong className="text-gray-900">{followersCount}</strong> seguidores
          </span>
        )}
        {jobsCount !== undefined && (
          <span className="flex items-center gap-1">
            <Briefcase className="h-3.5 w-3.5" />
            <strong className="text-gray-900">{jobsCount}</strong> vagas
          </span>
        )}
      </div>

      {onFollow && (
        <button
          onClick={onFollow}
          className="mt-4 flex w-full items-center justify-center gap-1.5 rounded-full bg-violet-600 py-2 text-sm font-medium text-white hover:bg-violet-700 transition-colors"
        >
          <UserPlus className="h-4 w-4" />
          Seguir
        </button>
      )}
    </div>
  )
}
