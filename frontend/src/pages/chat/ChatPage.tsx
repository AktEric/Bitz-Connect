import { useState, useEffect, useRef } from 'react'
import { useLocation } from 'react-router-dom'
import { differenceInHours, formatDistanceToNow, format } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { Search, Send, MessageSquare } from 'lucide-react'
import {
  useConversations, useMessages, useSendMessage, useMarkRead,
  useHotelThreadMessages, useSendToHotel, useMarkHotelThreadRead,
} from '@/hooks/useChat'
import { useUserProfile } from '@/hooks/useProfile'
import { useHotel } from '@/hooks/useHotel'
import { UserLink } from '@/components/common/UserLink'
import { ProfileAvatar } from '@/components/common/ProfileAvatar'
import { CompanyLink } from '@/components/common/CompanyLink'
import { CompanyAvatar } from '@/components/common/CompanyAvatar'
import { Avatar } from '@/components/ui/Avatar'
import { MessageBubble } from '@/components/chat/MessageBubble'
import type { Conversation, ChatParty } from '@/types/api.types'

// ──────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────

function formatTime(iso: string): string {
  const msgDate = new Date(iso)
  const hoursDiff = differenceInHours(new Date(), msgDate)

  if (hoursDiff < 24) {
    return formatDistanceToNow(msgDate, { addSuffix: true, locale: ptBR })
  }
  return format(msgDate, 'dd/MM/yyyy', { locale: ptBR })
}

/** Chave estável para uma conversa, já que o outro lado pode ser pessoa ou hotel. */
function partyKey(party: ChatParty): string {
  return `${party.type}:${party.uuid}`
}

// ──────────────────────────────────────────────────────────
// Conversation list item
// ──────────────────────────────────────────────────────────

function ConversationItem({
  conversation,
  isActive,
  onClick,
}: {
  conversation: Conversation
  isActive: boolean
  onClick: () => void
}) {
  const { party, last_message, unread_count } = conversation
  const hasUnread = unread_count > 0

  return (
    <button
      onClick={onClick}
      className={`w-full flex items-center gap-3 px-3 py-3 rounded-xl text-left transition-colors ${
        isActive ? 'bg-primary/10' : hasUnread ? 'bg-violet-50 hover:bg-violet-100' : 'hover:bg-gray-50'
      }`}
    >
      {party.type === 'hotel' ? (
        <Avatar name={party.name} src={party.avatar_url} size="md" />
      ) : (
        <ProfileAvatar name={party.name} src={party.avatar_url} uuid={party.uuid} size="md" />
      )}
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between gap-1">
          <span className={`text-sm truncate ${isActive ? 'text-primary font-medium' : hasUnread ? 'text-gray-900 font-semibold' : 'text-gray-900 font-medium'}`}>
            {party.name}
          </span>
          <span className="text-xs text-gray-400 flex-shrink-0">
            {formatTime(last_message.created_at)}
          </span>
        </div>
        <div className="flex items-center justify-between gap-1 mt-0.5">
          <p className="text-xs text-gray-500 truncate">
            {last_message.is_mine && <span className="text-gray-400">Você: </span>}
            {last_message.content}
          </p>
          {unread_count > 0 && (
            <span className="flex-shrink-0 h-5 w-5 rounded-full bg-primary text-white text-xs flex items-center justify-center font-semibold">
              {unread_count > 9 ? '9+' : unread_count}
            </span>
          )}
        </div>
      </div>
    </button>
  )
}

// ──────────────────────────────────────────────────────────
// Message window
// ──────────────────────────────────────────────────────────

