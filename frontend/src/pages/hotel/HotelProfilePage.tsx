import { useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import {
  Pencil, Briefcase, Plus, MapPin, Share2, UserCheck,
  UserX, Flag, Heart, MessageSquare,
} from 'lucide-react'
import {
  useHotel, useUploadLogo, useUploadCover,
  useFollowHotel, useUnfollowHotel,
  useHotelEmployees, usePendingEmployees,
  useApproveEmployee, useRejectEmployee,
  useClaimHotel, useHotelJobs,
} from '@/hooks/useHotel'
import { useHotelPosts } from '@/hooks/useFeed'
import HotelHeader from '@/components/hotel/HotelHeader'
import { EmployeeCard } from '@/components/hotel/EmployeeCard'
import PostCard from '@/components/feed/PostCard'
import { EmptyState } from '@/components/ui/EmptyState'
import { SkeletonCard } from '@/components/ui/Skeleton'
import { useAuth } from '@/hooks/useAuth'
import type { Job } from '@/types/job.types'

// ──────────────────────────────────────────────────────────
// Job card
// ──────────────────────────────────────────────────────────

const CONTRACT_LABELS: Record<string, string> = {
  clt: 'CLT', pj: 'PJ', freelance: 'Freelance', internship: 'Estágio',
}
const MODALITY_LABELS: Record<string, string> = {
  on_site: 'Presencial', remote: 'Remoto', hybrid: 'Híbrido',
}

function JobCard({ job }: { job: Job }) {
  return (
    <Link
      to={`/jobs/${job.uuid}`}
      className="block rounded-xl border border-gray-100 bg-gray-50 p-4 hover:shadow-md transition-shadow"
    >
      <h3 className="font-semibold text-gray-900">{job.title}</h3>
      <div className="mt-1.5 flex flex-wrap gap-2 text-xs text-gray-500">
        {(job.city || job.state) && (
          <span className="flex items-center gap-1">
            <MapPin className="h-3 w-3" />
            {[job.city, job.state].filter(Boolean).join(', ')}
          </span>
        )}
        {job.contract_type && (
          <span className="rounded-full bg-blue-50 px-2 py-0.5 text-blue-600">
            {CONTRACT_LABELS[job.contract_type] ?? job.contract_type}
          </span>
        )}
        {job.modality && (
          <span className="rounded-full bg-green-50 px-2 py-0.5 text-green-600">
            {MODALITY_LABELS[job.modality] ?? job.modality}
          </span>
        )}
      </div>
    </Link>
  )
}

// ──────────────────────────────────────────────────────────
// HotelProfilePage
// ──────────────────────────────────────────────────────────

type Tab = 'about' | 'posts' | 'employees' | 'jobs'

/**
 * Public hotel profile page with tabs: Sobre, Publicações, Colaboradores, Vagas.
 * Requirements: 1.3, 1.5, 4.1–4.9, 5.1–5.5, 6.6, 6.7, 7.1, 8.2, 8.3, 9.5
 */
export default function HotelProfilePage() {
  const { slug } = useParams<{ slug: string }>()
  const { user } = useAuth()
  const navigate = useNavigate()
  const [tab, setTab] = useState<Tab>('about')
  const [shareCopied, setShareCopied] = useState(false)

  const { data: hotel, isLoading, isError } = useHotel(slug ?? '')
  const uploadLogo  = useUploadLogo(slug ?? '')
  const uploadCover = useUploadCover(slug ?? '')
  const follow      = useFollowHotel(slug ?? '')
  const unfollow    = useUnfollowHotel(slug ?? '')
  const claimHotel  = useClaimHotel(slug ?? '')

  const isAdmin = hotel?.is_admin ?? false

  const { data: employees = [] }        = useHotelEmployees(slug ?? '')
  const { data: pending = [] }          = usePendingEmployees(slug ?? '', isAdmin)
  const { data: jobsData }              = useHotelJobs(slug ?? '')
  const { data: postsData, isLoading: postsLoading } = useHotelPosts(slug ?? '')
  const approveEmployee = useApproveEmployee(slug ?? '')
  const rejectEmployee  = useRejectEmployee(slug ?? '')

  async function handleShare() {
    const url = `${window.location.origin}/hotels/${slug}`
    try {
      await navigator.clipboard.writeText(url)
      setShareCopied(true)
      setTimeout(() => setShareCopied(false), 2000)
    } catch {
      // ignore
    }
  }

  // ── Loading ────────────────────────────────────────────

  if (isLoading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }

  // ── 404 ───────────────────────────────────────────────

  if (isError) {
    return (
      <div className="mx-auto max-w-md px-4 py-16 text-center">
        <h1 className="text-4xl font-bold text-gray-900">404</h1>
        <p className="mt-2 text-gray-500">Hotel não encontrado.</p>
        <Link to="/hotels" className="mt-4 inline-block text-sm text-violet-600 hover:underline">
          ← Voltar para Hotéis
        </Link>
      </div>
    )
  }

  if (!hotel) return null

  const jobs: Job[] = (jobsData?.data ?? []) as Job[]
  const posts = postsData?.data ?? []

  const TABS: { id: Tab; label: string }[] = [
    { id: 'about',     label: 'Sobre' },
    { id: 'posts',     label: 'Publicações' },
    { id: 'employees', label: `Colaboradores${hotel.employees_count ? ` (${hotel.employees_count})` : ''}` },
    { id: 'jobs',      label: `Vagas${hotel.jobs_count ? ` (${hotel.jobs_count})` : ''}` },
  ]

  return (
    <div className="mx-auto max-w-3xl px-4 py-8">
      {/* Header */}
      <HotelHeader
        hotel={hotel}
        editable={isAdmin}
        onLogoSelect={(file) => uploadLogo.mutate(file)}
        onCoverSelect={(file) => uploadCover.mutate(file)}
        logoLoading={uploadLogo.isPending}
        coverLoading={uploadCover.isPending}
      />

      {/* Stats */}
      <div className="mt-4 flex flex-wrap gap-6 rounded-xl border border-gray-100 bg-white px-6 py-4 shadow-sm">
        <div className="text-center">
          <p className="text-lg font-bold text-gray-900">{hotel.followers_count}</p>
          <p className="text-xs text-gray-500">seguidores</p>
        </div>
        <div className="text-center">
          <p className="text-lg font-bold text-gray-900">{hotel.employees_count ?? 0}</p>
          <p className="text-xs text-gray-500">colaboradores</p>
        </div>
        <div className="text-center">
          <p className="text-lg font-bold text-gray-900">{hotel.jobs_count ?? 0}</p>
          <p className="text-xs text-gray-500">vagas ativas</p>
        </div>
      </div>

      {/* Action bar */}
      <div className="mt-4 flex flex-wrap items-center gap-2">
        {user && (
          hotel.is_following ? (
            <button
              onClick={() => unfollow.mutate()}
              disabled={unfollow.isPending}
              className="flex items-center gap-1.5 rounded-full border border-gray-300 px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors"
            >
              <Heart className="h-4 w-4 fill-violet-600 text-violet-600" />
              Deixar de Seguir
            </button>
          ) : (
            <button
              onClick={() => follow.mutate()}
              disabled={follow.isPending}
              className="flex items-center gap-1.5 rounded-full bg-violet-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50 transition-colors"
            >
              <Heart className="h-4 w-4" />
              Seguir
            </button>
          )
        )}

        <button
          onClick={() => { setTab('jobs') }}
          className="flex items-center gap-1.5 rounded-full border border-gray-300 px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
        >
          <Briefcase className="h-4 w-4" />
          Ver Vagas
        </button>

        {user && !isAdmin && (
          <button
            onClick={() => navigate('/chat', { state: { openHotelSlug: hotel.slug } })}
            className="flex items-center gap-1.5 rounded-full border border-gray-300 px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          >
            <MessageSquare className="h-4 w-4" />
            Mensagem
          </button>
        )}

        <button
          onClick={handleShare}
          className="flex items-center gap-1.5 rounded-full border border-gray-300 px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
        >
          <Share2 className="h-4 w-4" />
          {shareCopied ? 'Copiado!' : 'Compartilhar'}
        </button>

        {isAdmin && (
          <Link
            to={`/hotels/${hotel.slug}/edit`}
            className="ml-auto flex items-center gap-1.5 rounded-full border border-gray-300 px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          >
            <Pencil className="h-4 w-4" />
            Editar Perfil do Hotel
          </Link>
        )}

        {!hotel.has_admin && user && (
          <button
            onClick={() => claimHotel.mutate(undefined)}
            disabled={claimHotel.isPending}
            className="flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-4 py-1.5 text-sm font-medium text-amber-700 hover:bg-amber-100 disabled:opacity-50 transition-colors"
          >
            <Flag className="h-4 w-4" />
            {claimHotel.isPending ? 'Enviando...' : 'Reivindicar este Hotel'}
          </button>
        )}
      </div>

      {/* Tabs */}
      <div className="mt-6 rounded-xl border border-gray-100 bg-white shadow-sm">
        <div className="flex overflow-x-auto border-b border-gray-100 px-2">
          {TABS.map((t) => (
            <button
              key={t.id}
              onClick={() => setTab(t.id)}
              className={`flex-shrink-0 px-5 py-3 text-sm font-medium border-b-2 transition-colors ${
                tab === t.id
                  ? 'border-violet-600 text-violet-600'
                  : 'border-transparent text-gray-500 hover:text-gray-800'
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>

        <div className="p-6">
          {/* Sobre */}
          {tab === 'about' && (
            <div className="space-y-3 text-sm text-gray-700">
              {hotel.segment && (
                <p><span className="font-medium text-gray-900">Segmento:</span> {hotel.segment}</p>
              )}
              {hotel.description && (
                <p className="leading-relaxed">{hotel.description}</p>
              )}
              {!hotel.description && !hotel.segment && (
                <p className="text-gray-400 italic">Nenhuma informação cadastrada.</p>
              )}
            </div>
          )}

          {/* Publicações */}
          {tab === 'posts' && (
            <div className="space-y-4">
              {postsLoading && (
                <div className="space-y-3">
                  <SkeletonCard />
                  <SkeletonCard />
                </div>
              )}

              {!postsLoading && posts.length === 0 && (
                <EmptyState
                  title="Nenhuma publicação ainda"
                  description="Publicações feitas em nome do hotel aparecerão aqui."
                />
              )}

              {posts.map((post) => (
                <PostCard key={post.uuid} post={post} />
              ))}
            </div>
          )}

          {/* Colaboradores */}
          {tab === 'employees' && (
            <div className="space-y-4">
              {/* Approved employees */}
              {employees.length === 0 ? (
                <p className="text-sm text-gray-400 italic">Nenhum colaborador verificado ainda.</p>
              ) : (
                <div className="space-y-3">
                  {employees.map((emp) => (
                    <EmployeeCard key={emp.uuid} employee={emp} />
                  ))}
                </div>
              )}

              {/* Pending requests — admin only */}
              {isAdmin && pending.length > 0 && (
                <div className="mt-6">
                  <h3 className="mb-3 text-sm font-semibold text-gray-700">Solicitações pendentes</h3>
                  <div className="space-y-3">
                    {pending.map((req: any) => (
                      <div key={req.user?.uuid ?? req.user_id} className="flex items-center gap-3 rounded-xl border border-amber-100 bg-amber-50 p-3">
                        <div className="flex-1 min-w-0">
                          <p className="text-sm font-medium text-gray-900">{req.user?.name ?? '–'}</p>
                        </div>
                        <div className="flex gap-2">
                          <button
                            onClick={() => approveEmployee.mutate(req.user?.id ?? req.user_id)}
                            disabled={approveEmployee.isPending}
                            className="flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-50"
                          >
                            <UserCheck className="h-3.5 w-3.5" /> Aprovar
                          </button>
                          <button
                            onClick={() => rejectEmployee.mutate(req.user?.id ?? req.user_id)}
                            disabled={rejectEmployee.isPending}
                            className="flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                          >
                            <UserX className="h-3.5 w-3.5" /> Rejeitar
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Vagas */}
          {tab === 'jobs' && (
            <div className="space-y-3">
              {isAdmin && (
                <div className="flex justify-end">
                  <Link
                    to="/jobs/new"
                    className="flex items-center gap-1.5 rounded-full bg-violet-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-violet-700 transition-colors"
                  >
                    <Plus className="h-4 w-4" />
                    Publicar vaga
                  </Link>
                </div>
              )}
              {jobs.length === 0 ? (
                <p className="text-sm text-gray-400 italic">
                  {isAdmin ? 'Nenhuma vaga ativa. Publique a primeira!' : 'Nenhuma vaga disponível no momento.'}
                </p>
              ) : (
                jobs.map((job) => <JobCard key={job.uuid} job={job} />)
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
