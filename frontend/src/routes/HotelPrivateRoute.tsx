import { Navigate } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useHotelAuth } from '@/hooks/useHotelAuth'
import HotelLayout from '@/components/hotel/HotelLayout'

/**
 * Guards hotel routes by checking both general authentication and an active hotel session.
 * Redirects unauthenticated users or users without an active hotel session to /login/hotel.
 * Wraps authenticated hotel routes in HotelLayout.
 * Requirements: 1.7, 3.5
 */
export default function HotelPrivateRoute() {
  const { isAuthenticated } = useAuth()
  const { isHotelAuthenticated } = useHotelAuth()

  if (!isAuthenticated) return <Navigate to="/login/hotel" replace />
  if (!isHotelAuthenticated) return <Navigate to="/login/hotel" replace />

  return <HotelLayout />
}