function MessageWindow({ conversation }: { conversation: Conversation }) {
  const { party } = conversation
  const isHotel = party.type === 'hotel'
  const [text, setText] = useState('')
  const messagesEndRef = useRef<HTMLDivElement>(null)

  const hotelSlug = isHotel ? party.slug : ''
  const userMessages = useMessages(isHotel ? '' : party.uuid)
  const hotelMessages = useHotelThreadMessages(hotelSlug)

  const messages = isHotel ? hotelMessages.data ?? [] : userMessages.data ?? []
  const isLoading = isHotel ? hotelMessages.isLoading : userMessages.isLoading

  const sendMessage = useSendMessage(isHotel ? '' : party.uuid)
  const sendToHotel = useSendToHotel(hotelSlug)
  const markRead = useMarkRead(isHotel ? '' : party.uuid)
  const markHotelRead = useMarkHotelThreadRead(hotelSlug)

  // Mark as read when opening the conversation
  useEffect(() => {
    if (isHotel) {
      markHotelRead.mutate()
    } else {
      markRead.mutate()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [party.uuid, isHotel])

  // Scroll to bottom on new messages
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  function handleSend() {
    const trimmed = text.trim()
    if (!trimmed) return
    if (isHotel) {
      if (sendToHotel.isPending) return
      sendToHotel.mutate(trimmed)
    } else {
      if (sendMessage.isPending) return
      sendMessage.mutate(trimmed)
    }
    setText('')
  }

  function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend()
    }
  }

  const sending = isHotel ? sendToHotel.isPending : sendMessage.isPending

  // Messages come newest-first from the server — reverse to display oldest at top
  const ordered = [...messages].reverse()

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center gap-3 px-4 py-3 border-b border-gray-100 bg-white flex-shrink-0">
        {isHotel ? (
          <>
            <CompanyAvatar slug={hotelSlug} name={party.name} logoUrl={party.avatar_url} size="md" />
            <CompanyLink slug={hotelSlug} name={party.name} className="text-sm font-semibold text-gray-900" />
          </>
        ) : (
          <>
            <ProfileAvatar name={party.name} src={party.avatar_url} uuid={party.uuid} size="md" />
            <UserLink uuid={party.uuid} name={party.name} className="text-sm font-semibold text-gray-900" />
          </>
        )}
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-4 py-4 space-y-2">
        {isLoading ? (
          <div className="flex justify-center py-8">
            <div className="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
          </div>
        ) : ordered.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-gray-400 gap-2">
            <MessageSquare className="h-10 w-10 text-gray-200" />
            <p className="text-sm">Nenhuma mensagem ainda. Diga olá!</p>
          </div>
        ) : (
          ordered.map((msg) => (
            <MessageBubble key={msg.uuid} msg={msg} />
          ))
        )}
        <div ref={messagesEndRef} />
      </div>

      {/* Input */}
      <div className="flex items-end gap-2 px-4 py-3 border-t border-gray-100 bg-white flex-shrink-0">
        <textarea
          value={text}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Escreva uma mensagem..."
          rows={1}
          className="flex-1 resize-none rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary max-h-32 overflow-auto"
          style={{ lineHeight: '1.5' }}
        />
        <button
          onClick={handleSend}
          disabled={!text.trim() || sending}
          className="h-9 w-9 flex-shrink-0 flex items-center justify-center rounded-xl bg-primary text-white hover:bg-primary/90 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          <Send className="h-4 w-4" />
        </button>
      </div>
    </div>
  )
}

// ──────────────────────────────────────────────────────────
// ChatPage
// ──────────────────────────────────────────────────────────

/** Monta uma conversa temporária com uma pessoa que ainda não tem histórico. */
function PendingConversationLoader({
  userUuid,
  onLoaded,
}: {
  userUuid: string
  onLoaded: (conv: Conversation) => void
}) {
  const { data: profile } = useUserProfile(userUuid)

  useEffect(() => {
    if (profile) {
      onLoaded({
        party: { type: 'user', uuid: profile.uuid, name: profile.name, avatar_url: profile.avatar_url },
        last_message: { content: '', created_at: new Date().toISOString(), is_mine: false },
        unread_count: 0,
      })
    }
  }, [profile, onLoaded])

  return null
}

/** Monta uma conversa temporária com a caixa de um hotel que ainda não tem histórico. */
function PendingHotelConversationLoader({
  hotelSlug,
  onLoaded,
}: {
  hotelSlug: string
  onLoaded: (conv: Conversation) => void
}) {
  const { data: hotel } = useHotel(hotelSlug)

  useEffect(() => {
    if (hotel) {
      onLoaded({
        party: { type: 'hotel', uuid: hotel.uuid, slug: hotel.slug, name: hotel.name, avatar_url: hotel.logo_url },
        last_message: { content: '', created_at: new Date().toISOString(), is_mine: false },
        unread_count: 0,
      })
    }
  }, [hotel, onLoaded])

  return null
}

