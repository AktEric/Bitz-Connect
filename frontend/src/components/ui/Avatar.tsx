import { cn } from '@/utils/cn'

type Size = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'

const sizes: Record<Size, string> = {
  xs:  'h-6  w-6  text-[10px]',
  sm:  'h-8  w-8  text-xs',
  md:  'h-10 w-10 text-sm',
  lg:  'h-12 w-12 text-base',
  xl:  'h-16 w-16 text-xl',
  '2xl': 'h-24 w-24 text-3xl',
}

interface AvatarProps {
  src?: string | null
  name: string
  size?: Size
  className?: string
  ring?: boolean
}

function initials(name: string) {
  return name.split(' ').slice(0, 2).map((w) => w[0]).join('').toUpperCase()
}

export function Avatar({ src, name, size = 'md', className, ring }: AvatarProps) {
  return (
    <div
      className={cn(
        'flex-shrink-0 overflow-hidden rounded-full bg-brand-100',
        sizes[size],
        ring && 'ring-2 ring-white ring-offset-0',
        className,
      )}
    >
      {src ? (
        <img src={src} alt={name} className="h-full w-full object-cover" />
      ) : (
        <div className="flex h-full w-full items-center justify-center font-semibold text-brand-600">
          {initials(name)}
        </div>
      )}
    </div>
  )
}
