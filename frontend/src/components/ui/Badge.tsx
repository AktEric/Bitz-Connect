import { cn } from '@/utils/cn'

type BadgeVariant = 'primary' | 'green' | 'amber' | 'red' | 'gray' | 'blue'

const variants: Record<BadgeVariant, string> = {
  primary: 'bg-violet-100 text-violet-700',
  green:   'bg-green-100 text-green-700',
  amber:   'bg-amber-100 text-amber-700',
  red:     'bg-red-100 text-red-600',
  gray:    'bg-gray-100 text-gray-600',
  blue:    'bg-blue-100 text-blue-700',
}

interface BadgeProps {
  children: React.ReactNode
  variant?: BadgeVariant
  dot?: boolean
  className?: string
}

export function Badge({ children, variant = 'gray', dot, className }: BadgeProps) {
  return (
    <span className={cn('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium', variants[variant], className)}>
      {dot && <span className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />}
      {children}
    </span>
  )
}

interface NotifBadgeProps {
  count: number
  max?: number
  className?: string
}

export function NotifBadge({ count, max = 9, className }: NotifBadgeProps) {
  if (count <= 0) return null
  return (
    <span className={cn(
      'absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white shadow-sm',
      className,
    )}>
      {count > max ? `${max}+` : count}
    </span>
  )
}
