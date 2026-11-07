import { Navigate } from 'react-router-dom'
import { lazy } from 'react'
import { useMyWorkspaces } from '@/hooks/useWorkspace'

const FeedPage = lazy(() => import('@/pages/feed/FeedPage'))

/** Quem já tem vínculo aprovado com uma empresa começa no Workspace; os demais seguem para a rede profissional. */
export default function HomeGate() {
  const { data, isLoading } = useMyWorkspaces()
  if (isLoading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <div className="h-7 w-7 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }
  if (data?.length) return <Navigate to={data.length === 1 ? `/w/${data[0].hotel.slug}` : '/w'} replace />
  return <FeedPage />
}
