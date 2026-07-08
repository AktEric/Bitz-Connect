import { useAuthContext } from '@/contexts/AuthContext'

/**
 * Convenience hook to access authentication state and actions.
 * Requirements: 1.1, 1.3, 1.5
 */
export function useAuth() {
  return useAuthContext()
}
