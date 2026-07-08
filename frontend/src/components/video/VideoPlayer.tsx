import { useRef } from 'react'

interface VideoPlayerProps {
  src: string
  poster?: string | null
  /** Chamado uma única vez quando a reprodução começa (para registrar view). */
  onFirstPlay?: () => void
  className?: string
  autoPlay?: boolean
}

/**
 * Player HTML5 reutilizável. Dispara onFirstPlay apenas na primeira reprodução.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
export function VideoPlayer({ src, poster, onFirstPlay, className, autoPlay }: VideoPlayerProps) {
  const played = useRef(false)

  function handlePlay() {
    if (played.current) return
    played.current = true
    onFirstPlay?.()
  }

  return (
    <video
      src={src}
      poster={poster ?? undefined}
      controls
      autoPlay={autoPlay}
      onPlay={handlePlay}
      className={className ?? 'w-full rounded-xl bg-black'}
    >
      Seu navegador não suporta a reprodução de vídeo.
    </video>
  )
}
