import type { JobBreakdown } from '@/types/analytics.types'

interface JobsBreakdownChartProps {
  jobs: JobBreakdown[]
}

const STATUS_LABELS: Record<string, string> = {
  active: 'Ativa',
  paused: 'Pausada',
  closed: 'Encerrada',
}

/**
 * Detalhamento de candidaturas por vaga — mostra onde o interesse dos
 * candidatos está concentrado.
 * Sprint: monetization-hotelconnect-pro (Empresa PRO)
 */
export function JobsBreakdownChart({ jobs }: JobsBreakdownChartProps) {
  const max = Math.max(1, ...jobs.map((j) => j.applications))

  return (
    <div className="rounded-xl border border-gray-100 bg-white p-4">
      <h3 className="mb-3 text-sm font-semibold text-gray-800">Candidaturas por vaga</h3>
      {jobs.length === 0 ? (
        <p className="text-xs text-gray-400">Nenhuma vaga publicada ainda.</p>
      ) : (
        <ul className="space-y-2.5">
          {jobs.slice(0, 8).map((job, i) => (
            <li key={`${job.title}-${i}`}>
              <div className="mb-1 flex items-center justify-between text-xs">
                <span className="truncate font-medium text-gray-700">{job.title}</span>
                <span className="ml-2 flex-shrink-0 text-gray-400">
                  {job.applications} · {STATUS_LABELS[job.status] ?? job.status}
                </span>
              </div>
              <div className="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                <div
                  className="h-full rounded-full bg-primary transition-all"
                  style={{ width: `${(job.applications / max) * 100}%` }}
                />
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
