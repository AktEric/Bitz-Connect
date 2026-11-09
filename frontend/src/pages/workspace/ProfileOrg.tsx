import { useQuery } from '@tanstack/react-query'
import { Building2, Crown } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { WorkspacePerson } from '@/types/workspace.types'
import PersonLink, { PersonAvatarLink } from './PersonLink'
import { Panel, Spinner } from './ui'

function PersonRow({ p, hint }: { p: WorkspacePerson; hint?: string }) {
  return (
    <div className="flex items-center gap-2.5 rounded-xl p-2 hover:bg-gray-50">
      <PersonAvatarLink person={p} />
      <div className="min-w-0">
        <PersonLink person={p} className="block truncate text-sm font-medium text-gray-900" />
        <span className="block truncate text-xs text-gray-500">{[p.job_title, hint].filter(Boolean).join(' · ') || '—'}</span>
      </div>
    </div>
  )
}

function Group({ title, people, hint }: { title: string; people: WorkspacePerson[]; hint?: string }) {
  if (!people.length) return null
  return (
    <section>
      <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{title} <span className="font-normal text-gray-400">({people.length})</span></h3>
      <div className="grid gap-1 sm:grid-cols-2">{people.map((p) => <PersonRow key={p.uuid} p={p} hint={hint} />)}</div>
    </section>
  )
}

/** Setor, liderança (até o topo), equipe direta e colegas — tudo com link para o perfil. */
export default function ProfileOrg({ slug, uuid }: { slug: string; uuid: string }) {
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'org', uuid], queryFn: () => workspaceService.personOrg(slug, uuid) })
  if (isLoading || !data) return <Spinner />

  const empty = !data.department && !data.leaders.length && !data.team.length && !data.peers.length

  return (
    <Panel className="space-y-5">
      {data.department && (
        <div className="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-xl bg-gray-50 px-4 py-3 text-sm">
          <span className="inline-flex items-center gap-1.5 text-gray-700"><Building2 className="h-4 w-4 text-brand-500" /> <b>Setor:</b> {data.department.name}</span>
          <span className="text-gray-500">{data.department.members_count} {data.department.members_count === 1 ? 'pessoa' : 'pessoas'}</span>
          {data.department.head && <span className="inline-flex items-center gap-1.5 text-gray-700"><Crown className="h-4 w-4 text-amber-500" /> Chefe: <PersonLink person={data.department.head} className="font-medium" /></span>}
        </div>
      )}
      {data.manager && <Group title="Gestor direto" people={[data.manager]} />}
      {data.leaders.length > 1 && <Group title="Liderança acima" people={data.leaders.slice(1)} />}
      <Group title="Equipe direta" people={data.team} />
      <Group title={`Colegas de setor${data.department ? ` — ${data.department.name}` : ''}`} people={data.peers} />
      {empty && <p className="text-sm text-gray-400">Sem conexões de equipe cadastradas.</p>}
    </Panel>
  )
}
