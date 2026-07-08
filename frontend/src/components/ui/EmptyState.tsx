import { cn } from '@/utils/cn'
import { Button } from './Button'

interface EmptyStateProps {
  title: string
  description?: string
  icon?: React.ReactNode
  action?: { label: string; onClick: () => void }
  className?: string
}

export function EmptyState({ title, description, icon, action, className }: EmptyStateProps) {
  return (
    <div
      role="status"
      aria-label={title}
      className={cn(
        'flex flex-col items-center justify-center gap-3 py-12 px-6 text-center',
        className,
      )}
    >
      {icon && (
        <div className="flex items-center justify-center text-gray-300" aria-hidden="true">
          {icon}
        </div>
      )}

      <div className="space-y-1">
        <p className="text-sm font-semibold text-gray-700">{title}</p>
        {description && (
          <p className="text-sm text-gray-400">{description}</p>
        )}
      </div>

      {action && (
        <Button
          variant="outline"
          size="sm"
          onClick={action.onClick}
        >
          {action.label}
        </Button>
      )}
    </div>
  )
}
