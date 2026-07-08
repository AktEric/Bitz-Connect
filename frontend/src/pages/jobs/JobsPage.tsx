import { useState } from 'react'
import { Briefcase } from 'lucide-react'
import { useJobs } from '@/hooks/useJobs'
import type { JobFiltersParams } from '@/services/job.service'
import JobCard from '@/components/job/JobCard'
import JobFilters from '@/components/job/JobFilters'

/**
 * Public jobs listing page with filters and pagination.
 * Requirements: 5.1, 5.2
 */
export default function JobsPage() {
  const [filters, setFilters] = useState<JobFiltersParams>({ page: 1 })

  const { data, isLoading, isError } = useJobs(filters)

  const jobs = data?.data ?? []
  const meta = data?.meta
  const page = filters.page ?? 1
  const hasMore = meta ? page < meta.last_page : false

  function handleFiltersChange(next: JobFiltersParams) {
    setFilters(next)
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      {/* Page header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Vagas</h1>
        {meta && (
          <p className="mt-1 text-sm text-gray-500">
            {meta.total} vaga{meta.total !== 1 ? 's' : ''} encontrada{meta.total !== 1 ? 's' : ''}
          </p>
        )}
      </div>

      {/* Filters */}
      <div className="mb-6">
        <JobFilters filters={filters} onChange={handleFiltersChange} />
      </div>

      {/* Results */}
      <div className="space-y-3">
        {isLoading ? (
          // Loading skeleton
          Array.from({ length: 5 }).map((_, i) => (
            <div
              key={i}
              className="animate-pulse rounded-xl border border-gray-100 bg-white p-4"
            >
              <div className="flex items-start gap-3">
                <div className="h-11 w-11 rounded-lg bg-gray-200" />
                <div className="flex-1 space-y-2">
                  <div className="h-3.5 w-48 rounded bg-gray-200" />
                  <div className="h-3 w-32 rounded bg-gray-100" />
                </div>
              </div>
              <div className="mt-3 flex gap-2">
                <div className="h-5 w-20 rounded-full bg-gray-100" />
                <div className="h-5 w-20 rounded-full bg-gray-100" />
              </div>
            </div>
          ))
        ) : isError ? (
          <div className="rounded-xl border border-red-100 bg-red-50 p-6 text-center text-sm text-red-500">
            Erro ao carregar as vagas. Tente recarregar a página.
          </div>
        ) : jobs.length === 0 ? (
          <div className="rounded-xl border border-gray-100 bg-white p-10 text-center">
            <Briefcase className="mx-auto mb-3 h-8 w-8 text-gray-300" />
            <p className="text-gray-500">Nenhuma vaga encontrada.</p>
            {(filters.contract_type || filters.modality || filters.city || filters.state) && (
              <p className="mt-1 text-sm text-gray-400">
                Tente remover alguns filtros.
              </p>
            )}
          </div>
        ) : (
          jobs.map((job) => <JobCard key={job.uuid} job={job} />)
        )}
      </div>

      {/* Pagination */}
      {!isLoading && !isError && jobs.length > 0 && (
        <div className="mt-6 flex items-center justify-center gap-3">
          {page > 1 && (
            <button
              onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) - 1 }))}
              className="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50"
            >
              ← Anterior
            </button>
          )}

          {meta && (
            <span className="text-sm text-gray-400">
              Página {meta.current_page} de {meta.last_page}
            </span>
          )}

          {hasMore && (
            <button
              onClick={() => setFilters((f) => ({ ...f, page: (f.page ?? 1) + 1 }))}
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
