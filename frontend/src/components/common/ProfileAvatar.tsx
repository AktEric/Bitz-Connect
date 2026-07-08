import { Link } from 'react-router-dom'
import { Avatar } from '@/components/ui/Avatar'

interface ProfileAvatarProps {
  uuid: string
  name: string
  src?: string | null
  size?: 'xs' | 'sm' | 'md' | 'lg'
}

/**
 * Clickable avatar that navigates to the user's profile page.
 * Requirements: 10.1, 10.3
 */
export function ProfileAvatar({ uuid, name, src, size = 'sm' }: ProfileAvatarProps) {
  return (
    <Link to={`/users/${uuid}`} aria-label={`Perfil de ${name}`}>
      <Avatar src={src} name={name} size={size} />
    </Link>
  )
}
