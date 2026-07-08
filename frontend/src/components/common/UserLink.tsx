import { Link } from 'react-router-dom'
import { cn } from '@/utils/cn'

interface UserLinkProps {
  uuid: string
  name: string
  className?: string
  children?: React.ReactNode
}

/**
 * Clickable link to a user's profile page.
 * Requirements: 10.1, 10.3
 */
export function UserLink({ uuid, name, className, children }: UserLinkProps) {
  return (
    <Link
      to={`/users/${uuid}`}
      className={cn('font-medium text-gray-900 hover:underline', className)}
    >
      {children ?? name}
    </Link>
  )
}
