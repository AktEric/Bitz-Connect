import { Link } from 'react-router-dom'
import { Avatar } from '@/components/ui/Avatar'

interface CompanyAvatarProps {
  slug: string
  name: string
  logoUrl?: string | null
  size?: 'xs' | 'sm' | 'md' | 'lg'
}

/**
 * Clickable avatar that navigates to the hotel's profile page.
 * Requirements: 10.2, 10.4
 */
export function CompanyAvatar({ slug, name, logoUrl, size = 'sm' }: CompanyAvatarProps) {
  return (
    <Link to={`/hotels/${slug}`} aria-label={`Perfil de ${name}`}>
      <Avatar src={logoUrl} name={name} size={size} />
    </Link>
  )
}
