import type { ApplicationStatus as ApplicationStatusType } from '@/types/job.types'

interface ApplicationStatusProps {
  status: ApplicationStatusType
  showLabel?: boolean
}

const STATUS_CONFIG: Record<
  ApplicationStatusType,
  { label: string; className: string }
> = {
  pending: {
    label: 'Aguardando',
    className: 'bg-yellow-50 text-yellow-700 border-yellow-200',
  },
  viewed: {
    label: 'Visualizada',
    className: 'bg-blue-50 text-blue-700 border-blue-200',
  },
  rejected: {
    label: 'Recusada',
    className: 'bg-red-50 text-red-700 border-red-200',
  },
  hired: {
    label: 'Contratado(a)',
    className: 'bg-green-50 text-green-700 border-green-200',
  },
}

/**
 * Badge that displays the current status of a job application.
 * Requirements: 5.9
 */
export default function ApplicationStatus({
  status,
  showLabel = true,
}: ApplicationStatusProps) {
  const config = STATUS_CONFIG[status]

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium ${config.className}`}
      aria-label={`Status da candidatura: ${config.label}`}
    >
      {/* Status dot */}
      <span
        className="h-1.5 w-1.5 rounded-full bg-current"
        aria-hidden="true"
      />
      {showLabel && config.label}
    </span>
  )
}
