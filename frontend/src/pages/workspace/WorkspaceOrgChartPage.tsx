import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ChevronsDownUp, ChevronsUpDown, Minus, Plus, Search } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { OrgTreeView, buildTree, withKids } from './OrgTree'
import { Empty, PageHeader, Panel, Spinner, btnGhost, inputCls, useWs } from './ui'

/** Organograma em árvore: do cargo mais alto ao mais baixo, ligado pelos respectivos líderes. */
export default function WorkspaceOrgChartPage() {
  const { slug, info } = useWs()
  const { data: members, isLoading } = useQuery({ queryKey: ['ws', slug, 'members'], queryFn: () => workspaceService.members(slug) })
  const [q, setQ] = useState('')
  const [zoom, setZoom] = useState(1)
  const roots = useMemo(() => buildTree(members ?? []), [members])
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
          {several && !term && <p className="mb-3 text-xs text-gray-400">Pessoas sem gestor definido aparecem ligadas à empresa, no mesmo nível.</p>}
          <OrgTreeView roots={roots} slug={slug} term={term} open={open} toggle={toggle} zoom={zoom} company={several ? { name: info.hotel.name, avatar_url: info.hotel.avatar_url } : null} />
        </Panel>
      )}
    </div>
  )
}
