import { Sparkles } from 'lucide-react'
import { cn } from '@/utils/cn'
import type { PlanTier } from '@/types/subscription.types'

interface PremiumBadgeProps {
  plan?: PlanTier | null
  /** Compact icon-only pill for dense contexts (comments, search rows). */
  compact?: boolean
  className?: string
}

const LABELS: Record<Exclude<PlanTier, 'free'>, string> = {
  pro_consultor: 'PRO',
  pro_empresa: 'Empresa PRO',
}

/**
 * Discrete PRO identifier shown across the app (profile, comments, videos,
 * search, etc. — §9). Renders nothing for the free plan, so callers can pass
 * a user's/hotel's plan unconditionally.
 */
export function PremiumBadge({ plan, compact = false, className }: PremiumBadgeProps) {
  if (!plan || plan === 'free') return null

  const label = LABELS[plan]

  return (
    <span
      title={`Bitz Connect ${label}`}
      className={cn(
        'inline-flex items-center gap-0.5 rounded-full bg-gradient-to-r from-amber-400 to-amber-500',
        'font-semibold text-white shadow-sm',
        compact ? 'px-1 py-0.5 text-[9px]' : 'px-1.5 py-0.5 text-[10px]',
        className,
      )}
    >
      <Sparkles className={compact ? 'h-2.5 w-2.5' : 'h-3 w-3'} />
      {!compact && label}
    </span>
  )
}
