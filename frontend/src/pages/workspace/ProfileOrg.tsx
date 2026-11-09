import { useMemo } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Building2, Crown, Network } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { OrgTreeView, buildTree, withKids, type OrgNode } from './OrgTree'
import PersonLink from './PersonLink'
import { Panel, Spinner } from './ui'

const findNode = (nodes: OrgNode[], uuid: string): OrgNode | null => {
  for (const n of nodes) {
    if (n.m.uuid === uuid) return n
    const f = findNode(n.children, uuid)
    if (f) return f
  }
  return null
}

/**
 * Árvore recortada para a pessoa:
 *  - líder: seus superiores (até o topo) e a equipe inteira abaixo dele, sem outras equipes;
 *  - sem equipe: o líder e a equipe dele (os colegas que respondem ao mesmo líder), com a pessoa destacada.
 */
function useScopedTree(members: Parameters<typeof buildTree>[0] | undefined, uuid: string) {
  return useMemo(() => {
    if (!members) return null
    const forest = buildTree(members)
    const me = findNode(forest, uuid)
    if (!me) return null
    const parentOf = (n: OrgNode) => (n.m.manager ? findNode(forest, n.m.manager.uuid) : null)

    if (me.children.length) {
      let root: OrgNode = me
      const seen = new Set([me.m.uuid])
      for (let up = parentOf(me), i = 0; up && !seen.has(up.m.uuid) && i < 20; up = parentOf(up), i++) {
        seen.add(up.m.uuid)
        root = { m: up.m, children: [root] }
      }
      return { root, isLeader: true }
    }
    const leader = parentOf(me)
    if (!leader) return { root: me, isLeader: false }

    return { root: { m: leader.m, children: leader.children.map((c) => ({ m: c.m, children: [] })) }, isLeader: false }
  }, [members, uuid])
}

/** Setor e organograma da pessoa (líder e equipe), com link para o perfil de cada um. */
export default function ProfileOrg({ slug, uuid }: { slug: string; uuid: string }) {
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'org', uuid], queryFn: () => workspaceService.personOrg(slug, uuid) })
  const { data: members } = useQuery({ queryKey: ['ws', slug, 'members'], queryFn: () => workspaceService.members(slug) })
  const scoped = useScopedTree(members, uuid)
  if (isLoading || !data || !members) return <Spinner />

  const open = new Set(scoped ? withKids([scoped.root]) : [])
  const alone = !scoped || (!scoped.root.children.length && scoped.root.m.uuid === uuid)

  return (
    <Panel className="space-y-4">
      {data.department && (
        <div className="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-xl bg-gray-50 px-4 py-3 text-sm">
          <span className="inline-flex items-center gap-1.5 text-gray-700"><Building2 className="h-4 w-4 text-brand-500" /> <b>Setor:</b> {data.department.name}</span>
          <span className="text-gray-500">{data.department.members_count} {data.department.members_count === 1 ? 'pessoa' : 'pessoas'}</span>
          {data.department.head && <span className="inline-flex items-center gap-1.5 text-gray-700"><Crown className="h-4 w-4 text-amber-500" /> Chefe: <PersonLink person={data.department.head} className="font-medium" /></span>}
        </div>
      )}

      {alone ? (
        <p className="text-sm text-gray-400">Sem líder nem equipe cadastrados.</p>
      ) : (
        <>
          <p className="text-xs text-gray-500">
            {scoped.isLeader ? 'Liderança acima e equipe completa desta pessoa.' : 'Líder e equipe desta pessoa.'} A pessoa está destacada.
          </p>
          <div className="overflow-x-auto">
            <OrgTreeView roots={[scoped.root]} slug={slug} open={open} toggle={() => undefined} highlight={uuid} fixed />
          </div>
        </>
      )}
      <Link to={`/w/${slug}/organograma`} className="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:underline"><Network className="h-4 w-4" /> Ver o organograma completo da empresa</Link>
    </Panel>
  )
}
