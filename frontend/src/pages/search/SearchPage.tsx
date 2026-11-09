import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Search, MapPin, Briefcase, Users, Building2 } from 'lucide-react'
import { useSearch, type SearchTab } from '@/hooks/useSearch'
import { UserLink } from '@/components/common/UserLink'
import { ProfileAvatar } from '@/components/common/ProfileAvatar'
import { CompanyLink } from '@/components/common/CompanyLink'
import { CompanyAvatar } from '@/components/common/CompanyAvatar'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import type { Hotel } from '@/types/hotel.types'
import type { Job } from '@/types/job.types'
import type { User } from '@/types/auth.types'

// ─── Tab configuration ────────────────────────────────────────────────────────

const TABS: { id: SearchTab; label: string; icon: React.ElementType }[] = [
  { id: 'hotels', label: 'Hotéis',   icon: Building2 },
  { id: 'jobs',   label: 'Vagas',    icon: Briefcase  },
  { id: 'users',  label: 'Usuários', icon: Users      },
]

// ─── Contract type labels ─────────────────────────────────────────────────────

const CONTRACT_LABELS: Record<string, string> = {
  clt:        'CLT',
  pj:         'PJ',
  freelance:  'Freelance',
  internship: 'Estágio',
}

const MODALITY_LABELS: Record<string, string> = {
  on_site: 'Presencial',
  remote:  'Remoto',
  hybrid:  'Híbrido',
}

// ─── Sub-components ───────────────────────────────────────────────────────────

function HotelResult({ hotel }: { hotel: Hotel }) {
  return (
    <div className="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-4 transition hover:shadow-md">
      <CompanyAvatar slug={hotel.slug} name={hotel.name} logoUrl={hotel.logo_url} size="md" />
      <div className="min-w-0 flex-1">
        <span className="flex items-center gap-1 min-w-0">
          <CompanyLink slug={hotel.slug} name={hotel.name} className="truncate font-semibold text-gray-900 hover:text-brand-600" />
          <PremiumBadge plan={hotel.plan} compact />
        </span>
        {hotel.description && (
          <p className="mt-0.5 line-clamp-1 text-sm text-gray-500">{hotel.description}</p>
        )}
        {(hotel.city || hotel.state) && (
          <span className="mt-1 flex items-center gap-1 text-xs text-gray-400">
            <MapPin className="h-3 w-3" />
            {[hotel.city, hotel.state].filter(Boolean).join(', ')}
          </span>
        )}
      </div>
    </div>
  )
}

