import { CheckCircle } from 'lucide-react'
import { differenceInMonths, differenceInYears } from 'date-fns'
import type { Employee } from '@/types/hotel.types'
import { UserLink } from '@/components/common/UserLink'
import { ProfileAvatar } from '@/components/common/ProfileAvatar'

/**
 * Formats the duration between two dates in a human-readable way.
 * Returns "X anos" or "X meses" or "Menos de 1 mês".
 * Requirements: 5.4
 */
export function formatDuration(startDate: Date, endDate: Date): string {
  const years  = differenceInYears(endDate, startDate)
  const months = differenceInMonths(endDate, startDate)

  if (years >= 1) return `${years} ano${years !== 1 ? 's' : ''}`
  if (months >= 1) return `${months} mês${months !== 1 ? 'es' : ''}`
  return 'Menos de 1 mês'
}

interface EmployeeCardProps {
  employee: Employee
}

/**
 * Card that displays an approved employee with their verified badge and tenure.
 * Requirements: 5.2, 5.3, 5.4
 */
export function EmployeeCard({ employee }: EmployeeCardProps) {
  const duration = employee.started_at
    ? formatDuration(new Date(employee.started_at), new Date())
    : null

  return (
    <div className="flex items-start gap-3 rounded-xl border border-gray-100 bg-white p-4 hover:shadow-sm transition-shadow">
      <ProfileAvatar
        uuid={employee.uuid}
        name={employee.name}
        src={employee.avatar_url}
        size="md"
      />

      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2 flex-wrap">
          <UserLink
            uuid={employee.uuid}
            name={employee.name}
            className="text-sm font-semibold text-gray-900 hover:text-violet-600 transition-colors"
          />
          {employee.verified && (
            <span className="flex items-center gap-1 rounded-full bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-600">
              <CheckCircle className="h-3 w-3" />
              Verificado
            </span>
          )}
        </div>

        {employee.title && (
          <p className="mt-0.5 text-xs text-gray-500">{employee.title}</p>
        )}

        {duration && (
          <p className="mt-0.5 text-xs text-gray-400">{duration}</p>
        )}
      </div>
    </div>
  )
}
