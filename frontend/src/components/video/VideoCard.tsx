import { Play, Eye, Heart, MessageCircle } from 'lucide-react'
import { Avatar } from '@/components/ui/Avatar'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import { formatRelative } from '@/utils/format'
import type { Video } from '@/types/video.types'

interface VideoCardProps {
  video: Video
  onOpen?: (video: Video) => void
}

/** Formata segundos como m:ss. */
function formatDuration(seconds: number | null): string | null {
  if (!seconds || seconds <= 0) return null
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${s.toString().padStart(2, '0')}`
}

/** Formata contagem grande (1.2 mil). */
function formatCount(n: number): string {
  if (n >= 1000) return `${(n / 1000).toFixed(1).replace('.0', '')} mil`
  return String(n)
}

/**
 * Card de vídeo do feed (§4).
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
export function VideoCard({ video, onOpen }: VideoCardProps) {
  const duration = formatDuration(video.duration_seconds)

  return (
    <button
      type="button"
      onClick={() => onOpen?.(video)}
      className="group flex flex-col overflow-hidden rounded-xl border border-gray-100 bg-white text-left transition-shadow hover:shadow-md"
    >
      {/* Miniatura */}
      <div className="relative aspect-video w-full overflow-hidden bg-gray-900">
        {video.thumbnail_url ? (
          <img
            src={video.thumbnail_url}
            alt={video.title}
            className="h-full w-full object-cover"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-800 to-gray-900">
            <Play className="h-10 w-10 text-white/70" />
          </div>
        )}
        <div className="absolute inset-0 flex items-center justify-center bg-black/0 transition-colors group-hover:bg-black/20">
          <span className="rounded-full bg-white/90 p-2 opacity-0 shadow transition-opacity group-hover:opacity-100">
            <Play className="h-5 w-5 text-gray-900" />
          </span>
        </div>
        {duration && (
          <span className="absolute bottom-1.5 right-1.5 rounded bg-black/80 px-1.5 py-0.5 text-[11px] font-medium text-white">
            {duration}
          </span>
        )}
        {video.category && (
          <span className="absolute left-1.5 top-1.5 rounded bg-black/60 px-1.5 py-0.5 text-[10px] font-medium text-white">
            {video.category}
          </span>
        )}
      </div>

      {/* Info */}
      <div className="flex gap-3 p-3">
        <Avatar src={video.creator.avatar_url} name={video.creator.name} size="sm" />
        <div className="min-w-0 flex-1">
          <h3 className="line-clamp-2 text-sm font-semibold text-gray-900">{video.title}</h3>
          <div className="mt-0.5 flex items-center gap-1 text-xs text-gray-500">
            <span className="truncate">{video.creator.name}</span>
            <PremiumBadge plan={video.creator.plan} compact />
          </div>
          <div className="mt-1 flex items-center gap-3 text-[11px] text-gray-400">
            <span className="flex items-center gap-0.5"><Eye className="h-3 w-3" />{formatCount(video.views_count)}</span>
            <span className="flex items-center gap-0.5"><Heart className="h-3 w-3" />{formatCount(video.likes_count)}</span>
            <span className="flex items-center gap-0.5"><MessageCircle className="h-3 w-3" />{formatCount(video.comments_count)}</span>
            <span>· {formatRelative(video.created_at)}</span>
          </div>
        </div>
      </div>
    </button>
  )
}