export default function ChatPage() {
  const location = useLocation()
  const state = location.state as { openUserUuid?: string; openHotelSlug?: string } | null
  const openUserUuid = state?.openUserUuid
  const openHotelSlug = state?.openHotelSlug

  const [search, setSearch] = useState('')
  const [activeKey, setActiveKey] = useState<string | null>(null)
  const [tempConversation, setTempConversation] = useState<Conversation | null>(null)
  const [pendingUserUuid, setPendingUserUuid] = useState<string | null>(null)
  const [pendingHotelSlug, setPendingHotelSlug] = useState<string | null>(null)

  const { data: conversations = [], isLoading } = useConversations()

  useEffect(() => {
    if (openUserUuid) {
      const existing = conversations.find((c) => c.party.type === 'user' && c.party.uuid === openUserUuid)
      if (existing) {
        setActiveKey(partyKey(existing.party))
        setTempConversation(null)
        setPendingUserUuid(null)
      } else {
        setPendingUserUuid(openUserUuid)
      }
    } else if (openHotelSlug) {
      const existing = conversations.find((c) => c.party.type === 'hotel' && c.party.slug === openHotelSlug)
      if (existing) {
        setActiveKey(partyKey(existing.party))
        setTempConversation(null)
        setPendingHotelSlug(null)
      } else {
        setPendingHotelSlug(openHotelSlug)
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [openUserUuid, openHotelSlug, conversations.length])

  function handleTempConversationLoaded(conv: Conversation) {
    setTempConversation(conv)
    setActiveKey(partyKey(conv.party))
    setPendingUserUuid(null)
    setPendingHotelSlug(null)
  }

  const filtered = conversations.filter((c) => c.party.name.toLowerCase().includes(search.toLowerCase()))

  const displayList =
    tempConversation && !conversations.find((c) => partyKey(c.party) === partyKey(tempConversation.party))
      ? [tempConversation, ...filtered]
      : filtered

  const activeConversation =
    conversations.find((c) => partyKey(c.party) === activeKey) ??
    (tempConversation && partyKey(tempConversation.party) === activeKey ? tempConversation : null)

  return (
    <>
      {pendingUserUuid && (
        <PendingConversationLoader userUuid={pendingUserUuid} onLoaded={handleTempConversationLoaded} />
      )}
      {pendingHotelSlug && (
        <PendingHotelConversationLoader hotelSlug={pendingHotelSlug} onLoaded={handleTempConversationLoaded} />
      )}

      <div className="flex h-[calc(100vh-3.5rem-3rem)] overflow-hidden bg-white rounded-xl border border-gray-100 shadow-sm">
        {/* Left column — conversation list */}
        <div className="w-full max-w-[300px] flex-shrink-0 flex flex-col border-r border-gray-100">
          {/* Search */}
          <div className="p-3 border-b border-gray-100">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                type="text"
                placeholder="Buscar conversa..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-9 pr-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
              />
            </div>
          </div>

          {/* List */}
          <div className="flex-1 overflow-y-auto p-2">
            {isLoading ? (
              Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="flex items-center gap-3 px-3 py-3 animate-pulse">
                  <div className="h-10 w-10 rounded-full bg-gray-200 flex-shrink-0" />
                  <div className="flex-1 space-y-1.5">
                    <div className="h-3 w-24 rounded bg-gray-200" />
                    <div className="h-2.5 w-36 rounded bg-gray-100" />
                  </div>
                </div>
              ))
            ) : displayList.length === 0 ? (
              <div className="flex flex-col items-center justify-center h-full py-12 text-center px-4 text-gray-400 gap-2">
                <MessageSquare className="h-10 w-10 text-gray-200" />
                <p className="text-sm">
                  {search
                    ? 'Nenhuma conversa encontrada.'
                    : 'Nenhuma conversa ainda. Encontre pessoas na pesquisa para começar a conversar.'}
                </p>
              </div>
            ) : (
              displayList.map((conv) => (
                <ConversationItem
                  key={partyKey(conv.party)}
                  conversation={conv}
                  isActive={partyKey(conv.party) === activeKey}
                  onClick={() => setActiveKey(partyKey(conv.party))}
                />
              ))
            )}
          </div>
        </div>

        {/* Right column — message window */}
        <div className="flex-1 flex flex-col min-w-0">
          {activeConversation ? (
            <MessageWindow key={partyKey(activeConversation.party)} conversation={activeConversation} />
          ) : (
            <div className="flex flex-col items-center justify-center h-full text-gray-400 gap-3">
              <MessageSquare className="h-14 w-14 text-gray-200" />
              <p className="text-base font-medium text-gray-500">Selecione uma conversa</p>
              <p className="text-sm text-gray-400">
                Escolha uma conversa à esquerda para começar
              </p>
            </div>
          )}
        </div>
      </div>
    </>
  )
}
