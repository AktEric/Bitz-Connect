import { MapPin, Users, UserPlus } from 'lucide-react'
import { Avatar } from '@/components/ui/Avatar'

interface ProfileCardProps {
  uuid: string
  name: string
  avatarUrl?: string | null
  title?: string | null
  city?: string | null
  connectionsCount?: number
  onConnect?: () => void
}

/**
 * Card previewing a user's profile, shown inside HoverCard.
 * Requirements: 11.1
 */
export function ProfileCard({
  name,
  avatarUrl,
  title,
  city,
  connectionsCount,
  onConnect,
}: ProfileCardProps) {
  return (
    <div className="w-72 rounded-2xl border border-gray-100 bg-white p-5 shadow-lg">
      <div className="flex items-start gap-3">
        <Avatar src={avatarUrl} name={name} size="lg" />
        <div className="flex-1 min-w-0">
          <p className="font-semibold text-gray-900 truncate">{name}</p>
          {title && <p className="text-xs text-gray-500 truncate mt-0.5">{title}</p>}
          {city && (
            <p className="flex items-center gap-1 text-xs text-gray-400 mt-1">
              <MapPin className="h-3 w-3" />
              {city}
            </p>
          )}
        </div>
      </div>

      {connectionsCount !== undefined && (
        <div className="mt-3 flex items-center gap-1 text-xs text-gray-500">
          <Users className="h-3.5 w-3.5" />
          <span><strong className="text-gray-900">{connectionsCount}</strong> conexões</span>
        </div>
      )}

      {onConnect && (
        <button
          onClick={onConnect}
          className="mt-4 flex w-full items-center justify-center gap-1.5 rounded-full bg-violet-600 py-2 text-sm font-medium text-white hover:bg-violet-700 transition-colors"
        >
          <UserPlus className="h-4 w-4" />
          Conectar
        </button>
      )}
    </div>
  )
}
