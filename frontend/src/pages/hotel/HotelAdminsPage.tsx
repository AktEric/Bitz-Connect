import { useParams } from 'react-router-dom'
import { Shield, ShieldOff, Users } from 'lucide-react'
import { useHotelEmployees, usePromoteUser, useDemoteUser } from '@/hooks/useHotel'
import { ProfileAvatar } from '@/components/common/ProfileAvatar'
import { UserLink } from '@/components/common/UserLink'
import { EmptyState } from '@/components/ui/EmptyState'
import { EmployeeCardSkeleton } from '@/components/ui/Skeleton'
import type { Employee } from '@/types/hotel.types'

/**
 * HotelAdminsPage — lista administradores e colaboradores aprovados do hotel,
 * com ações para promover um colaborador a admin ou rebaixar um admin.
 * Requirements: 9.6, 9.7, 9.8
 */
export default function HotelAdminsPage() {
  const { slug = '' } = useParams<{ slug: string }>()
  const { data: members = [], isLoading } = useHotelEmployees(slug)
  const promote = usePromoteUser(slug)
  const demote = useDemoteUser(slug)

  const admins = members.filter((m) => m.hotel_user.role === 'admin')
  const employees = members.filter((m) => m.hotel_user.role === 'employee')

  return (
    <div className="p-6 space-y-8">
      <div>
        <h1 className="text-2xl font-semibold">Administradores</h1>
        <p className="mt-2 text-muted-foreground">Gerencie quem pode administrar o perfil e as vagas deste hotel.</p>
      </div>

      <section>
        <h2 className="mb-3 text-base font-semibold text-gray-900">Administradores ({admins.length})</h2>

        {isLoading && (
          <div className="space-y-3">
            <EmployeeCardSkeleton />
            <EmployeeCardSkeleton />
          </div>
        )}

        {!isLoading && admins.length === 0 && (
          <EmptyState
            title="Nenhum administrador encontrado"
            description="Administradores com uma experiência vinculada e em andamento neste hotel aparecem aqui."
            icon={<Shield className="h-8 w-8" />}
          />
        )}

        {!isLoading && admins.length > 0 && (
          <div className="space-y-3">
            {admins.map((admin) => (
              <AdminRow
                key={admin.uuid}
                member={admin}
                action={
                  <button
                    onClick={() => demote.mutate(admin.user_id)}
                    disabled={demote.isPending}
                    className="flex items-center gap-1 rounded-md bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-100 disabled:opacity-50"
                  >
                    <ShieldOff className="h-3.5 w-3.5" /> Rebaixar
                  </button>
                }
              />
            ))}
          </div>
        )}
      </section>

      <section>
        <h2 className="mb-3 text-base font-semibold text-gray-900">Colaboradores ({employees.length})</h2>

        {!isLoading && employees.length === 0 && (
          <EmptyState
            title="Nenhum colaborador para promover"
            description="Colaboradores aprovados e com vínculo em andamento aparecem aqui."
            icon={<Users className="h-8 w-8" />}
          />
        )}

        {employees.length > 0 && (
          <div className="space-y-3">
            {employees.map((emp) => (
              <AdminRow
                key={emp.uuid}
                member={emp}
                action={
                  <button
                    onClick={() => promote.mutate(emp.user_id)}
                    disabled={promote.isPending}
                    className="flex items-center gap-1 rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 disabled:opacity-50"
                  >
                    <Shield className="h-3.5 w-3.5" /> Promover a admin
                  </button>
                }
              />
            ))}
          </div>
        )}
      </section>
    </div>
  )
}

function AdminRow({ member, action }: { member: Employee; action: React.ReactNode }) {
  return (
    <div className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4">
      <ProfileAvatar uuid={member.uuid} name={member.name} src={member.avatar_url} size="md" />
      <div className="flex-1 min-w-0">
        <UserLink uuid={member.uuid} name={member.name} className="text-sm font-semibold text-gray-900" />
        {member.title && <p className="mt-0.5 text-xs text-gray-500">{member.title}</p>}
      </div>
      <div className="flex-shrink-0">{action}</div>
    </div>
  )
}
