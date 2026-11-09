import { Link } from 'react-router-dom'
import { Building2 } from 'lucide-react'
import { useMyWorkspaces } from '@/hooks/useWorkspace'
import { Avatar } from '@/components/ui/Avatar'
import { Empty, PageHeader, Pill, Spinner } from './ui'

/** Lista as empresas em que o usuário tem vínculo aprovado (admin ou colaborador). */
export default function WorkspacesPage() {
  const { data, isLoading } = useMyWorkspaces()
  if (isLoading) return <Spinner />

  return (
    <div className="mx-auto max-w-3xl px-4 py-8">
      <PageHeader title="Minhas empresas" subtitle="Acesse o espaço interno de cada empresa em que você trabalha." />
      {!data?.length ? (
        <Empty text="Você ainda não tem vínculo aprovado com nenhuma empresa. Peça um link de convite ao RH." />
      ) : (
        <div className="space-y-3">
          {data.map((w) => (
            <Link
              key={w.hotel.uuid}
              to={`/w/${w.hotel.slug}`}
              className="flex items-center gap-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm transition-shadow hover:shadow-md"
            >
              <Avatar src={w.hotel.avatar_url} name={w.hotel.name} size="lg" />
              <div className="min-w-0 flex-1">
                <p className="truncate font-semibold text-gray-900">{w.hotel.name}</p>
                <p className="truncate text-sm text-gray-500">
                  {[w.job_title, w.department].filter(Boolean).join(' · ') || w.hotel.segment || 'Colaborador'}
                </p>
              </div>
              {w.role === 'admin' ? <Pill tone="brand">RH / Gestão</Pill> : <Building2 className="h-5 w-5 text-gray-300" />}
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}
