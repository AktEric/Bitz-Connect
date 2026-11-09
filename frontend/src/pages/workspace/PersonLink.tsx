import type { ReactNode } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Avatar } from '@/components/ui/Avatar'
import type { WorkspacePerson } from '@/types/workspace.types'
import { cn } from '@/utils/cn'

type PersonRef = Pick<WorkspacePerson, 'uuid' | 'name'> & Partial<Pick<WorkspacePerson, 'avatar_url' | 'job_title'>>

/** Caminho do perfil de uma pessoa no Workspace. */
export const personPath = (slug: string, uuid: string) => `/w/${slug}/pessoas/${uuid}`

/**
 * Nome (ou o conteúdo passado) que leva ao perfil da pessoa. Use em TODO lugar que mostra uma pessoa:
 * autor de publicação, comentário, reação, menção, candidato, ausência, equipe.
 */
export default function PersonLink({ person, className, children }: { person: PersonRef | null | undefined; className?: string; children?: ReactNode }) {
  const { slug = '' } = useParams()
  if (!person) return <span className={className}>{children ?? 'Ex-colaborador'}</span>

  return (
    <Link to={personPath(slug, person.uuid)} className={cn('rounded hover:text-brand-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-500', className)}>
      {children ?? person.name}
    </Link>
  )
}

/** Foto da pessoa levando ao perfil. */
export function PersonAvatarLink({ person, size = 'sm', className }: { person: PersonRef | null | undefined; size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl'; className?: string }) {
  return (
    <PersonLink person={person} className={cn('flex-shrink-0 rounded-full hover:no-underline', className)}>
      <Avatar src={person?.avatar_url} name={person?.name ?? '?'} size={size} />
    </PersonLink>
  )
}
