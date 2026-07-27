import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Briefcase, Building2, Video as VideoIcon, Calendar, MapPin } from 'lucide-react'
import { useFeed } from '@/hooks/useFeed'
import { useVideos } from '@/hooks/useVideos'
import { useJobs } from '@/hooks/useJobs'
import { useHotelDirectory } from '@/hooks/useHotel'
import PostForm from '@/components/feed/PostForm'
import { StoriesBar } from '@/components/story/StoriesBar'
import PostCard from '@/components/feed/PostCard'
import { VideoCard } from '@/components/video/VideoCard'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import { CompanyAvatar } from '@/components/common/CompanyAvatar'
import { Avatar } from '@/components/ui/Avatar'
import { EmptyState } from '@/components/ui/EmptyState'
import { SkeletonCard } from '@/components/ui/Skeleton'
import { SidebarLeft, SidebarRight } from '@/components/sidebar'
import type { Video } from '@/types/video.types'

// Categorias de conteúdo do feed (§10)
type FeedTab = 'todos' | 'publicacoes' | 'videos' | 'vagas' | 'hoteis' | 'eventos'

const TABS: { key: FeedTab; label: string }[] = [
  { key: 'todos',       label: 'Todos' },
  { key: 'publicacoes', label: 'Publicações' },
  { key: 'videos',      label: 'Vídeos' },
  { key: 'vagas',       label: 'Vagas' },
  { key: 'hoteis',      label: 'Hotéis' },
  { key: 'eventos',     label: 'Eventos' },
]

/**
 * Feed principal com filtros por categoria (§10) — layout de 3 colunas.
 * Cada aba é um subcomponente para que seu hook só dispare quando ativa.
 * Requirements: 4.1, 4.2
 */
export default function FeedPage() {
  const [tab, setTab] = useState<FeedTab>('todos')

  return (
    <div className="mx-auto max-w-6xl px-4 py-4">
      <div className="grid grid-cols-1 lg:grid-cols-[280px_1fr_280px] gap-4">
        <aside className="hidden lg:block"><SidebarLeft /></aside>

        <main className="min-w-0">
          {(tab === 'todos' || tab === 'vagas') && <StoriesBar />}

          {(tab === 'todos' || tab === 'publicacoes') && <PostForm />}

          {/* Filter tabs */}
          <div className="mt-3 flex items-center gap-1 overflow-x-auto rounded-xl border border-gray-100 bg-white px-3 shadow-sm">
            {TABS.map((t) => (
              <button
                key={t.key}
                onClick={() => setTab(t.key)}
                className={`whitespace-nowrap px-3 py-2.5 text-sm font-medium transition-colors border-b-2 ${
                  tab === t.key ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-800'
                }`}
              >
                {t.label}
              </button>
            ))}
          </div>

          <div className="mt-3">
            {(tab === 'todos' || tab === 'publicacoes') && <PostsTab onlyPosts={tab === 'publicacoes'} />}
            {tab === 'videos' && <VideosTab />}
            {tab === 'vagas' && <JobsTab />}
            {tab === 'hoteis' && <HotelsTab />}
            {tab === 'eventos' && (
              <EmptyState
                title="Eventos em breve"
                description="Os eventos do setor hoteleiro aparecerão aqui."
                icon={<Calendar className="h-8 w-8" />}
              />
            )}
          </div>
        </main>

        <aside className="hidden xl:block"><SidebarRight /></aside>
      </div>
    </div>
  )
}

// ── Publicações ────────────────────────────────────────────────────────────
function PostsTab({ onlyPosts }: { onlyPosts: boolean }) {
  const { posts, isLoading, isFetchingNextPage, hasNextPage, fetchNextPage, isError } = useFeed()
  const sentinelRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const el = sentinelRef.current
    if (!el) return
    const observer = new IntersectionObserver(
      (entries) => { if (entries[0].isIntersecting && hasNextPage && !isFetchingNextPage) fetchNextPage() },
      { threshold: 0.1 },
    )
    observer.observe(el)
    return () => observer.disconnect()
  }, [hasNextPage, isFetchingNextPage, fetchNextPage])

  // "Publicações" exclui compartilhamentos de vaga (filtro client-side).
  const visible = onlyPosts ? posts.filter((p) => p.type !== 'job_share') : posts

  if (isLoading) return <div className="space-y-4">{Array.from({ length: 3 }).map((_, i) => <SkeletonCard key={i} />)}</div>
  if (isError) return <div className="rounded-xl border border-red-100 bg-red-50 p-6 text-center text-sm text-red-500">Erro ao carregar o feed.</div>
  if (visible.length === 0) return <EmptyState title="Nenhuma publicação ainda" description="Seja o primeiro a compartilhar algo com a comunidade!" />

  return (
    <>
      <div className="space-y-4">
        {visible.map((post) => <PostCard key={post.uuid} post={post} />)}
      </div>
      <div ref={sentinelRef} className="mt-4 flex justify-center">
        {isFetchingNextPage ? (
          <span className="text-sm text-gray-400">Carregando mais...</span>
        ) : hasNextPage ? (
          <button onClick={() => fetchNextPage()} className="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Carregar mais</button>
        ) : (
          <p className="text-xs text-gray-400">Você chegou ao fim do feed.</p>
        )}
      </div>
    </>
  )
}

