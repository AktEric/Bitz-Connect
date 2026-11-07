import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'

/** Rota autenticada do Workspace: não usa o AppLayout da rede profissional (o Workspace tem casca própria). */
export default function WorkspaceRoute() {
  const { isAuthenticated } = useAuth()
  return isAuthenticated ? <Outlet /> : <Navigate to="/entrar" replace />
}
