import { useEffect, useRef, useState } from 'react'
import { NavLink } from 'react-router-dom'
import { Check, Plus } from 'lucide-react'
import { cn } from '@/utils/cn'
import type { NavItem } from './WorkspaceRail'

const MAX = 8
const DEFAULTS = ['', 'mural', 'ponto', 'recebimentos', 'solicitacoes']
const keyOf = (slug: string) => `ws-shortcuts:${slug}`

/** Atalhos escolhidos pela pessoa, por empresa. Ficam no navegador (sem servidor por enquanto). */
function load(slug: string): string[] {
  try {
    const v = JSON.parse(localStorage.getItem(keyOf(slug)) ?? 'null')
    if (Array.isArray(v) && v.every((x) => typeof x === 'string')) return v
  } catch { /* storage bloqueado ou valor inválido */ }
  return DEFAULTS
}

/** Barra horizontal de atalhos (desktop), abaixo do topo. "+ Adicionar atalhos" escolhe quais aparecem. */
export default function ShortcutsBar({ slug, items }: { slug: string; items: NavItem[] }) {
  const [chosen, setChosen] = useState<string[]>(() => load(slug))
  const [picker, setPicker] = useState(false)
  const box = useRef<HTMLDivElement>(null)

  useEffect(() => { setChosen(load(slug)) }, [slug])
  useEffect(() => {
    if (!picker) return
    const close = (e: MouseEvent) => { if (!box.current?.contains(e.target as Node)) setPicker(false) }
    const esc = (e: KeyboardEvent) => { if (e.key === 'Escape') setPicker(false) }
    document.addEventListener('mousedown', close)
    document.addEventListener('keydown', esc)
    return () => { document.removeEventListener('mousedown', close); document.removeEventListener('keydown', esc) }
  }, [picker])

  const save = (next: string[]) => {
    setChosen(next)
    try { localStorage.setItem(keyOf(slug), JSON.stringify(next)) } catch { /* ignora */ }
  }
  const toggle = (to: string) => save(chosen.includes(to) ? chosen.filter((t) => t !== to) : [...chosen, to].slice(0, MAX))

  // na ordem escolhida; some o que a pessoa não pode mais acessar
  const byTo = new Map(items.map((i) => [i.to, i]))
  const shown = chosen.map((t) => byTo.get(t)).filter((i): i is NavItem => !!i)

  return (
    <div className="sticky top-14 z-30 hidden h-12 items-center gap-2 border-b border-gray-200 bg-white px-5 lg:flex 2xl:px-8">
      <nav className="flex min-w-0 flex-1 items-center gap-1 overflow-x-auto" aria-label="Meus atalhos">
        {shown.map(({ to, label, Icon, end, badge }) => (
          <NavLink
            key={to + label}
            to={to}
            end={end}
            className={({ isActive }) =>
              cn(
                'relative inline-flex flex-shrink-0 items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium transition-colors',
                isActive && !to.includes('?') ? 'bg-brand-600 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-100',
              )
            }
          >
            <Icon className="h-4 w-4" /> {label}
            {!!badge && badge > 0 && <span className="h-2 w-2 rounded-full bg-amber-400" aria-label={`${badge} pendente(s)`} />}
          </NavLink>
        ))}
      </nav>
      <div ref={box} className="relative flex-shrink-0">
        <button
          type="button" onClick={() => setPicker((v) => !v)} aria-expanded={picker}
          className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100"
        >
          <Plus className="h-4 w-4" /> Adicionar atalhos
        </button>
        {picker && (
          <div className="absolute right-0 mt-2 max-h-[70vh] w-72 overflow-y-auto rounded-2xl border border-gray-100 bg-white p-2 shadow-xl">
            <p className="px-3 pb-1 pt-2 text-xs text-gray-400">Escolha até {MAX} atalhos ({chosen.filter((t) => byTo.has(t)).length}/{MAX})</p>
            <ul>
              {items.map(({ to, label, Icon }) => {
                const on = chosen.includes(to)
                const full = !on && chosen.length >= MAX
                return (
                  <li key={to + label}>
                    <button
                      type="button" onClick={() => toggle(to)} disabled={full} aria-pressed={on}
                      className="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40"
                    >
                      <Icon className="h-4 w-4 text-gray-400" />
                      <span className="flex-1 truncate">{label}</span>
                      <span className={cn('flex h-5 w-5 items-center justify-center rounded-md border', on ? 'border-brand-600 bg-brand-600 text-white' : 'border-gray-300')}>
                        {on && <Check className="h-3.5 w-3.5" />}
                      </span>
                    </button>
                  </li>
                )
              })}
            </ul>
            <button type="button" onClick={() => save(DEFAULTS)} className="mt-1 w-full rounded-xl px-3 py-2 text-left text-xs font-medium text-brand-600 hover:bg-brand-50">
              Restaurar atalhos padrão
            </button>
          </div>
        )}
      </div>
    </div>
  )
}
