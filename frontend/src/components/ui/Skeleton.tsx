import { cn } from '@/utils/cn'

interface SkeletonProps {
  className?: string
  rounded?: 'sm' | 'md' | 'lg' | 'full'
}

export function Skeleton({ className, rounded = 'md' }: SkeletonProps) {
  const r = { sm: 'rounded', md: 'rounded-lg', lg: 'rounded-xl', full: 'rounded-full' }
  return (
    <div
      className={cn(
        'relative overflow-hidden bg-gray-100',
        r[rounded],
        className,
      )}
    >
      <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/60 to-transparent animate-shimmer" style={{ backgroundSize: '200% 100%' }} />
    </div>
  )
}

export function SkeletonCard() {
  return (
    <div className="rounded-2xl border border-gray-100 bg-white p-5 shadow-card space-y-3">
      <div className="flex items-center gap-3">
        <Skeleton className="h-10 w-10" rounded="full" />
        <div className="flex-1 space-y-2">
          <Skeleton className="h-3 w-32" />
          <Skeleton className="h-2.5 w-24" />
        </div>
      </div>
      <Skeleton className="h-3 w-full" />
      <Skeleton className="h-3 w-4/5" />
      <Skeleton className="h-3 w-3/5" />
    </div>
  )
}

export function SkeletonText({ lines = 3 }: { lines?: number }) {
  return (
    <div className="space-y-2">
      {Array.from({ length: lines }).map((_, i) => (
        <Skeleton key={i} className={`h-3 ${i === lines - 1 ? 'w-3/5' : 'w-full'}`} />
      ))}
    </div>
  )
}

/**
 * Skeleton placeholder for an EmployeeCard row.
 * Requirements: 7.2
 */
export function EmployeeCardSkeleton() {
  return (
    <div className="flex items-center gap-4 rounded-2xl border border-gray-100 bg-white p-4">
      <Skeleton className="h-12 w-12 shrink-0" rounded="full" />
      <div className="flex-1 space-y-2">
        <Skeleton className="h-3.5 w-36" />
        <Skeleton className="h-3 w-24" />
        <Skeleton className="h-2.5 w-20" />
      </div>
    </div>
  )
}
