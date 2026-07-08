import { Users } from 'lucide-react'
import { useHotelEmployees } from '@/hooks/useHotel'
import { EmployeeCard } from '@/components/hotel/EmployeeCard'
import { EmployeeCardSkeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/ui/EmptyState'

interface EmployeesTabProps {
  slug: string
}

/**
 * Tab that lists approved employees for a hotel.
 * Requirements: 5.1, 5.2, 5.4, 5.5
 */
export function EmployeesTab({ slug }: EmployeesTabProps) {
  const { data: employees = [], isLoading } = useHotelEmployees(slug)

  if (isLoading) {
    return (
      <div className="space-y-3">
        <EmployeeCardSkeleton />
        <EmployeeCardSkeleton />
        <EmployeeCardSkeleton />
      </div>
    )
  }

  if (employees.length === 0) {
    return (
      <EmptyState
        title="Nenhum colaborador verificado"
        description="Os colaboradores aparecem aqui após aprovação do vínculo."
        icon={<Users className="h-8 w-8" />}
      />
    )
  }

  return (
    <div className="space-y-3">
      {employees.map((emp) => (
        <EmployeeCard key={emp.uuid} employee={emp} />
      ))}
    </div>
  )
}
