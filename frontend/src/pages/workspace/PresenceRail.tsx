import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { MessageSquare } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { useConversations } from '@/hooks/useChat'
import { useAuth } from '@/hooks/useAuth'
import { Avatar } from '@/components/ui/Avatar'
import { useRailTooltip } from './RailTooltip'

export interface ChatPerson { uuid: string; name: string; avatar_url: string | null }

/**
 * Fileira de pessoas à direita (desktop largo): conversas recentes primeiro, depois o restante da equipe.
 * Clicar numa foto abre a conversa direta; o botão do topo abre o chat completo (grupos e conversas).
 */
export default function PresenceRail({ slug, onOpenChat, onOpenPerson }: { slug: string; onOpenChat: () => void; onOpenPerson: (p: ChatPerson) => void }) {
  const { user } = useAuth()
  const tip = useRailTooltip('left')
  const { data: members } = useQuery({ queryKey: ['ws', slug, 'members'], queryFn: () => workspaceService.members(slug), staleTime: 300_000 })
  const { data: conversations = [] } = useConversations()

  const people = useMemo(() => {
    const team = new Map((members ?? []).filter((m) => m.uuid !== user?.uuid).map((m) => [m.uuid, m]))
    const unread = new Map<string, number>()
    const recent: ChatPerson[] = []
    for (const c of conversations) {
      if (c.party.type !== 'user' || !team.has(c.party.uuid)) continue
      unread.set(c.party.uuid, c.unread_count)
      recent.push(team.get(c.party.uuid)!)
      team.delete(c.party.uuid)
    }
    const rest = [...team.values()].sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'))

    return { list: [...recent, ...rest], unread }
  }, [members, conversations, user?.uuid])

  const totalUnread = conversations.reduce((n, c) => n + (c.unread_count ?? 0), 0)

  return (
    <aside className="fixed bottom-0 right-0 top-14 z-30 hidden w-16 flex-col items-center border-l border-gray-200 bg-white xl:flex" aria-label="Pessoas e chat">
      <button
        type="button" onClick={onOpenChat} aria-label="Abrir o chat"
        onMouseEnter={(e) => tip.show(e, 'Chat da empresa')} onMouseLeave={tip.hide}
        className="relative mt-3 flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 hover:text-brand-700"
      >
        <MessageSquare className="h-5 w-5" />
        {totalUnread > 0 && <span className="absolute right-0.5 top-0.5 min-w-[16px] rounded-full bg-brand-600 px-1 text-center text-[10px] font-bold leading-4 text-white">{totalUnread > 9 ? '9+' : totalUnread}</span>}
      </button>
      <span className="my-2 h-px w-8 flex-shrink-0 bg-gray-200" aria-hidden />
      <ul className="ws-rail-scroll flex w-full flex-1 flex-col items-center gap-2 overflow-y-auto pb-20">
        {people.list.map((p) => {
          const n = people.unread.get(p.uuid) ?? 0
          return (
            <li key={p.uuid}>
              <button
                type="button" onClick={() => { tip.hide(); onOpenPerson({ uuid: p.uuid, name: p.name, avatar_url: p.avatar_url }) }}
                aria-label={`Conversar com ${p.name}`}
                onMouseEnter={(e) => tip.show(e, p.name)} onMouseLeave={tip.hide}
                className="relative block rounded-full ring-brand-300 ring-offset-2 transition hover:ring-2"
              >
                <Avatar src={p.avatar_url} name={p.name} size="md" />
                {n > 0 && <span className="absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full border-2 border-white bg-brand-600" aria-label={`${n} não lida(s)`} />}
              </button>
            </li>
          )
        })}
      </ul>
      {tip.node}
    </aside>
  )
}
