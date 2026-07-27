import { useCallback, useEffect, useState } from 'react'
import { X, MapPin, ChevronLeft, ChevronRight } from 'lucide-react'
import { useToast } from '@/contexts/ToastContext'
import { useRegisterStoryView, useToggleStoryLike, useShareStory } from '@/hooks/useStories'
import type { StoryGroup } from '@/types/story.types'
import { StoryJobActions } from './StoryJobActions'
import { StoryMentorshipActions } from './StoryMentorshipActions'

const DURATION_MS = 10000
const TICK_MS = 100

interface StoryViewerProps {
  groups: StoryGroup[]
  initialIndex: number
  onClose: () => void
}

/**
 * Viewer de story em tela cheia (estilo Instagram): barras de progresso
 * segmentadas, avanço automático a cada 10s, pausa ao segurar.
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
export function StoryViewer({ groups, initialIndex, onClose }: StoryViewerProps) {
  const [ownerIndex, setOwnerIndex] = useState(initialIndex)
  const [storyIndex, setStoryIndex] = useState(0)
  const [progress, setProgress] = useState(0)
  const [paused, setPaused] = useState(false)
  const { success } = useToast()

  const registerView = useRegisterStoryView()
  const toggleLike = useToggleStoryLike()
  const shareStory = useShareStory()

  const group = groups[ownerIndex]
  const story = group?.stories[storyIndex]

  const [liked, setLiked] = useState(story?.liked_by_me ?? false)
  const [likesCount, setLikesCount] = useState(story?.likes_count ?? 0)

  useEffect(() => {
    setLiked(story?.liked_by_me ?? false)
    setLikesCount(story?.likes_count ?? 0)
  }, [story?.uuid, story?.liked_by_me, story?.likes_count])

  const goNextStory = useCallback(() => {
    if (!group) return
    if (storyIndex < group.stories.length - 1) {
      setStoryIndex((i) => i + 1)
    } else if (ownerIndex < groups.length - 1) {
      setOwnerIndex((i) => i + 1)
      setStoryIndex(0)
    } else {
      onClose()
    }
  }, [group, storyIndex, ownerIndex, groups.length, onClose])

  const goPrevStory = useCallback(() => {
    if (storyIndex > 0) {
      setStoryIndex((i) => i - 1)
    } else if (ownerIndex > 0) {
      setOwnerIndex((i) => i - 1)
      setStoryIndex(groups[ownerIndex - 1].stories.length - 1)
    }
  }, [storyIndex, ownerIndex, groups])

  // Reseta o progresso e registra visualização a cada troca de story
  useEffect(() => {
    setProgress(0)
    if (story) registerView.mutate(story.uuid)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [story?.uuid])

  // Avanço automático (10s por story)
  useEffect(() => {
    if (paused || !story) return
    const interval = setInterval(() => {
      setProgress((p) => Math.min(100, p + (TICK_MS / DURATION_MS) * 100))
    }, TICK_MS)
    return () => clearInterval(interval)
  }, [paused, story?.uuid])

  useEffect(() => {
    if (progress >= 100) goNextStory()
  }, [progress, goNextStory])

  // Pausa/retoma ao segurar em qualquer lugar da tela (evita ficar travado)
  useEffect(() => {
    const onDown = () => setPaused(true)
    const onUp = () => setPaused(false)
    window.addEventListener('pointerdown', onDown)
    window.addEventListener('pointerup', onUp)
    window.addEventListener('pointercancel', onUp)
    return () => {
      window.removeEventListener('pointerdown', onDown)
      window.removeEventListener('pointerup', onUp)
      window.removeEventListener('pointercancel', onUp)
    }
  }, [])

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
      if (e.key === 'ArrowRight') goNextStory()
      if (e.key === 'ArrowLeft') goPrevStory()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [goNextStory, goPrevStory, onClose])

  if (!group || !story) return null

  const canGoPrev = storyIndex > 0 || ownerIndex > 0

  const handleLike = () => {
    const next = !liked
    setLiked(next)
    setLikesCount((c) => c + (next ? 1 : -1))
    toggleLike.mutate(story.uuid, {
      onSuccess: (res) => {
        setLiked(res.liked)
        setLikesCount(res.likes_count)
      },
    })
  }

  const handleShare = () => {
    const url = story.story_type === 'job'
      ? `${window.location.origin}/jobs/${story.job?.uuid}`
      : `${window.location.origin}/mentorships/${story.mentorship?.uuid}`
    navigator.clipboard.writeText(url)
    shareStory.mutate(story.uuid)
    success('Link copiado!')
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/90">
      <div className="relative flex h-full w-full max-w-md flex-col overflow-hidden bg-gradient-to-br from-[#431BFF] to-[#7C3AED] sm:h-[92vh] sm:rounded-2xl">
        {story.image_url && (
          <>
            <img src={story.image_url} alt="" className="absolute inset-0 z-0 h-full w-full object-cover" />
            <div className="absolute inset-0 z-0 bg-gradient-to-b from-black/60 via-black/10 to-black/70" />
          </>
        )}

        {/* Barras de progresso */}
        <div className="absolute inset-x-3 top-3 z-20 flex gap-1">
          {group.stories.map((s, i) => (
            <div key={s.uuid} className="h-1 flex-1 overflow-hidden rounded-full bg-white/30">
              <div
                className="h-full bg-white"
                style={{
                  width: i < storyIndex ? '100%' : i === storyIndex ? `${progress}%` : '0%',
                  transition: i === storyIndex ? `width ${TICK_MS}ms linear` : undefined,
                }}
              />
            </div>
          ))}
        </div>

        {/* Cabeçalho */}
        <div className="absolute inset-x-3 top-7 z-20 flex items-center justify-between">
          <div className="flex items-center gap-2 text-white">
            {group.owner.avatar_url ? (
              <img src={group.owner.avatar_url} alt={group.owner.name} className="h-8 w-8 rounded-full object-cover" />
            ) : (
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-xs font-semibold">
                {group.owner.name.slice(0, 2).toUpperCase()}
              </div>
            )}
            <span className="text-sm font-semibold">{group.owner.name}</span>
          </div>
          <button onClick={onClose} className="rounded-full p-1.5 text-white/90 hover:bg-white/10" aria-label="Fechar">
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Zonas de toque (anterior/próximo) */}
        <button aria-label="Story anterior" className="absolute left-0 z-10 w-1/3" style={{ top: '4rem', bottom: '6.5rem' }} onClick={goPrevStory} />
        <button aria-label="Próximo story" className="absolute right-0 z-10 w-1/3" style={{ top: '4rem', bottom: '6.5rem' }} onClick={goNextStory} />

        {/* Setas de navegação (avançar/voltar sem esperar o timer) */}
        {canGoPrev && (
          <button
            onClick={goPrevStory}
            aria-label="Story anterior"
            className="absolute left-2 top-1/2 z-20 -translate-y-1/2 rounded-full bg-black/30 p-2 text-white backdrop-blur-sm transition-colors hover:bg-black/50"
          >
            <ChevronLeft className="h-5 w-5" />
          </button>
        )}
        <button
          onClick={goNextStory}
          aria-label="Próximo story"
          className="absolute right-2 top-1/2 z-20 -translate-y-1/2 rounded-full bg-black/30 p-2 text-white backdrop-blur-sm transition-colors hover:bg-black/50"
        >
          <ChevronRight className="h-5 w-5" />
        </button>

        {/* Conteúdo do card */}
        <div className="relative z-10 flex flex-1 flex-col items-center justify-center px-8 text-center text-white">
          {story.story_type === 'job' && story.job ? (
            <>
              <span className="mb-3 rounded-full bg-white/15 px-3 py-1 text-xs font-medium">Vaga em destaque</span>
              <h2 className="text-2xl font-bold leading-tight">{story.job.title}</h2>
              <p className="mt-2 text-sm text-white/80">{story.job.hotel.name}</p>
              {story.job.salary_range && <p className="mt-4 text-lg font-semibold">{story.job.salary_range}</p>}
              {(story.job.city || story.job.state) && (
                <p className="mt-1 flex items-center gap-1 text-xs text-white/70">
                  <MapPin className="h-3 w-3" />
                  {[story.job.city, story.job.state].filter(Boolean).join(', ')}
                </p>
              )}
            </>
          ) : story.mentorship ? (
            <>
              <span className="mb-3 rounded-full bg-white/15 px-3 py-1 text-xs font-medium">Mentoria em destaque</span>
              <h2 className="text-2xl font-bold leading-tight">{story.mentorship.title}</h2>
              {story.mentorship.mentor && <p className="mt-2 text-sm text-white/80">com {story.mentorship.mentor.name}</p>}
              <p className="mt-4 text-lg font-semibold">
                {story.mentorship.price_cents > 0
                  ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(story.mentorship.price_cents / 100)
                  : 'Gratuita'}
              </p>
            </>
          ) : null}
          {story.caption && <p className="mt-4 text-sm text-white/90">{story.caption}</p>}
        </div>

        {/* Ações */}
        <div className="relative z-20 px-4 pb-6">
          {story.story_type === 'job' && story.job ? (
            <StoryJobActions job={story.job} liked={liked} likesCount={likesCount} onLike={handleLike} onShare={handleShare} />
          ) : story.mentorship ? (
            <StoryMentorshipActions mentorship={story.mentorship} liked={liked} likesCount={likesCount} onLike={handleLike} onShare={handleShare} />
          ) : null}
        </div>
      </div>
    </div>
  )
}
