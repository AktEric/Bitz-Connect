import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Heart, MessageCircle, Share2, GraduationCap, Volume2, VolumeX, Play } from 'lucide-react'
import { Avatar } from '@/components/ui/Avatar'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import { CommentsSheet } from './CommentsSheet'
import { useToggleLike, useShareVideo, useTrackView } from '@/hooks/useVideos'
import { useToast } from '@/contexts/ToastContext'
import { cn } from '@/utils/cn'
import type { Video } from '@/types/video.types'

interface VideoReelProps {
  video: Video
  /** True quando este reel é o que está em foco no feed (controla autoplay). */
  isActive: boolean
  muted: boolean
  onToggleMute: () => void
}

function formatCount(n: number): string {
  if (n >= 1000) return `${(n / 1000).toFixed(1).replace('.0', '')} mil`
  return String(n)
}

/**
 * Reel em tela cheia estilo TikTok/Instagram: vídeo de até 30s (divulgação),
 * bolinha do criador clicável (→ perfil), ações laterais (curtir/comentar/
 * compartilhar) e CTA "Acessar cursos".
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
export function VideoReel({ video, isActive, muted, onToggleMute }: VideoReelProps) {
  const videoRef = useRef<HTMLVideoElement>(null)
  const viewed = useRef(false)

  const toggleLike = useToggleLike()
  const shareVideo = useShareVideo()
  const trackView = useTrackView()
  const { success } = useToast()
  const navigate = useNavigate()

  const [liked, setLiked] = useState(!!video.liked_by_me)
  const [likes, setLikes] = useState(video.likes_count)
  const [comments, setComments] = useState(video.comments_count)
  const [shares, setShares] = useState(video.shares_count)
  const [showComments, setShowComments] = useState(false)
  const [paused, setPaused] = useState(false)

  // Autoplay do reel em foco; pausa os demais.
  useEffect(() => {
    const el = videoRef.current
    if (!el) return
    if (isActive) {
      el.play().then(() => setPaused(false)).catch(() => {/* autoplay bloqueado */})
      if (!viewed.current) {
        viewed.current = true
        trackView.mutate(video.uuid)
      }
    } else {
      el.pause()
      el.currentTime = 0
    }
  }, [isActive, video.uuid, trackView])

  function togglePlay() {
    const el = videoRef.current
    if (!el) return
    if (el.paused) { el.play(); setPaused(false) } else { el.pause(); setPaused(true) }
  }

  function handleLike() {
    // Otimista: reflete imediatamente e reconcilia com a resposta.
    const next = !liked
    setLiked(next)
    setLikes((n) => n + (next ? 1 : -1))
    toggleLike.mutate(video.uuid, {
      onSuccess: (res) => { setLiked(res.liked); setLikes(res.likes_count) },
      onError: () => { setLiked(!next); setLikes((n) => n + (next ? -1 : 1)) },
    })
  }

  function handleShare() {
    const url = `${window.location.origin}/videos/${video.uuid}`
    navigator.clipboard?.writeText(url).catch(() => {})
    setShares((n) => n + 1)
    shareVideo.mutate(video.uuid, {
      onSuccess: (res) => setShares(res.shares_count),
      onError: () => setShares((n) => n - 1),
    })
    success('Link copiado para compartilhar.')
  }

  return (
    <section className="relative flex h-full w-full snap-start items-center justify-center bg-black">
      {/* Vídeo */}
      {video.video_url && (
        <video
          ref={videoRef}
          src={video.video_url}
          poster={video.thumbnail_url ?? undefined}
          loop
          muted={muted}
          playsInline
          onClick={togglePlay}
          className="h-full max-h-full w-auto max-w-full object-contain"
        />
      )}

      {/* Ícone de play quando pausado manualmente */}
      {paused && (
        <button onClick={togglePlay} className="absolute inset-0 flex items-center justify-center" aria-label="Reproduzir">
          <span className="rounded-full bg-black/40 p-4"><Play className="h-8 w-8 text-white" /></span>
        </button>
      )}

      {/* Mute/unmute */}
      <button
        onClick={onToggleMute}
        aria-label={muted ? 'Ativar som' : 'Silenciar'}
        className="absolute right-3 top-3 rounded-full bg-black/40 p-2 text-white hover:bg-black/60"
      >
        {muted ? <VolumeX className="h-4 w-4" /> : <Volume2 className="h-4 w-4" />}
      </button>

      {/* Info do criador + descrição (canto inferior esquerdo) */}
      <div className="absolute bottom-4 left-4 right-20 text-white">
        <Link to={`/creators/${video.creator.uuid}`} className="flex items-center gap-2">
          <Avatar src={video.creator.avatar_url} name={video.creator.name} size="sm" ring />
          <span className="text-sm font-semibold drop-shadow">{video.creator.name}</span>
          <PremiumBadge plan={video.creator.plan} compact />
        </Link>
        <h2 className="mt-2 text-sm font-medium drop-shadow line-clamp-2">{video.title}</h2>
        {video.description && (
          <p className="mt-1 text-xs text-white/80 drop-shadow line-clamp-2">{video.description}</p>
        )}
        {video.tags.length > 0 && (
          <div className="mt-1 flex flex-wrap gap-1.5">
            {video.tags.map((t) => <span key={t} className="text-xs text-white/70">#{t}</span>)}
          </div>
        )}
      </div>

      {/* Coluna de ações (direita, estilo TikTok) */}
      <div className="absolute bottom-4 right-3 flex flex-col items-center gap-4">
        {/* Bolinha do criador clicável */}
        <Link to={`/creators/${video.creator.uuid}`} className="mb-1" aria-label={`Página de ${video.creator.name}`}>
          <Avatar src={video.creator.avatar_url} name={video.creator.name} size="md" ring />
        </Link>

        <ReelAction
          icon={<Heart className={cn('h-6 w-6', liked && 'fill-red-500 text-red-500')} />}
          label={formatCount(likes)}
          onClick={handleLike}
          ariaLabel="Curtir"
        />
        <ReelAction
          icon={<MessageCircle className="h-6 w-6" />}
          label={formatCount(comments)}
          onClick={() => setShowComments(true)}
          ariaLabel="Comentários"
        />
        <ReelAction
          icon={<Share2 className="h-6 w-6" />}
          label={formatCount(shares)}
          onClick={handleShare}
          ariaLabel="Compartilhar"
        />
        <ReelAction
          icon={<GraduationCap className="h-6 w-6" />}
          label="Cursos"
          onClick={() => navigate(`/creators/${video.creator.uuid}?tab=courses`)}
          ariaLabel="Acessar cursos"
        />
      </div>

      <CommentsSheet
        open={showComments}
        videoUuid={video.uuid}
        videoOwnerUuid={video.creator.uuid}
        onClose={() => setShowComments(false)}
        onAdded={() => setComments((n) => n + 1)}
        onDeleted={() => setComments((n) => Math.max(0, n - 1))}
      />
    </section>
  )
}

function ReelAction({ icon, label, onClick, ariaLabel }: {
  icon: React.ReactNode; label: string; onClick: () => void; ariaLabel: string
}) {
  return (
    <button onClick={onClick} aria-label={ariaLabel} className="flex flex-col items-center gap-1 text-white">
      <span className="rounded-full bg-black/30 p-2.5 backdrop-blur-sm transition-colors hover:bg-black/50">
        {icon}
      </span>
      <span className="text-[11px] font-medium drop-shadow">{label}</span>
    </button>
  )
}
