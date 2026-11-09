import { useState } from 'react'

interface Tip { label: string; top: number; x: number; side: 'right' | 'left' }

/**
 * Dica flutuante das barras de ícones. Fica em `position: fixed` para não ser cortada
 * pela rolagem das barras (overflow-y também corta na horizontal).
 */
export function useRailTooltip(side: 'right' | 'left') {
  const [tip, setTip] = useState<Tip | null>(null)
  const show = (e: { currentTarget: HTMLElement }, label: string) => {
    const r = e.currentTarget.getBoundingClientRect()
    setTip({ label, top: r.top + r.height / 2, x: side === 'right' ? r.right + 10 : window.innerWidth - r.left + 10, side })
  }
  const hide = () => setTip(null)
  const node = tip && (
    <div
      role="tooltip"
      style={{ top: tip.top, [tip.side === 'right' ? 'left' : 'right']: tip.x }}
      className="pointer-events-none fixed z-[60] -translate-y-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs font-medium text-white shadow-lg"
    >
      {tip.label}
    </div>
  )

  return { show, hide, node }
}
