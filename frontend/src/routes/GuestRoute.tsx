import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useHotelAuth } from '@/hooks/useHotelAuth'

/**
 * Redirects already-authenticated users away from guest routes.
 * - type='pessoa': redirects to /feed if authenticated as Pessoa
 * - type='hotel': redirects to /hotel/:slug/dashboard if authenticated as Hotel
 * Requirements: 1.8, 1.9
 */
type GuestRouteProps = { type: 'pessoa' | 'hotel' }

export default function GuestRoute({ type }: GuestRouteProps) {
  const { isAuthenticated } = useAuth()
  const { isHotelAuthenticated, session } = useHotelAuth()

  if (type === 'pessoa' && isAuthenticated)
    return <Navigate to="/feed" replace />

  if (type === 'hotel' && isHotelAuthenticated)
    return <Navigate to={`/hotel/${session?.hotelSlug}/dashboard`} replace />

  return <Outlet />
}
