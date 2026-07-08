import { cn } from '@/utils/cn'
import { type HTMLAttributes, forwardRef } from 'react'

interface CardProps extends HTMLAttributes<HTMLDivElement> {
  hover?: boolean
  noPad?: boolean
}

export const Card = forwardRef<HTMLDivElement, CardProps>(
  ({ hover, noPad, children, className, ...props }, ref) => (
    <div
      ref={ref}
      className={cn(
        'rounded-2xl border border-gray-100 bg-white shadow-card transition-shadow duration-200',
        hover && 'hover:shadow-card-hover cursor-pointer',
        !noPad && 'p-5',
        className,
      )}
      {...props}
    >
      {children}
    </div>
  ),
)
Card.displayName = 'Card'

export function CardTitle({ children, className }: { children: React.ReactNode; className?: string }) {
  return <h3 className={cn('text-sm font-semibold text-gray-900', className)}>{children}</h3>
}

export function CardDivider() {
  return <div className="my-3 h-px bg-gray-100" />
}
