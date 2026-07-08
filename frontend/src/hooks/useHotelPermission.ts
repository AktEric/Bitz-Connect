import { useAuth } from '@/hooks/useAuth'
import { useHotelAdmins } from '@/hooks/useHotel'

/**
 * Returns true if the currently authenticated user is an admin of the hotel
 * identified by the given slug. Returns false while loading or if data is
 * unavailable.
 *
 * Requirements: 6.1, 6.3
 */
export function useIsHotelAdmin(hotelSlug: string): boolean {
  const { user } = useAuth()
  const { data: admins } = useHotelAdmins(hotelSlug)
  return admins?.some((admin) => admin.uuid === user?.uuid) ?? false
}
