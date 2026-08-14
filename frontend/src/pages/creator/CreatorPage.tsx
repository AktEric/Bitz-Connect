import { useState } from 'react'
import { useParams, Link, useSearchParams } from 'react-router-dom'
import { Users, Video as VideoIcon, GraduationCap, Star, Handshake, X } from 'lucide-react'
import { useCreatorProfile, useFollowCreator, useUnfollowCreator } from '@/hooks/useCreator'
import { useCreatorVideos, useTrackView } from '@/hooks/useVideos'
import { useCreatorCourses } from '@/hooks/useCourses'
import { useMentorMentorships } from '@/hooks/useMentorships'
import { useAuth } from '@/hooks/useAuth'
import { Avatar } from '@/components/ui/Avatar'
import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { SkeletonCard } from '@/components/ui/Skeleton'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import { VideoCard } from '@/components/video/VideoCard'
import { VideoPlayer } from '@/components/video/VideoPlayer'
import { CourseCard } from '@/components/course/CourseCard'
import { MentorshipCard } from '@/components/mentorship/MentorshipCard'
import { ReviewList } from '@/components/review/ReviewList'
import { cn } from '@/utils/cn'
import type { Video } from '@/types/video.types'

type Tab = 'videos' | 'courses' | 'mentorships' | 'reviews'

const TABS: { id: Tab; label: string; Icon: React.ElementType }[] = [
  { id: 'videos',      label: 'Vídeos',     Icon: VideoIcon },
  { id: 'courses',     label: 'Cursos',     Icon: GraduationCap },
  { id: 'mentorships', label: 'Mentorias',  Icon: Handshake },
  { id: 'reviews',     label: 'Avaliações', Icon: Star },
]

/**
 * Página pública do criador (§6): foto, banner, selo PRO, especialidade,
 * descrição, seguidores, vídeos publicados, e abas de cursos/mentorias/
 * avaliações como estrutura preparada.
 * Sprint: monetization-hotelconnect-pro (Página do Criador)
 */
