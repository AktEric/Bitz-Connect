import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { CalendarDays, ClipboardList, CornerDownLeft, Newspaper, Search, User as UserIcon, Compass } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { cn } from '@/utils/cn'

export interface PaletteLink { to: string; label: string }

interface Row { key: string; section: string; title: string; hint?: string; Icon: typeof Search; go: string }

const plain = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/** Busca global (Ctrl/Cmd+K): atalhos para as telas e resultados do servidor (pessoas, mural, eventos, solicitações). */
export default function CommandPalette({ slug, pages, onClose }: { slug: string; pages: PaletteLink[]; onClose: () => void }) {
  const navigate = useNavigate()
  const [q, setQ] = useState('')
  const [debounced, setDebounced] = useState('')
  const [active, setActive] = useState(0)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => { inputRef.current?.focus() }, [])
  useEffect(() => {
    const t = setTimeout(() => setDebounced(q.trim()), 250)
    return () => clearTimeout(t)
  }, [q])

  const enabled = debounced.length >= 2
  const { data, isFetching } = useQuery({
    queryKey: ['ws', slug, 'search', debounced],
    queryFn: () => workspaceService.search(slug, debounced),
    enabled,
    staleTime: 30_000,
  })

  const rows = useMemo<Row[]>(() => {
    const term = plain(q.trim())
    const out: Row[] = []
    const matchedPages = pages.filter((p) => !term || plain(p.label).includes(term)).slice(0, term ? 5 : 8)
    matchedPages.forEach((p) => out.push({ key: `p-${p.to}`, section: 'Ir para', title: p.label, Icon: Compass, go: `/w/${slug}/${p.to}` }))
    if (enabled && data) {
      data.people.forEach((p) => out.push({ key: `u-${p.uuid}`, section: 'Pessoas', title: p.name, hint: [p.job_title, p.department].filter(Boolean).join(' · '), Icon: UserIcon, go: `/w/${slug}/equipe?q=${encodeURIComponent(p.name)}` }))
      data.posts.forEach((p) => out.push({ key: `m-${p.uuid}`, section: 'Mural', title: p.excerpt, hint: p.author ?? undefined, Icon: Newspaper, go: `/w/${slug}/mural` }))
      data.events.forEach((e) => out.push({ key: `e-${e.uuid}`, section: 'Calendário', title: e.title, hint: new Date(`${e.date}T12:00:00`).toLocaleDateString('pt-BR'), Icon: CalendarDays, go: `/w/${slug}/calendario?d=${e.date}` }))
      data.requests.forEach((r) => out.push({ key: `r-${r.uuid}`, section: 'Solicitações', title: r.title, hint: r.requester ?? undefined, Icon: ClipboardList, go: `/w/${slug}/solicitacoes` }))
    }
    return out
  }, [q, pages, enabled, data, slug])

  useEffect(() => { setActive(0) }, [rows.length, debounced])

  const choose = (r: Row | undefined) => { if (r) { onClose(); navigate(r.go) } }
  const onKey = (e: React.KeyboardEvent) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => Math.min(rows.length - 1, a + 1)) }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(0, a - 1)) }
    else if (e.key === 'Enter') { e.preventDefault(); choose(rows[active]) }
    else if (e.key === 'Escape') { onClose() }
  }

  let lastSection = ''
  return (
    <div className="fixed inset-0 z-[85] flex items-start justify-center bg-black/40 p-4 pt-[12vh]" role="dialog" aria-modal="true" aria-label="Busca" onClick={onClose}>
      <div className="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center gap-2 border-b border-gray-100 px-4">
          <Search className="h-4 w-4 text-gray-400" />
          <input
            ref={inputRef} value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={onKey}
            placeholder="Buscar pessoas, publicações, eventos, solicitações ou telas…"
            aria-label="Buscar" role="combobox" aria-expanded="true" aria-controls="palette-list"
            className="h-12 w-full border-0 bg-transparent text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0"
          />
          <kbd className="hidden rounded border border-gray-200 px-1.5 py-0.5 text-[10px] text-gray-400 sm:block">Esc</kbd>
        </div>
        <ul id="palette-list" role="listbox" className="max-h-[50vh] overflow-y-auto p-2">
          {rows.length === 0 && (
            <li className="px-3 py-8 text-center text-sm text-gray-400">{enabled ? (isFetching ? 'Buscando…' : 'Nada encontrado.') : 'Digite ao menos 2 letras.'}</li>
          )}
          {rows.map((r, i) => {
            const header = r.section !== lastSection
            lastSection = r.section
            return (
              <li key={r.key} role="presentation">
                {header && <p className="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{r.section}</p>}
                <button
                  type="button" role="option" aria-selected={i === active}
                  onMouseEnter={() => setActive(i)} onClick={() => choose(r)}
                  className={cn('flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm', i === active ? 'bg-violet-50 text-violet-900' : 'text-gray-700')}
                >
                  <r.Icon className="h-4 w-4 flex-shrink-0 text-gray-400" />
                  <span className="min-w-0 flex-1 truncate">{r.title}{r.hint && <span className="ml-2 text-xs text-gray-400">{r.hint}</span>}</span>
                  {i === active && <CornerDownLeft className="h-3.5 w-3.5 text-violet-400" />}
                </button>
              </li>
            )
          })}
        </ul>
      </div>
    </div>
  )
}
