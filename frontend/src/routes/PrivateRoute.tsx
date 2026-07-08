import { Navigate } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import AppLayout from '@/components/common/AppLayout'

/**
 * Redirects unauthenticated users to /login.
 * Wraps authenticated routes in the global AppLayout (sidebar/navbar).
 * Requirements: 1.1, 1.3
 */
export default function PrivateRoute() {
  const { isAuthenticated } = useAuth()
  return isAuthenticated ? <AppLayout /> : <Navigate to="/login/pessoa" replace />
}