export default function CreatorPage() {
  const { uuid = '' } = useParams<{ uuid: string }>()
  const { user } = useAuth()
  const { data: creator, isLoading } = useCreatorProfile(uuid)
  const { data: videosRes, isLoading: loadingVideos } = useCreatorVideos(uuid)
  const { data: coursesRes, isLoading: loadingCourses } = useCreatorCourses(uuid)
  const { data: mentorshipsRes, isLoading: loadingMentorships } = useMentorMentorships(uuid)
  const follow = useFollowCreator(uuid)
  const unfollow = useUnfollowCreator(uuid)
  const trackView = useTrackView()

  const [searchParams] = useSearchParams()
  const initialTab = (searchParams.get('tab') as Tab) || 'videos'
  const [tab, setTab] = useState<Tab>(initialTab)
  const [active, setActive] = useState<Video | null>(null)

  if (isLoading) {
    return <div className="flex h-64 items-center justify-center"><div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" /></div>
  }

  if (!creator) return null

  // Só quem está no plano Consultor tem página de criador.
  if (!creator.is_creator) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-12">
        <EmptyState
          title="Este usuário ainda não é um criador PRO"
          description="A página de criador está disponível para assinantes do ConnectHotel PRO - Consultor."
          icon={<VideoIcon className="h-8 w-8" />}
          action={{ label: 'Ver perfil', onClick: () => { window.location.href = `/users/${creator.uuid}` } }}
        />
      </div>
    )
  }

  const isSelf = user?.uuid === creator.uuid
  const videos = videosRes?.data ?? []

  return (
    <div className="mx-auto max-w-4xl pb-10">
      {/* Banner */}
      <div className="relative h-40 w-full overflow-hidden rounded-b-xl bg-gradient-to-r from-violet-500 to-indigo-600 sm:h-52">
        {creator.banner_url && <img src={creator.banner_url} alt="" className="h-full w-full object-cover" />}
      </div>

      {/* Cabeçalho */}
      <div className="px-4">
        <div className="relative z-10 -mt-12 flex items-end justify-between">
          <div className="rounded-full ring-4 ring-white">
            <Avatar src={creator.avatar_url} name={creator.name} size="2xl" />
          </div>
          {!isSelf ? (
            creator.is_following ? (
              <Button variant="outline" loading={unfollow.isPending} onClick={() => unfollow.mutate()}>
                Seguindo
              </Button>
            ) : (
              <Button loading={follow.isPending} onClick={() => follow.mutate()}>
                Seguir
              </Button>
            )
          ) : (
            <Link to="/profile/edit"><Button variant="outline">Editar perfil</Button></Link>
          )}
        </div>

        <div className="mt-3">
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold text-gray-900">{creator.name}</h1>
            <PremiumBadge plan={creator.plan} />
          </div>
          {creator.specialty && <p className="mt-0.5 text-sm font-medium text-primary">{creator.specialty}</p>}
          {creator.bio && <p className="mt-2 text-sm text-gray-600 leading-relaxed">{creator.bio}</p>}

          <div className="mt-3 flex items-center gap-4 text-sm text-gray-500">
            <span className="flex items-center gap-1"><Users className="h-4 w-4" />{creator.followers_count ?? 0} seguidores</span>
            <span className="flex items-center gap-1"><VideoIcon className="h-4 w-4" />{creator.videos_count ?? 0} vídeos</span>
            {!!creator.reviews_count && (
              <span className="flex items-center gap-1"><Star className="h-4 w-4 fill-amber-400 text-amber-400" />{creator.rating_avg} ({creator.reviews_count})</span>
            )}
          </div>
        </div>

        {/* Abas */}
        <div className="mt-5 flex gap-1 border-b border-gray-200">
          {TABS.map(({ id, label, Icon }) => (
            <button
              key={id}
              onClick={() => setTab(id)}
              className={cn(
                'flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors',
                tab === id ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700',
              )}
            >
              <Icon className="h-4 w-4" /> {label}
            </button>
          ))}
        </div>

        {/* Conteúdo das abas */}
        <div className="mt-5">
          {tab === 'videos' && (
            loadingVideos ? (
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"><SkeletonCard /><SkeletonCard /><SkeletonCard /></div>
            ) : videos.length === 0 ? (
              <EmptyState title="Nenhum vídeo publicado" description="Os vídeos deste criador aparecerão aqui." icon={<VideoIcon className="h-8 w-8" />} />
            ) : (
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {videos.map((v) => <VideoCard key={v.uuid} video={v} onOpen={setActive} />)}
              </div>
            )
          )}

          {tab === 'courses' && (
            loadingCourses ? (
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"><SkeletonCard /><SkeletonCard /></div>
            ) : (coursesRes?.data ?? []).length === 0 ? (
              <EmptyState title="Nenhum curso publicado" description="Os cursos deste criador aparecerão aqui." icon={<GraduationCap className="h-8 w-8" />} />
            ) : (
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {(coursesRes?.data ?? []).map((c) => <CourseCard key={c.uuid} course={c} />)}
              </div>
            )
          )}
          {tab === 'mentorships' && (
            loadingMentorships ? (
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"><SkeletonCard /><SkeletonCard /></div>
            ) : (mentorshipsRes?.data ?? []).length === 0 ? (
              <EmptyState title="Nenhuma mentoria publicada" description="As mentorias deste criador aparecerão aqui." icon={<Handshake className="h-8 w-8" />} />
            ) : (
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {(mentorshipsRes?.data ?? []).map((m) => <MentorshipCard key={m.uuid} mentorship={m} />)}
              </div>
            )
          )}
          {tab === 'reviews' && <ReviewList type="creator" uuid={creator.uuid} />}
        </div>
      </div>

      {/* Player modal */}
      {active && (
        <>
          <div className="fixed inset-0 z-50 bg-black/70" onClick={() => setActive(null)} aria-hidden="true" />
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="relative w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
              <button onClick={() => setActive(null)} aria-label="Fechar" className="absolute right-3 top-3 z-10 rounded-full bg-black/50 p-1.5 text-white hover:bg-black/70">
                <X className="h-4 w-4" />
              </button>
              {active.video_url && (
                <VideoPlayer src={active.video_url} poster={active.thumbnail_url} autoPlay onFirstPlay={() => trackView.mutate(active.uuid)} className="aspect-video w-full bg-black" />
              )}
              <div className="p-4">
                <h2 className="text-lg font-semibold text-gray-900">{active.title}</h2>
                {active.description && <p className="mt-2 whitespace-pre-line text-sm text-gray-600">{active.description}</p>}
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
