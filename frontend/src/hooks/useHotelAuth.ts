import { useHotelAuthContext } from '@/contexts/HotelAuthContext'

/**
 * Convenience hook to access hotel session state and actions.
 * Requirements: 3.7
 */
export function useHotelAuth() {
  return useHotelAuthContext()
}
