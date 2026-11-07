import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft, Info, Lock, Megaphone, MessageSquare, Plus, Send, Users, X } from 'lucide-react'
import { useConversations } from '@/hooks/useChat'
import {
  useChatGroup,
  useChatGroups,
  useGroupMessages,
  useMarkGroupRead,
  useSendGroupMessage,
} from '@/hooks/useChatGroups'
import { useMyHotels } from '@/hooks/useHotel'
import { useToast } from '@/contexts/ToastContext'
import { Avatar } from '@/components/ui/Avatar'
import { cn } from '@/utils/cn'
import { extractApiError } from '@/utils/api-error'
import CreateGroupModal from './CreateGroupModal'
import GroupInfoPanel from './GroupInfoPanel'
import type { ChatGroup } from '@/types/chatGroup.types'

interface Props {
  open: boolean
  onClose: () => void
  /** Grupo a abrir ao exibir o painel (ex.: vindo de uma notificação). */
  requestedGroup?: string | null
}

type View = 'list' | 'thread' | 'info'

function formatTime(iso: string): string {
  const d = new Date(iso)
  const today = new Date()
  if (d.toDateString() === today.toDateString()) {
    return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
  }
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
}

/**
 * Barra lateral de chat (painel deslizante, disponível em qualquer tela):
 * grupos da empresa e acesso às conversas diretas.
 */
