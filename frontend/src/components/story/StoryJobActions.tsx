import { useNavigate } from 'react-router-dom'
import { MessageSquare, Send, Heart, Share2 } from 'lucide-react'
import type { Job } from '@/types/job.types'

interface StoryJobActionsProps {
  job: Job
  liked: boolean
  likesCount: number
  onLike: () => void
  onShare: () => void
}

/** Ações do story de vaga: mensagem, candidatar-se, curtir, compartilhar. */
export function StoryJobActions({ job, liked, likesCount, onLike, onShare }: StoryJobActionsProps) {
  const navigate = useNavigate()

  return (
    <div className="flex w-full items-center gap-2">
      <button
        onClick={() => navigate('/chat', { state: { openHotelSlug: job.hotel.slug } })}
        className="flex flex-1 items-center justify-center gap-1.5 rounded-full bg-white/15 px-3 py-2.5 text-sm font-medium text-white backdrop-blur-sm transition-colors hover:bg-white/25"
      >
        <MessageSquare className="h-4 w-4" /> Mensagem
      </button>
      <button
        onClick={() => navigate(`/jobs/${job.uuid}`)}
        className="flex flex-1 items-center justify-center gap-1.5 rounded-full bg-white px-3 py-2.5 text-sm font-semibold text-brand-700 transition-colors hover:bg-white/90"
      >
        <Send className="h-4 w-4" /> Candidatar-se
      </button>
      <button
        onClick={onLike}
        className="flex h-11 w-11 flex-shrink-0 flex-col items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-sm transition-colors hover:bg-white/25"
        aria-label="Curtir"
      >
        <Heart className={liked ? 'h-4 w-4 fill-red-500 text-red-500' : 'h-4 w-4'} />
        <span className="text-[9px] leading-none">{likesCount}</span>
      </button>
      <button
        onClick={onShare}
        className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-sm transition-colors hover:bg-white/25"
        aria-label="Compartilhar"
      >
        <Share2 className="h-5 w-5" />
      </button>
    </div>
  )
}
