import { useState, useRef, useCallback } from 'react'
import { useMediaQuery } from '@/hooks/useMediaQuery'

interface HoverCardProps {
  /** The trigger element (link, avatar, etc.) */
  children: React.ReactNode
  /** The card content to show on hover */
  card: React.ReactNode
  /** Delay in ms before showing (default 300) */
  delay?: number
}

/**
 * Desktop-only hover card wrapper.
 * Shows `card` after hovering over `children` for `delay` ms.
 * Hidden on viewports < 1024px.
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5
 */
export function HoverCard({ children, card, delay = 300 }: HoverCardProps) {
  const isDesktop = useMediaQuery('(min-width: 1024px)')
  const [visible, setVisible] = useState(false)
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  const handleMouseEnter = useCallback(() => {
    if (!isDesktop) return
    timerRef.current = setTimeout(() => setVisible(true), delay)
  }, [isDesktop, delay])

  const handleMouseLeave = useCallback(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current)
      timerRef.current = null
    }
    setVisible(false)
  }, [])

  if (!isDesktop) {
    return <>{children}</>
  }

  return (
    <span
      className="relative inline-block"
      onMouseEnter={handleMouseEnter}
      onMouseLeave={handleMouseLeave}
    >
      {children}
      {visible && (
        <span className="absolute left-0 top-full z-50 mt-1 block">
          {card}
        </span>
      )}
    </span>
  )
}