export default function ChatSidebar({ open, onClose, requestedGroup }: Props) {
  const navigate = useNavigate()
  const [tab, setTab] = useState<'groups' | 'direct'>('groups')
  const [view, setView] = useState<View>('list')
  const [activeGroup, setActiveGroup] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)

  const { data: groups = [], isLoading } = useChatGroups(open)
  const { data: conversations = [] } = useConversations()
  const { data: hotelsData } = useMyHotels()
  const isCompanyAdmin = (hotelsData?.data?.length ?? 0) > 0

  useEffect(() => {
    if (requestedGroup) {
      setActiveGroup(requestedGroup)
      setView('thread')
      setTab('groups')
    }
  }, [requestedGroup])

  function openGroup(uuid: string) {
    setActiveGroup(uuid)
    setView('thread')
  }

  return (
    <>
      <aside
        aria-label="Chat da empresa"
        aria-hidden={!open}
        className={cn(
          'fixed inset-y-0 right-0 z-[55] flex w-full flex-col border-l border-gray-200 bg-white shadow-2xl transition-transform duration-300 sm:w-[400px]',
          open ? 'translate-x-0' : 'pointer-events-none translate-x-full',
        )}
      >
        {view === 'list' && (
          <>
            <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
              <h2 className="text-base font-semibold text-gray-900">Chat</h2>
              <div className="flex items-center gap-1">
                {isCompanyAdmin && (
                  <button
                    onClick={() => setCreating(true)}
                    className="flex items-center gap-1 rounded-lg bg-violet-50 px-2.5 py-1.5 text-xs font-medium text-violet-700 hover:bg-violet-100"
                  >
                    <Plus className="h-3.5 w-3.5" />Novo grupo
                  </button>
                )}
                <button onClick={onClose} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100" aria-label="Fechar chat">
                  <X className="h-5 w-5" />
                </button>
              </div>
            </div>

            <div className="flex border-b border-gray-100 text-sm">
              {([['groups', 'Grupos'], ['direct', 'Conversas']] as const).map(([key, label]) => (
                <button
                  key={key}
                  onClick={() => setTab(key)}
                  className={cn(
                    'flex-1 border-b-2 py-2.5 font-medium transition-colors',
                    tab === key ? 'border-violet-600 text-violet-700' : 'border-transparent text-gray-500 hover:text-gray-800',
                  )}
                >
                  {label}
                </button>
              ))}
            </div>

            <div className="flex-1 overflow-y-auto">
              {tab === 'groups' ? (
                <GroupList groups={groups} loading={isLoading} onOpen={openGroup} canCreate={isCompanyAdmin} onCreate={() => setCreating(true)} />
              ) : (
                <ul className="divide-y divide-gray-50">
                  {conversations.length === 0 && (
                    <li className="px-6 py-12 text-center text-sm text-gray-400">Nenhuma conversa ainda.</li>
                  )}
                  {conversations.map((c) => (
                    <li key={`${c.party.type}:${c.party.uuid}`}>
                      <button
                        onClick={() => { onClose(); navigate('/chat') }}
                        className="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50"
                      >
                        <Avatar src={c.party.avatar_url} name={c.party.name} size="md" />
                        <span className="min-w-0 flex-1">
                          <span className="block truncate text-sm font-medium text-gray-900">{c.party.name}</span>
                          <span className="block truncate text-xs text-gray-500">{c.last_message.content}</span>
                        </span>
                        {c.unread_count > 0 && (
                          <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-violet-600 px-1.5 text-[11px] font-bold text-white">{c.unread_count}</span>
                        )}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </>
        )}

        {view === 'thread' && activeGroup && (
          <GroupThread groupUuid={activeGroup} onBack={() => setView('list')} onInfo={() => setView('info')} onClose={onClose} />
        )}

        {view === 'info' && activeGroup && (
          <GroupInfoPanel groupUuid={activeGroup} onBack={() => setView('thread')} onLeft={() => { setActiveGroup(null); setView('list') }} />
        )}
      </aside>

      {open && (
        <button aria-label="Fechar chat" onClick={onClose} className="fixed inset-0 z-[54] hidden bg-black/10 sm:block" tabIndex={-1} />
      )}

      {creating && (
        <CreateGroupModal
          onClose={() => setCreating(false)}
          onCreated={(g) => { setCreating(false); setTab('groups'); openGroup(g.uuid) }}
        />
      )}
    </>
  )
}

// ──────────────────────────────────────────────────────────
// Lista de grupos
// ──────────────────────────────────────────────────────────

function GroupList({
  groups, loading, onOpen, canCreate, onCreate,
}: {
  groups: ChatGroup[]
  loading: boolean
  onOpen: (uuid: string) => void
  canCreate: boolean
  onCreate: () => void
}) {
  if (loading) {
    return <div className="px-6 py-12 text-center text-sm text-gray-400">Carregando grupos…</div>
  }

  if (groups.length === 0) {
    return (
      <div className="flex flex-col items-center gap-3 px-6 py-14 text-center">
        <Users className="h-10 w-10 text-gray-300" />
        <p className="text-sm font-semibold text-gray-700">Nenhum grupo ainda</p>
        <p className="text-xs text-gray-500">
          {canCreate
            ? 'Crie um grupo para falar com a equipe ou enviar avisos.'
            : 'Quando a empresa adicionar você a um grupo, ele aparece aqui.'}
        </p>
        {canCreate && (
          <button onClick={onCreate} className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-violet-700">
            Criar primeiro grupo
          </button>
        )}
      </div>
    )
  }

  return (
    <ul className="divide-y divide-gray-50">
      {groups.map((g) => (
        <li key={g.uuid}>
          <button onClick={() => onOpen(g.uuid)} className="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50">
            <span className="relative">
              <Avatar src={g.hotel?.avatar_url} name={g.name} size="md" />
              {g.only_admins_can_post && (
                <span className="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-amber-400 text-white ring-2 ring-white">
                  <Megaphone className="h-2.5 w-2.5" />
                </span>
              )}
            </span>
            <span className="min-w-0 flex-1">
              <span className="flex items-baseline justify-between gap-2">
                <span className="truncate text-sm font-semibold text-gray-900">{g.name}</span>
                {g.last_message && <span className="flex-shrink-0 text-[11px] text-gray-400">{formatTime(g.last_message.created_at)}</span>}
              </span>
              <span className="block truncate text-xs text-gray-500">
                {g.last_message
                  ? `${g.last_message.sender_name ? g.last_message.sender_name.split(' ')[0] + ': ' : ''}${g.last_message.content}`
                  : g.hotel?.name ?? 'Sem mensagens'}
              </span>
            </span>
            {g.unread_count > 0 && !g.notifications_muted && (
              <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-violet-600 px-1.5 text-[11px] font-bold text-white">{g.unread_count > 99 ? '99+' : g.unread_count}</span>
            )}
          </button>
        </li>
      ))}
    </ul>
  )
}

// ──────────────────────────────────────────────────────────
// Conversa do grupo
// ──────────────────────────────────────────────────────────

function GroupThread({
  groupUuid, onBack, onInfo, onClose,
}: {
  groupUuid: string
  onBack: () => void
  onInfo: () => void
  onClose: () => void
}) {
  const { toast } = useToast()
  const { data: group } = useChatGroup(groupUuid)
  const { data: rawMessages = [] } = useGroupMessages(groupUuid)
  const send = useSendGroupMessage(groupUuid)
  const markRead = useMarkGroupRead(groupUuid)

  const [text, setText] = useState('')
  const [asAnnouncement, setAsAnnouncement] = useState(false)
  const bottomRef = useRef<HTMLDivElement>(null)

  const messages = useMemo(() => [...rawMessages].reverse(), [rawMessages])
  const latestUuid = rawMessages[0]?.uuid

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ block: 'end' })
  }, [messages.length, groupUuid])

  useEffect(() => {
    if (latestUuid) markRead.mutate()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [latestUuid, groupUuid])

  async function handleSend() {
    const content = text.trim()
    if (!content || send.isPending) return
    try {
      await send.mutateAsync({ content, isAnnouncement: asAnnouncement })
      setText('')
      setAsAnnouncement(false)
    } catch (err) {
      toast(extractApiError(err), 'error')
    }
  }

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center gap-2 border-b border-gray-100 px-3 py-3">
        <button onClick={onBack} className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100" aria-label="Voltar">
          <ArrowLeft className="h-5 w-5" />
        </button>
        <button onClick={onInfo} className="flex min-w-0 flex-1 items-center gap-2.5 text-left">
          <Avatar src={group?.hotel?.avatar_url} name={group?.name ?? '?'} size="sm" />
          <span className="min-w-0">
            <span className="block truncate text-sm font-semibold text-gray-900">{group?.name ?? 'Grupo'}</span>
            <span className="block truncate text-xs text-gray-400">{group ? `${group.members_count} participantes` : ' '}</span>
          </span>
        </button>
        <button onClick={onInfo} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100" aria-label="Informações do grupo">
          <Info className="h-5 w-5" />
        </button>
        <button onClick={onClose} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100" aria-label="Fechar chat">
          <X className="h-5 w-5" />
        </button>
      </div>

      {group?.only_admins_can_post && (
        <div className="flex items-center gap-2 bg-amber-50 px-4 py-2 text-xs text-amber-800">
          <Megaphone className="h-3.5 w-3.5" />Modo aviso: somente administradores enviam mensagens.
        </div>
      )}

      <div className="flex-1 space-y-3 overflow-y-auto bg-gray-50/60 px-4 py-4">
        {messages.length === 0 && (
          <div className="flex flex-col items-center gap-2 py-12 text-center text-gray-400">
            <MessageSquare className="h-8 w-8" />
            <p className="text-sm">Nenhuma mensagem ainda.</p>
          </div>
        )}

        {messages.map((m) => (
          <div key={m.uuid} className={cn('flex gap-2', m.is_mine && 'flex-row-reverse')}>
            {!m.is_mine && <Avatar src={m.sender.avatar_url} name={m.sender.name} size="xs" className="mt-1" />}
            <div
              className={cn(
                'max-w-[78%] rounded-2xl px-3 py-2 text-sm shadow-sm',
                m.is_announcement
                  ? 'border border-amber-200 bg-amber-50 text-amber-900'
                  : m.is_mine
                    ? 'bg-violet-600 text-white'
                    : 'bg-white text-gray-800',
              )}
            >
              {m.is_announcement && (
                <p className="mb-1 flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wide text-amber-600">
                  <Megaphone className="h-3 w-3" />Aviso
                </p>
              )}
              {!m.is_mine && <p className="mb-0.5 text-xs font-semibold text-violet-600">{m.sender.name}</p>}
              <p className="whitespace-pre-wrap break-words">{m.content}</p>
              <p className={cn('mt-1 text-right text-[10px]', m.is_mine && !m.is_announcement ? 'text-violet-200' : 'text-gray-400')}>
                {formatTime(m.created_at)}
              </p>
            </div>
          </div>
        ))}
        <div ref={bottomRef} />
      </div>

      {group && !group.can_post ? (
        <div className="flex items-center justify-center gap-2 border-t border-gray-100 bg-white px-4 py-4 text-xs text-gray-500">
          <Lock className="h-3.5 w-3.5" />Somente administradores podem enviar mensagens neste grupo.
        </div>
      ) : (
        <div className="border-t border-gray-100 bg-white p-3">
          {group?.can_manage && (
            <label className="mb-2 flex cursor-pointer items-center gap-2 text-xs text-gray-600">
              <input type="checkbox" checked={asAnnouncement} onChange={(e) => setAsAnnouncement(e.target.checked)} className="rounded border-gray-300 text-amber-500" />
              <Megaphone className="h-3.5 w-3.5 text-amber-500" />Enviar como aviso (destacado)
            </label>
          )}
          <div className="flex items-end gap-2">
            <textarea
              value={text}
              onChange={(e) => setText(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                  e.preventDefault()
                  handleSend()
                }
              }}
              rows={1}
              maxLength={5000}
              placeholder="Escreva uma mensagem"
              className="max-h-28 min-h-[40px] flex-1 resize-none rounded-xl border-gray-200 text-sm"
            />
            <button
              onClick={handleSend}
              disabled={!text.trim() || send.isPending}
              className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-violet-600 text-white transition-colors hover:bg-violet-700 disabled:opacity-40"
              aria-label="Enviar"
            >
              <Send className="h-4 w-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
