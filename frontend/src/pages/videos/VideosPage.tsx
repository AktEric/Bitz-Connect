import { useEffect, useRef, useState } from 'react'
import { Video as VideoIcon, Plus } from 'lucide-react'
import { useVideos } from '@/hooks/useVideos'
import { useCan } from '@/hooks/useSubscription'
import { VideoReel } from '@/components/video/VideoReel'
import { VideoUploadModal } from '@/components/video/VideoUploadModal'
import { UpgradeModal } from '@/components/subscription/UpgradeModal'
import { EmptyState } from '@/components/ui/EmptyState'

/**
 * Feed de vídeos no formato Reels (§4/§5): rolagem vertical com snap, um vídeo
 * de até 30s por vez, autoplay do que está em foco. Upload é exclusivo de
 * Consultores PRO — usuários gratuitos veem o UpgradeModal (§13).
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
export default function VideosPage() {
  const { data, isLoading } = useVideos()
  const canPublish = useCan('publish_videos')

  const [uploadOpen, setUploadOpen] = useState(false)
  const [upgradeOpen, setUpgradeOpen] = useState(false)
  const [activeUuid, setActiveUuid] = useState<string | null>(null)
  const [muted, setMuted] = useState(true)

  const containerRef = useRef<HTMLDivElement>(null)
  const wrapperRef = useRef<HTMLDivElement>(null)
  const [height, setHeight] = useState<number | null>(null)
  const videos = data?.data ?? []

  // Calcula a altura disponível de verdade (viewport - offset real do
  // AppLayout), em vez de chutar um calc(100vh - Xrem) que se desalinha com o
  // padding do layout e faz a PÁGINA rolar em vez do carrossel interno.
  useEffect(() => {
    function measure() {
      if (!wrapperRef.current) return
      const top = wrapperRef.current.getBoundingClientRect().top
      // Desconta o padding inferior do <main> do AppLayout para não sobrar
      // nem alguns pixels de rolagem residual na página.
      const mainEl = wrapperRef.current.closest('main')
      const bottomPadding = mainEl ? parseFloat(getComputedStyle(mainEl).paddingBottom || '0') : 0
      setHeight(window.innerHeight - top - bottomPadding)
    }
    measure()
    window.addEventListener('resize', measure)
    return () => window.removeEventListener('resize', measure)
  }, [])

  // Detecta qual reel está em foco (>= 60% visível) para autoplay.
  useEffect(() => {
    const root = containerRef.current
    if (!root || videos.length === 0) return

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting && entry.intersectionRatio >= 0.6) {
            setActiveUuid((entry.target as HTMLElement).dataset.uuid ?? null)
          }
        }
      },
      { root, threshold: [0.6] },
    )

    const items = root.querySelectorAll('[data-reel]')
    items.forEach((el) => observer.observe(el))
    // Ativa o primeiro por padrão.
    if (!activeUuid && videos[0]) setActiveUuid(videos[0].uuid)

    return () => observer.disconnect()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [videos])

  function handlePublishClick() {
    if (canPublish) setUploadOpen(true)
    else setUpgradeOpen(true)
  }

  return (
    <div
      ref={wrapperRef}
      className="relative mx-auto max-w-md overflow-hidden"
      style={{ height: height ?? '80vh' }}
    >
      {/* Botão flutuante de publicar */}
      <button
        onClick={handlePublishClick}
        className="absolute right-4 top-4 z-20 flex items-center gap-1.5 rounded-full bg-primary px-3.5 py-2 text-sm font-medium text-white shadow-lg hover:bg-violet-700"
      >
        <Plus className="h-4 w-4" /> Publicar
      </button>

      {isLoading ? (
        <div className="flex h-full items-center justify-center">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
        </div>
      ) : videos.length === 0 ? (
        <div className="flex h-full items-center justify-center px-4">
          <EmptyState
            title="Nenhum vídeo por aqui ainda"
            description="Vídeos de até 30s publicados pelos Consultores PRO aparecerão neste feed."
            icon={<VideoIcon className="h-8 w-8" />}
            action={{ label: canPublish ? 'Publicar o primeiro' : 'Conhecer o PRO', onClick: handlePublishClick }}
          />
        </div>
      ) : (
        <div
          ref={containerRef}
          className="h-full snap-y snap-mandatory overflow-y-scroll rounded-xl"
          style={{ scrollbarWidth: 'none' }}
        >
          {videos.map((v) => (
            <div key={v.uuid} data-reel data-uuid={v.uuid} className="h-full w-full snap-start">
              <VideoReel
                video={v}
                isActive={activeUuid === v.uuid}
                muted={muted}
                onToggleMute={() => setMuted((m) => !m)}
              />
            </div>
          ))}
        </div>
      )}

      <VideoUploadModal open={uploadOpen} onClose={() => setUploadOpen(false)} />
      <UpgradeModal
        open={upgradeOpen}
        onClose={() => setUpgradeOpen(false)}
        title="Publique vídeos profissionais"
        description="A publicação de vídeos é exclusiva do HotelConnect PRO - Consultor."
      />
    </div>
  )
}
