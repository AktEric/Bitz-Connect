import { Link } from 'react-router-dom'
import { MapPin, Briefcase, Monitor } from 'lucide-react'
import type { Job } from '@/types/job.types'
import { formatRelative } from '@/utils/format'

interface JobCardProps {
  job: Job
}

const CONTRACT_LABELS: Record<string, string> = {
  clt: 'CLT',
  pj: 'PJ',
  freelance: 'Freelance',
  internship: 'Estágio',
}

const MODALITY_LABELS: Record<string, string> = {
  on_site: 'Presencial',
  remote: 'Remoto',
  hybrid: 'Híbrido',
}

const MODALITY_COLORS: Record<string, string> = {
  on_site: 'bg-gray-100 text-gray-600',
  remote: 'bg-green-50 text-green-700',
  hybrid: 'bg-blue-50 text-blue-700',
}

/**
 * Compact job card for list views.
 * Requirements: 5.2
 */
export default function JobCard({ job }: JobCardProps) {
  return (
    <Link
      to={`/jobs/${job.uuid}`}
      className="block rounded-xl border border-gray-100 bg-white p-4 transition hover:shadow-md"
    >
      {/* Header: hotel logo + title */}
      <div className="flex items-start gap-3">
        <div className="h-11 w-11 flex-shrink-0 overflow-hidden rounded-lg bg-primary/10">
          {job.hotel.logo_url ? (
            <img
              src={job.hotel.logo_url}
              alt={job.hotel.name}
              className="h-full w-full object-cover"
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center text-base font-bold text-primary">
              {job.hotel.name.charAt(0).toUpperCase()}
            </div>
          )}
        </div>

        <div className="min-w-0 flex-1">
          <h3 className="truncate font-semibold text-gray-900">{job.title}</h3>
          <p className="truncate text-sm text-gray-500">{job.hotel.name}</p>
        </div>
      </div>

      {/* Meta: location, contract, modality, salary */}
      <div className="mt-3 flex flex-wrap items-center gap-2 text-xs">
        {(job.city || job.state) && (
          <span className="flex items-center gap-1 text-gray-400">
            <MapPin className="h-3 w-3" />
            {[job.city, job.state].filter(Boolean).join(', ')}
          </span>
        )}

        <span className="rounded-full bg-amber-50 px-2 py-0.5 font-medium text-amber-700">
          <Briefcase className="mr-1 inline h-3 w-3" />
          {CONTRACT_LABELS[job.contract_type] ?? job.contract_type}
        </span>

        <span
          className={`flex items-center gap-1 rounded-full px-2 py-0.5 font-medium ${
            MODALITY_COLORS[job.modality] ?? 'bg-gray-100 text-gray-600'
          }`}
        >
          <Monitor className="h-3 w-3" />
          {MODALITY_LABELS[job.modality] ?? job.modality}
        </span>

        {job.salary_range && (
          <span className="text-gray-500">· {job.salary_range}</span>
        )}
      </div>

      {/* Footer: posted at */}
      <p className="mt-3 text-xs text-gray-400">{formatRelative(job.created_at)}</p>
    </Link>
  )
}
