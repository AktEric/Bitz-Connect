import { Link } from 'react-router-dom'
import { cn } from '@/utils/cn'

interface CompanyLinkProps {
  slug: string
  name: string
  className?: string
  children?: React.ReactNode
}

/**
 * Clickable link to a hotel's profile page.
 * Requirements: 10.2, 10.4
 */
export function CompanyLink({ slug, name, className, children }: CompanyLinkProps) {
  return (
    <Link
      to={`/hotels/${slug}`}
      className={cn('font-medium text-gray-900 hover:underline', className)}
    >
      {children ?? name}
    </Link>
  )
}
