import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ChevronDown, ChevronRight, Search } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { WorkspaceMember } from '@/types/workspace.types'
import { Empty, PageHeader, Panel, Spinner, inputCls, useWs } from './ui'

type Node = { m: WorkspaceMember; children: Node[] }

function build(members: WorkspaceMember[]): Node[] {
  const byId = new Map(members.map((m) => [m.uuid, { m, children: [] as Node[] }]))
  const roots: Node[] = []
  for (const n of byId.values()) {
    const parent = n.m.manager ? byId.get(n.m.manager.uuid) : undefined
    // evita ciclo (A gerencia B e B gerencia A): quem cair num ciclo vira raiz
    let cyc = false
    for (let p: Node | undefined = parent, i = 0; p && i < 50; p = p.m.manager ? byId.get(p.m.manager.uuid) : undefined, i++) { if (p === n) { cyc = true; break } }
    if (parent && !cyc) parent.children.push(n); else roots.push(n)
  }
  const sort = (a: Node[]) => { a.sort((x, y) => x.m.name.localeCompare(y.m.name, 'pt-BR')); a.forEach((c) => sort(c.children)) }
  sort(roots)
  return roots
}

const count = (n: Node): number => n.children.reduce((s, c) => s + 1 + count(c), 0)
const matches = (n: Node, q: string): boolean =>
  `${n.m.name} ${n.m.job_title ?? ''} ${n.m.department?.name ?? ''}`.toLowerCase().includes(q) || n.children.some((c) => matches(c, q))

function Row({ n, slug, q, depth }: { n: Node; slug: string; q: string; depth: number }) {
  const [open, setOpen] = useState(depth < 1)
  if (q && !matches(n, q)) return null
  const expanded = q ? true : open
  const total = count(n)

  return (
    <li>
      <div className="flex items-center gap-2 rounded-xl py-1.5 pr-2 hover:bg-gray-50" style={{ paddingLeft: depth * 20 + 4 }}>
        {n.children.length ? (
          <button type="button" onClick={() => setOpen((o) => !o)} aria-expanded={expanded} aria-label={expanded ? 'Recolher' : 'Expandir'} className="rounded p-0.5 text-gray-400 hover:text-gray-700">
            {expanded ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
          </button>
        ) : <span className="w-5" />}
        <Link to={`/w/${slug}/pessoas/${n.m.uuid}`} className="flex min-w-0 flex-1 items-center gap-2.5">
          <Avatar src={n.m.avatar_url} name={n.m.name} size="sm" />
          <span className="min-w-0">
            <span className="block truncate text-sm font-medium text-gray-900">{n.m.name}</span>
            <span className="block truncate text-xs text-gray-500">{[n.m.job_title, n.m.department?.name].filter(Boolean).join(' · ') || '—'}</span>
          </span>
        </Link>
        {total > 0 && <span className="flex-shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">{total} {total === 1 ? 'pessoa' : 'pessoas'}</span>}
      </div>
      {expanded && n.children.length > 0 && (
        <ul className="border-l border-gray-100" style={{ marginLeft: depth * 20 + 14 }}>
          {n.children.map((c) => <Row key={c.m.uuid} n={c} slug={slug} q={q} depth={depth + 1} />)}
        </ul>
      )}
    </li>
  )
}

/** Organograma: quem responde a quem. Monta a árvore a partir do gestor de cada pessoa. */
export default function WorkspaceOrgChartPage() {
  const { slug } = useWs()
  const { data: members, isLoading } = useQuery({ queryKey: ['ws', slug, 'members'], queryFn: () => workspaceService.members(slug) })
  const [q, setQ] = useState('')
  const roots = useMemo(() => build(members ?? []), [members])
  const term = q.trim().toLowerCase()

  return (
    <div>
      <PageHeader title="Organograma" subtitle="Quem responde a quem na empresa. Clique em uma pessoa para ver o perfil." />
      <div className="relative mb-4 max-w-md">
        <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
        <input className={`${inputCls} !pl-9`} placeholder="Buscar por nome, cargo ou setor" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Buscar no organograma" />
      </div>
      {isLoading ? <Spinner /> : !roots.length ? <Empty text="Ainda não há pessoas cadastradas." /> : (
        <Panel>
          {roots.length > 1 && !term && <p className="mb-2 text-xs text-gray-400">Pessoas sem gestor definido aparecem no topo, no mesmo nível.</p>}
          <ul>{roots.map((n) => <Row key={n.m.uuid} n={n} slug={slug} q={term} depth={0} />)}</ul>
        </Panel>
      )}
    </div>
  )
}
