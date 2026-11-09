import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ChevronsDownUp, ChevronsUpDown, Minus, Plus, Search } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { WorkspaceMember } from '@/types/workspace.types'
import { cn } from '@/utils/cn'
import { Empty, PageHeader, Panel, Spinner, btnGhost, inputCls, useWs } from './ui'

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

/** Linhas de ligação da árvore: líder no topo, ligado aos liderados por traços (estilo organograma clássico). */
const TREE_CSS = `
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

function Card({ n, slug, expanded, toggle, hit }: { n: Node; slug: string; expanded: boolean; toggle: () => void; hit: boolean }) {
  const total = count(n)

  return (
    <div className="relative">
      <Link
        to={`/w/${slug}/pessoas/${n.m.uuid}`}
        className={cn('flex w-44 flex-col items-center rounded-2xl border bg-white px-3 pb-4 pt-3 text-center shadow-sm transition hover:-translate-y-0.5 hover:shadow-md', hit ? 'border-brand-400 ring-2 ring-brand-200' : 'border-gray-200')}
      >
        <Avatar src={n.m.avatar_url} name={n.m.name} size="lg" />
        <span className="mt-2 w-full truncate text-sm font-semibold text-gray-900">{n.m.name}</span>
        <span className="w-full truncate text-xs text-brand-700">{n.m.job_title ?? 'Sem cargo'}</span>
        {n.m.department && <span className="w-full truncate text-[11px] text-gray-400">{n.m.department.name}</span>}
      </Link>
      {n.children.length > 0 && (
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

function Branch({ n, slug, term, open, toggle }: { n: Node; slug: string; term: string; open: Set<string>; toggle: (id: string) => void }) {
  if (term && !matches(n, term)) return null
  const expanded = term ? true : open.has(n.m.uuid)
  const self = !!term && `${n.m.name} ${n.m.job_title ?? ''} ${n.m.department?.name ?? ''}`.toLowerCase().includes(term)

  return (
    <li>
      <Card n={n} slug={slug} expanded={expanded} toggle={() => toggle(n.m.uuid)} hit={self} />
      {expanded && n.children.length > 0 && (
        <ul>{n.children.map((c) => <Branch key={c.m.uuid} n={c} slug={slug} term={term} open={open} toggle={toggle} />)}</ul>
      )}
    </li>
  )
}

const withKids = (nodes: Node[]): string[] => nodes.flatMap((n) => (n.children.length ? [n.m.uuid, ...withKids(n.children)] : []))

/** Organograma em árvore: do cargo mais alto ao mais baixo, ligado pelos respectivos líderes. */
export default function WorkspaceOrgChartPage() {
  const { slug, info } = useWs()
  const { data: members, isLoading } = useQuery({ queryKey: ['ws', slug, 'members'], queryFn: () => workspaceService.members(slug) })
  const [q, setQ] = useState('')
  const [zoom, setZoom] = useState(1)
  const roots = useMemo(() => build(members ?? []), [members])
  const [openIds, setOpenIds] = useState<Set<string> | null>(null)
  const term = q.trim().toLowerCase()

  // por padrão mostra a diretoria e a camada logo abaixo; o resto abre pelo botão de cada líder
  const open = useMemo(() => openIds ?? new Set(roots.flatMap((r) => [r.m.uuid, ...r.children.filter((c) => c.children.length).map((c) => c.m.uuid)])), [openIds, roots])
  const toggle = (id: string) => setOpenIds((cur) => { const n = new Set(cur ?? open); if (n.has(id)) n.delete(id); else n.add(id); return n })
  const several = roots.length > 1

  return (
    <div>
      <PageHeader title="Organograma" subtitle="Do cargo mais alto ao mais baixo, ligado pelos respectivos líderes. Clique em uma pessoa para ver o perfil." />
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <div className="relative min-w-[14rem] max-w-md flex-1">
          <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
          <input className={`${inputCls} !pl-9`} placeholder="Buscar por nome, cargo ou setor" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Buscar no organograma" />
        </div>
        <button type="button" className={btnGhost} onClick={() => setOpenIds(new Set(withKids(roots)))}><ChevronsUpDown className="h-4 w-4" /> Expandir tudo</button>
        <button type="button" className={btnGhost} onClick={() => setOpenIds(new Set(roots.filter((r) => r.children.length).map((r) => r.m.uuid)))}><ChevronsDownUp className="h-4 w-4" /> Recolher</button>
        <div className="ml-auto inline-flex items-center rounded-xl border border-gray-200 bg-white" role="group" aria-label="Zoom">
          <button type="button" className="px-2.5 py-2 text-gray-600 hover:bg-gray-50 disabled:opacity-40" onClick={() => setZoom((z) => Math.max(0.5, +(z - 0.1).toFixed(1)))} disabled={zoom <= 0.5} aria-label="Diminuir"><Minus className="h-4 w-4" /></button>
          <button type="button" className="min-w-[3.25rem] px-1 text-xs font-medium text-gray-600 hover:bg-gray-50" onClick={() => setZoom(1)} aria-label="Zoom 100%">{Math.round(zoom * 100)}%</button>
          <button type="button" className="px-2.5 py-2 text-gray-600 hover:bg-gray-50 disabled:opacity-40" onClick={() => setZoom((z) => Math.min(1.5, +(z + 0.1).toFixed(1)))} disabled={zoom >= 1.5} aria-label="Aumentar"><Plus className="h-4 w-4" /></button>
        </div>
      </div>

      {isLoading ? <Spinner /> : !roots.length ? <Empty text="Ainda não há pessoas cadastradas." /> : (
        <Panel className="overflow-x-auto !p-4 sm:!p-6">
          <style>{TREE_CSS}</style>
          {several && !term && <p className="mb-3 text-xs text-gray-400">Pessoas sem gestor definido aparecem ligadas à empresa, no mesmo nível.</p>}
          <div className="orgtree mx-auto w-max min-w-full pb-6" style={{ zoom }}>
            <ul>
              {several ? (
                <li>
                  <div className="flex w-52 flex-col items-center rounded-2xl bg-brand-600 px-3 py-3 text-center text-white shadow-sm">
                    <Avatar src={info.hotel.avatar_url} name={info.hotel.name} size="lg" />
                    <span className="mt-2 w-full truncate text-sm font-semibold">{info.hotel.name}</span>
                  </div>
                  <ul>{roots.map((n) => <Branch key={n.m.uuid} n={n} slug={slug} term={term} open={open} toggle={toggle} />)}</ul>
                </li>
              ) : roots.map((n) => <Branch key={n.m.uuid} n={n} slug={slug} term={term} open={open} toggle={toggle} />)}
            </ul>
          </div>
        </Panel>
      )}
    </div>
  )
}
