import { useQuery } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import workspaceService from '@/services/workspace.service'
import { useAuth } from '@/hooks/useAuth'
import ProfileView from './ProfileView'
import { Spinner, useWs } from './ui'

/** Meu perfil (sem parâmetro) ou o perfil de um colega em /pessoas/:uuid (o RH também edita a ficha cadastral aqui). */
export default function WorkspaceProfilePage() {
  const { slug } = useWs()
  const { uuid } = useParams()
  const { user } = useAuth()
  const { data: mine } = useQuery({ queryKey: ['ws', slug, 'myprofile'], queryFn: () => workspaceService.myProfile(slug) })
  const myUuid = mine?.person?.uuid ?? user?.uuid
  const target = uuid ?? myUuid
  if (!target) return <Spinner />

  return <ProfileView key={target} uuid={target} self={target === myUuid} />
}