// ── Vídeos ───────────────────────────────────────────────────────────────────
function VideosTab() {
  const { data, isLoading } = useVideos()
  const navigate = useNavigate()
  const videos = data?.data ?? []

  if (isLoading) return <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">{Array.from({ length: 4 }).map((_, i) => <SkeletonCard key={i} />)}</div>
  if (videos.length === 0) return <EmptyState title="Nenhum vídeo ainda" description="Os vídeos dos Consultores PRO aparecerão aqui." icon={<VideoIcon className="h-8 w-8" />} action={{ label: 'Ir para Vídeos', onClick: () => navigate('/videos') }} />

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      {videos.map((v: Video) => <VideoCard key={v.uuid} video={v} onOpen={() => navigate('/videos')} />)}
    </div>
  )
}

// ── Vagas ────────────────────────────────────────────────────────────────────
function JobsTab() {
  const { data, isLoading } = useJobs()
  const jobs = data?.data ?? []

  if (isLoading) return <div className="space-y-3">{Array.from({ length: 4 }).map((_, i) => <SkeletonCard key={i} />)}</div>
  if (jobs.length === 0) return <EmptyState title="Nenhuma vaga disponível" description="Novas vagas aparecerão aqui." icon={<Briefcase className="h-8 w-8" />} />

  return (
    <div className="space-y-3">
      {jobs.map((job) => (
        <div key={job.uuid} className="relative flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 hover:shadow-md transition-shadow">
          {/* Avatar do hotel fica acima do link estendido, com destino próprio (perfil do hotel) */}
          <span className="relative z-10">
            <CompanyAvatar slug={job.hotel.slug} name={job.hotel.name} logoUrl={job.hotel.logo_url} size="md" />
          </span>
          <div className="min-w-0 flex-1">
            {/* Link estendido: cobre toda a linha, mas fica visualmente atrás do avatar */}
            <Link to={`/jobs/${job.uuid}`} className="absolute inset-0" aria-label={job.title} />
            <p className="truncate font-semibold text-gray-900">{job.title}</p>
            <p className="truncate text-sm text-gray-500">{job.hotel.name}</p>
            {(job.city || job.state) && (
              <span className="mt-0.5 flex items-center gap-1 text-xs text-gray-400"><MapPin className="h-3 w-3" />{[job.city, job.state].filter(Boolean).join(', ')}</span>
            )}
          </div>
        </div>
      ))}
    </div>
  )
}

// ── Hotéis ───────────────────────────────────────────────────────────────────
function HotelsTab() {
  const { data, isLoading } = useHotelDirectory()
  const hotels = data?.data ?? []

  if (isLoading) return <div className="space-y-3">{Array.from({ length: 4 }).map((_, i) => <SkeletonCard key={i} />)}</div>
  if (hotels.length === 0) return <EmptyState title="Nenhum hotel cadastrado" description="Os hotéis da plataforma aparecerão aqui." icon={<Building2 className="h-8 w-8" />} />

  return (
    <div className="space-y-3">
      {hotels.map((hotel) => (
        <Link key={hotel.slug} to={`/hotels/${hotel.slug}`} className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 hover:shadow-md transition-shadow">
          <Avatar src={hotel.logo_url} name={hotel.name} size="md" />
          <div className="min-w-0 flex-1">
            <span className="flex items-center gap-1 min-w-0">
              <p className="truncate font-semibold text-gray-900">{hotel.name}</p>
              <PremiumBadge plan={hotel.plan} compact />
            </span>
            {(hotel.city || hotel.state) && (
              <span className="mt-0.5 flex items-center gap-1 text-xs text-gray-400"><MapPin className="h-3 w-3" />{[hotel.city, hotel.state].filter(Boolean).join(', ')}</span>
            )}
          </div>
          <span className="text-xs text-gray-400">{hotel.followers_count ?? 0} seguidores</span>
        </Link>
      ))}
    </div>
  )
}
