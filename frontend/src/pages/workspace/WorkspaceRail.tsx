import { Fragment, useEffect } from 'react'
import { NavLink } from 'react-router-dom'
import { Menu, X, type LucideIcon } from 'lucide-react'
import { cn } from '@/utils/cn'
import { useRailTooltip } from './RailTooltip'

export interface NavItem { to: string; label: string; Icon: LucideIcon; end?: boolean; badge?: number }
export interface NavGroup { title: string; items: NavItem[] }

/** Item ativo: links com query (ex.: "ponto?aba=time") não disputam o destaque com a página base. */
const active = (isActive: boolean, to: string) => isActive && !to.includes('?')

/** Menu completo, por área (gaveta do celular e painel expandido do desktop). */
export function NavGroups({ groups, onNavigate }: { groups: NavGroup[]; onNavigate?: () => void }) {
  return (
    <nav className="space-y-5" aria-label="Menu do Workspace">
      {groups.map((g) => (
        <div key={g.title}>
          <p className="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{g.title}</p>
          <ul className="space-y-0.5">
            {g.items.map(({ to, label, Icon, end, badge }) => (
              <li key={to + label}>
                <NavLink
                  to={to}
                  end={end}
                  onClick={onNavigate}
                  className={({ isActive }) =>
                    cn(
                      'flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition-colors',
                      active(isActive, to) ? 'bg-brand-50 text-brand-700' : 'text-gray-600 hover:bg-gray-100',
                    )
                  }
                >
                  <Icon className="h-[18px] w-[18px]" />
                  <span className="flex-1 truncate">{label}</span>
                  {!!badge && badge > 0 && (
                    <span className="rounded-full bg-brand-600 px-1.5 py-0.5 text-[11px] font-semibold leading-none text-white">{badge}</span>
                  )}
                </NavLink>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </nav>
  )
}

/** Barra de ícones fixa à esquerda (desktop). O botão ☰ abre o menu completo ao lado. */
export default function WorkspaceRail({ groups, expanded, onToggle }: { groups: NavGroup[]; expanded: boolean; onToggle: () => void }) {
  const tip = useRailTooltip('right')

  useEffect(() => {
    if (!expanded) return
    const esc = (e: KeyboardEvent) => { if (e.key === 'Escape') onToggle() }
    window.addEventListener('keydown', esc)
    return () => window.removeEventListener('keydown', esc)
  }, [expanded, onToggle])

  return (
    <>
      <aside className="ws-rail fixed inset-y-0 left-0 z-50 hidden w-[68px] flex-col items-center bg-ink-900 py-3 lg:flex" aria-label="Menu">
        <button
          type="button" onClick={onToggle} aria-expanded={expanded} aria-label={expanded ? 'Fechar menu' : 'Abrir menu completo'}
          className="mb-3 flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl text-white hover:bg-white/10"
        >
          {expanded ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
        </button>
        <nav className="ws-rail-scroll flex w-full flex-1 flex-col items-center gap-1 overflow-y-auto pb-2" aria-label="Atalhos do menu">
          {groups.map((g, gi) => (
            <Fragment key={g.title}>
              {gi > 0 && <span className="my-1.5 h-px w-8 flex-shrink-0 bg-white/15" aria-hidden />}
              {g.items.map(({ to, label, Icon, end, badge }) => (
                <NavLink
                  key={to + label}
                  to={to}
                  end={end}
                  aria-label={label}
                  onMouseEnter={(e) => tip.show(e, label)}
                  onMouseLeave={tip.hide}
                  onFocus={(e) => tip.show(e, label)}
                  onBlur={tip.hide}
                  onClick={tip.hide}
                  className={({ isActive }) =>
                    cn(
                      'relative flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl transition-colors',
                      active(isActive, to) ? 'bg-brand-600 text-white shadow-sm' : 'text-gray-400 hover:bg-white/10 hover:text-white',
                    )
                  }
                >
                  <Icon className="h-5 w-5" />
                  {!!badge && badge > 0 && (
                    <span className="absolute right-1 top-1 min-w-[16px] rounded-full bg-amber-400 px-1 text-center text-[10px] font-bold leading-4 text-ink-900">{badge > 9 ? '9+' : badge}</span>
                  )}
                </NavLink>
              ))}
            </Fragment>
          ))}
        </nav>
      </aside>
      {tip.node}

      {expanded && (
        <div className="fixed inset-0 z-[45] hidden lg:block" role="dialog" aria-modal="true" aria-label="Menu completo">
          <div className="absolute inset-0 bg-black/20" onClick={onToggle} />
          <div className="absolute inset-y-0 left-[68px] w-64 overflow-y-auto border-r border-gray-200 bg-white p-4 shadow-xl">
            <NavGroups groups={groups} onNavigate={onToggle} />
          </div>
        </div>
      )}
    </>
  )
}