function JobResult({ job }: { job: Job }) {
  return (
    <Link
      to={`/jobs/${job.uuid}`}
      className="flex flex-col gap-1 rounded-xl border border-gray-100 bg-white p-4 transition hover:shadow-md"
    >
      <h3 className="font-semibold text-gray-900">{job.title}</h3>
      <p className="text-sm text-gray-600">{job.hotel.name}</p>
      <div className="mt-1 flex flex-wrap gap-2 text-xs text-gray-400">
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

function UserResult({ user }: { user: User }) {
  return (
    <div className="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-4 transition hover:shadow-md">
      <ProfileAvatar uuid={user.uuid} name={user.name} src={user.avatar_url} size="sm" />
      <div className="min-w-0 flex-1">
        <span className="flex items-center gap-1 min-w-0">
          <UserLink uuid={user.uuid} name={user.name} className="truncate font-semibold text-gray-900 hover:text-brand-600" />
          <PremiumBadge plan={user.plan} compact />
        </span>
        {(user.city || user.state) && (
          <span className="flex items-center gap-1 text-xs text-gray-400">
            <MapPin className="h-3 w-3" />
            {[user.city, user.state].filter(Boolean).join(', ')}
          </span>
        )}
      </div>
    </div>
  )
}

// ─── Skeleton loader ──────────────────────────────────────────────────────────

function ResultSkeleton() {
  return (
    <div className="space-y-3">
      {Array.from({ length: 4 }).map((_, i) => (
        <div
          key={i}
          className="animate-pulse rounded-xl border border-gray-100 bg-white p-4"
        >
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 rounded-lg bg-gray-200" />
            <div className="flex-1 space-y-2">
              <div className="h-3.5 w-40 rounded bg-gray-200" />
              <div className="h-3 w-56 rounded bg-gray-100" />
              <div className="h-2.5 w-24 rounded bg-gray-100" />
            </div>
          </div>
        </div>
      ))}
    </div>
  )
}

// ─── Main page ────────────────────────────────────────────────────────────────

/**
 * Full-page search experience with tabs for Hotels, Jobs, and Users.
 * Requirements: 6.1, 6.2, 6.3
 */
export default function SearchPage() {
  const [query, setQuery]       = useState('')
  const [activeTab, setActiveTab] = useState<SearchTab>('hotels')
  const [page, setPage]         = useState(1)

  // Reset to page 1 when tab or query changes
  function handleTabChange(tab: SearchTab) {
    setActiveTab(tab)
    setPage(1)
  }

  function handleQueryChange(value: string) {
    setQuery(value)
    setPage(1)
  }

  const { debouncedQuery, data, isLoading, isFetching, isError } = useSearch(
    query,
    activeTab,
    page,
  )

  const results = data?.data ?? []
  const meta    = data?.meta
  const hasMore = meta ? page < meta.last_page : false
  const isEmpty = debouncedQuery.trim().length > 0 && !isLoading && !isFetching && results.length === 0

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      {/* Page title */}
      <h1 className="mb-6 text-2xl font-bold text-gray-900">Pesquisa</h1>

      {/* Search input */}
      <div className="relative mb-6">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input
          type="search"
          value={query}
          onChange={(e) => handleQueryChange(e.target.value)}
          placeholder="Buscar hotéis, vagas ou usuários..."
          className="w-full rounded-xl border border-gray-200 bg-white py-3 pl-10 pr-4 text-sm shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
          autoFocus
        />
      </div>

      {/* Tabs */}
      <div className="mb-6 flex gap-1 rounded-xl border border-gray-100 bg-gray-50 p-1">
        {TABS.map((tab) => {
          const Icon = tab.icon
          const isActive = activeTab === tab.id
          return (
            <button
              key={tab.id}
              onClick={() => handleTabChange(tab.id)}
              className={`flex flex-1 items-center justify-center gap-2 rounded-lg py-2 text-sm font-medium transition ${
                isActive
                  ? 'bg-white text-primary shadow-sm'
                  : 'text-gray-500 hover:text-gray-700'
              }`}
            >
              <Icon className="h-4 w-4" />
              {tab.label}
            </button>
          )
        })}
      </div>

      {/* Results area */}
      <div className="space-y-3">
        {/* Initial / idle state — no query yet */}
        {debouncedQuery.trim().length === 0 && (
          <div className="rounded-xl border border-gray-100 bg-white p-10 text-center">
            <Search className="mx-auto mb-3 h-8 w-8 text-gray-300" />
            <p className="text-gray-500">Digite algo para começar a busca.</p>
          </div>
        )}

        {/* Loading skeleton */}
        {(isLoading || isFetching) && debouncedQuery.trim().length > 0 && (
          <ResultSkeleton />
        )}

        {/* Error state */}
        {isError && !isFetching && (
          <div className="rounded-xl border border-red-100 bg-red-50 p-6 text-center text-sm text-red-500">
            Ocorreu um erro ao buscar resultados. Tente novamente.
          </div>
        )}

        {/* Empty state */}
        {isEmpty && (
          <div className="rounded-xl border border-gray-100 bg-white p-10 text-center">
            <p className="text-gray-500">
              Nenhum resultado encontrado para <strong>"{debouncedQuery}"</strong>.
            </p>
          </div>
        )}

        {/* Hotel results */}
        {!isLoading && !isFetching && activeTab === 'hotels' &&
          (results as Hotel[]).map((hotel) => (
            <HotelResult key={hotel.uuid} hotel={hotel} />
          ))
        }

        {/* Job results */}
        {!isLoading && !isFetching && activeTab === 'jobs' &&
          (results as Job[]).map((job) => (
            <JobResult key={job.uuid} job={job} />
          ))
        }

        {/* User results */}
        {!isLoading && !isFetching && activeTab === 'users' &&
          (results as User[]).map((user) => (
            <UserResult key={user.uuid} user={user} />
          ))
        }
      </div>

      {/* Pagination */}
      {!isLoading && !isFetching && results.length > 0 && (
        <div className="mt-6 flex items-center justify-center gap-3">
          {page > 1 && (
            <button
              onClick={() => setPage((p) => p - 1)}
              className="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
            >
              ← Anterior
            </button>
          )}

          {meta && (
            <span className="text-sm text-gray-400">
              Página {meta.current_page} de {meta.last_page}
              {' '}·{' '}
              {meta.total} resultado{meta.total !== 1 ? 's' : ''}
            </span>
          )}

          {hasMore && (
            <button
              onClick={() => setPage((p) => p + 1)}
              className="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
            >
              Próxima →
            </button>
          )}
        </div>
      )}
    </div>
  )
}
