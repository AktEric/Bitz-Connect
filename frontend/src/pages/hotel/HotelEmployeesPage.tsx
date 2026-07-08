import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { Users, Check, X } from 'lucide-react'
import { usePendingEmployees, useApproveEmployee, useRejectEmployee } from '@/hooks/useHotel'
import { EmployeesTab } from '@/components/hotel/EmployeesTab'
import { EmployeeCardSkeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/ui/EmptyState'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { ProfileAvatar } from '@/components/common/ProfileAvatar'
import { UserLink } from '@/components/common/UserLink'
import type { Employee } from '@/types/hotel.types'

/**
 * HotelEmployeesPage — manages employee link requests and lists approved employees.
 * Requirements: 4.2, 4.3, 4.4, 5.1, 5.2, 5.4
 */
export default function HotelEmployeesPage() {
  const { slug = '' } = useParams<{ slug: string }>()
  const { data: pending = [], isLoading } = usePendingEmployees(slug)
  const approveEmployee = useApproveEmployee(slug)
  const rejectEmployee = useRejectEmployee(slug)
  const [rejectTarget, setRejectTarget] = useState<Employee | null>(null)

  function handleReject() {
    if (!rejectTarget) return
    rejectEmployee.mutate(rejectTarget.user_id, {
      onSuccess: () => setRejectTarget(null),
    })
  }

  return (
    <div className="p-6 space-y-8">
      <div>
        <h1 className="text-2xl font-semibold">Gerenciar Colaboradores</h1>
        <p className="mt-2 text-muted-foreground">Aprove ou recuse solicitações de vínculo e acompanhe seus colaboradores.</p>
      </div>

      <section>
        <h2 className="mb-3 text-base font-semibold text-gray-900">Solicitações pendentes</h2>

        {isLoading && (
          <div className="space-y-3">
            <EmployeeCardSkeleton />
            <EmployeeCardSkeleton />
          </div>
        )}

        {!isLoading && pending.length === 0 && (
          <EmptyState
            title="Nenhuma solicitação pendente"
            description="Novas solicitações de vínculo profissional aparecerão aqui."
            icon={<Users className="h-8 w-8" />}
          />
        )}

        {!isLoading && pending.length > 0 && (
          <div className="space-y-3">
            {pending.map((emp) => (
              <div
                key={emp.uuid}
                className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4"
              >
                <ProfileAvatar uuid={emp.uuid} name={emp.name} src={emp.avatar_url} size="md" />
                <div className="flex-1 min-w-0">
                  <UserLink uuid={emp.uuid} name={emp.name} className="text-sm font-semibold text-gray-900" />
                  {emp.title && <p className="mt-0.5 text-xs text-gray-500">{emp.title}</p>}
                </div>
                <div className="flex flex-shrink-0 gap-2">
                  <button
                    onClick={() => approveEmployee.mutate(emp.user_id)}
                    disabled={approveEmployee.isPending}
                    className="flex items-center gap-1 rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 disabled:opacity-50"
                  >
                    <Check className="h-3.5 w-3.5" /> Aprovar
                  </button>
                  <button
                    onClick={() => setRejectTarget(emp)}
                    disabled={rejectEmployee.isPending}
                    className="flex items-center gap-1 rounded-md bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-100 disabled:opacity-50"
                  >
                    <X className="h-3.5 w-3.5" /> Recusar
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      <section>
        <h2 className="mb-3 text-base font-semibold text-gray-900">Colaboradores</h2>
        <EmployeesTab slug={slug} />
      </section>

      <ConfirmDialog
        open={!!rejectTarget}
        title="Recusar solicitação de vínculo"
        description={`Tem certeza que deseja recusar o vínculo de ${rejectTarget?.name ?? ''}?`}
        confirmLabel="Recusar"
        variant="danger"
        loading={rejectEmployee.isPending}
        onConfirm={handleReject}
        onCancel={() => setRejectTarget(null)}
      />
    </div>
  )
}
