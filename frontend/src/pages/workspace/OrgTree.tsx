import { Link } from 'react-router-dom'
import { Minus, Plus } from 'lucide-react'
import { Avatar } from '@/components/ui/Avatar'
import type { WorkspaceMember } from '@/types/workspace.types'
import { cn } from '@/utils/cn'

export type OrgNode = { m: WorkspaceMember; children: OrgNode[] }

export function buildTree(members: WorkspaceMember[]): OrgNode[] {
  const byId = new Map(members.map((m) => [m.uuid, { m, children: [] as OrgNode[] }]))
  const roots: OrgNode[] = []
  for (const n of byId.values()) {
    const parent = n.m.manager ? byId.get(n.m.manager.uuid) : undefined
    // evita ciclo (A gerencia B e B gerencia A): quem cair num ciclo vira raiz
    let cyc = false
    for (let p: OrgNode | undefined = parent, i = 0; p && i < 50; p = p.m.manager ? byId.get(p.m.manager.uuid) : undefined, i++) { if (p === n) { cyc = true; break } }
    if (parent && !cyc) parent.children.push(n); else roots.push(n)
  }
  const sort = (a: OrgNode[]) => { a.sort((x, y) => x.m.name.localeCompare(y.m.name, 'pt-BR')); a.forEach((c) => sort(c.children)) }
  sort(roots)
  return roots
}

export const count = (n: OrgNode): number => n.children.reduce((s, c) => s + 1 + count(c), 0)
export const matches = (n: OrgNode, q: string): boolean =>
  `${n.m.name} ${n.m.job_title ?? ''} ${n.m.department?.name ?? ''}`.toLowerCase().includes(q) || n.children.some((c) => matches(c, q))

/** Linhas de ligação da árvore: líder no topo, ligado aos liderados por traços (estilo organograma clássico). */
export const TREE_CSS = `
.orgtree{--org-line:#9ca3af}
.orgtree ul{display:flex;justify-content:center;padding-top:22px;position:relative;margin:0;list-style:none}
.orgtree li{display:flex;flex-direction:column;align-items:center;position:relative;padding:22px 8px 0}
.orgtree li::before,.orgtree li::after{content:'';position:absolute;top:0;right:50%;border-top:2px solid var(--org-line);width:50%;height:22px}
.orgtree li::after{right:auto;left:50%;border-left:2px solid var(--org-line)}
.orgtree li:only-child::before,.orgtree li:only-child::after{display:none}
.orgtree li:only-child{padding-top:0}
.orgtree li:first-child::before,.orgtree li:last-child::after{border:0 none}
.orgtree li:last-child::before{border-right:2px solid var(--org-line);border-radius:0 8px 0 0}
.orgtree li:first-child::after{border-radius:8px 0 0 0}
.orgtree ul ul::before{content:'';position:absolute;top:0;left:50%;border-left:2px solid var(--org-line);height:22px}
.orgtree>ul{padding-top:0}
.orgtree>ul>li{padding-top:0}
.orgtree>ul>li::before,.orgtree>ul>li::after{display:none}
`

function Card({ n, slug, expanded, toggle, hit, me, fixed }: { n: OrgNode; slug: string; expanded: boolean; toggle: () => void; hit: boolean; me: boolean; fixed?: boolean }) {
  const total = count(n)

  return (
    <div className="relative">
      <Link
        to={`/w/${slug}/pessoas/${n.m.uuid}`}
        className={cn('flex w-44 flex-col items-center rounded-2xl border bg-white px-3 pb-4 pt-3 text-center shadow-sm transition hover:-translate-y-0.5 hover:shadow-md', hit || me ? 'border-brand-400 ring-2 ring-brand-200' : 'border-gray-200')}
      >
        <Avatar src={n.m.avatar_url} name={n.m.name} size="lg" />
        <span className="mt-2 w-full truncate text-sm font-semibold text-gray-900">{n.m.name}</span>
        <span className="w-full truncate text-xs text-brand-700">{n.m.job_title ?? 'Sem cargo'}</span>
        {n.m.department && <span className="w-full truncate text-[11px] text-gray-400">{n.m.department.name}</span>}
      </Link>
      {n.children.length > 0 && !fixed && (
        <button
          type="button" onClick={toggle} aria-expanded={expanded}
          aria-label={`${expanded ? 'Recolher' : 'Expandir'} equipe de ${n.m.name} (${total} ${total === 1 ? 'pessoa' : 'pessoas'})`}
          className="absolute -bottom-3 left-1/2 flex h-6 min-w-6 -translate-x-1/2 items-center justify-center gap-0.5 rounded-full border border-gray-300 bg-white px-1.5 text-[11px] font-semibold text-gray-600 shadow-sm hover:border-brand-400 hover:text-brand-700"
        >
          {expanded ? <Minus className="h-3 w-3" /> : <Plus className="h-3 w-3" />}{!expanded && total}
        </button>
      )}
    </div>
  )
}

function Branch({ n, slug, term, open, toggle, highlight, fixed }: { n: OrgNode; slug: string; term: string; open: Set<string>; toggle: (id: string) => void; highlight?: string; fixed?: boolean }) {
  if (term && !matches(n, term)) return null
  const expanded = term ? true : open.has(n.m.uuid)
  const self = !!term && `${n.m.name} ${n.m.job_title ?? ''} ${n.m.department?.name ?? ''}`.toLowerCase().includes(term)

  return (
    <li>
      <Card n={n} slug={slug} expanded={expanded} toggle={() => toggle(n.m.uuid)} hit={self} me={n.m.uuid === highlight} fixed={fixed} />
      {expanded && n.children.length > 0 && (
        <ul>{n.children.map((c) => <Branch key={c.m.uuid} n={c} slug={slug} term={term} open={open} toggle={toggle} highlight={highlight} fixed={fixed} />)}</ul>
      )}
    </li>
  )
}


/** Desenha a árvore (líder no topo, ligado aos liderados). `root` é a empresa quando há mais de um topo. */
export function OrgTreeView({ roots, slug, term = '', open, toggle, highlight, company, zoom = 1, fixed }: {
  roots: OrgNode[]; slug: string; term?: string; open: Set<string>; toggle: (id: string) => void; highlight?: string
  company?: { name: string; avatar_url: string | null } | null; zoom?: number; fixed?: boolean
}) {
  return (
    <>
      <style>{TREE_CSS}</style>
      <div className="orgtree mx-auto w-max min-w-full pb-6" style={{ zoom }}>
        <ul>
          {company ? (
            <li>
              <div className="flex w-52 flex-col items-center rounded-2xl bg-brand-600 px-3 py-3 text-center text-white shadow-sm">
                <Avatar src={company.avatar_url} name={company.name} size="lg" />
                <span className="mt-2 w-full truncate text-sm font-semibold">{company.name}</span>
              </div>
              <ul>{roots.map((n) => <Branch key={n.m.uuid} n={n} slug={slug} term={term} open={open} toggle={toggle} highlight={highlight} fixed={fixed} />)}</ul>
            </li>
          ) : roots.map((n) => <Branch key={n.m.uuid} n={n} slug={slug} term={term} open={open} toggle={toggle} highlight={highlight} fixed={fixed} />)}
        </ul>
      </div>
    </>
  )
}

export const withKids = (nodes: OrgNode[]): string[] => nodes.flatMap((n) => (n.children.length ? [n.m.uuid, ...withKids(n.children)] : []))
