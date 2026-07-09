import { useState, useEffect, useRef } from 'react'
import { useParams, useSearchParams } from 'react-router-dom'
import { differenceInHours, formatDistanceToNow, format } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { Search, Send, MessageSquare } from 'lucide-react'
import {
  useHotelConversations, useHotelAdminMessages, useSendAsHotel, useMarkHotelAdminRead,
} from '@/hooks/useChat'
import { ProfileAvatar } from '@/components/common/ProfileAvatar'
import { UserLink } from '@/components/common/UserLink'
import { MessageBubble } from '@/components/chat/MessageBubble'
import type { Conversation } from '@/types/api.types'

function formatTime(iso: string): string {
  const msgDate = new Date(iso)
  const hoursDiff = differenceInHours(new Date(), msgDate)
  if (hoursDiff < 24) return formatDistanceToNow(msgDate, { addSuffix: true, locale: ptBR })
  return format(msgDate, 'dd/MM/yyyy', { locale: ptBR })
}

function ConversationItem({
  conversation, isActive, onClick,
}: { conversation: Conversation; isActive: boolean; onClick: () => void }) {
  const { party, last_message, unread_count } = conversation
  if (party.type !== 'user') return null
  const hasUnread = unread_count > 0

  return (
    <button
      onClick={onClick}
      className={`w-full flex items-center gap-3 px-3 py-3 rounded-xl text-left transition-colors ${
        isActive ? 'bg-amber-100' : hasUnread ? 'bg-amber-50 hover:bg-amber-100' : 'hover:bg-gray-50'
      }`}
    >
      <ProfileAvatar name={party.name} src={party.avatar_url} uuid={party.uuid} size="md" />
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between gap-1">
          <span className={`text-sm truncate ${hasUnread ? 'text-gray-900 font-semibold' : 'text-gray-900 font-medium'}`}>
            {party.name}
          </span>
          <span className="text-xs text-gray-400 flex-shrink-0">{formatTime(last_message.created_at)}</span>
        </div>
        <div className="flex items-center justify-between gap-1 mt-0.5">
          <p className="text-xs text-gray-500 truncate">
            {last_message.is_mine && <span className="text-gray-400">Hotel: </span>}
            {last_message.content}
          </p>
          {unread_count > 0 && (
            <span className="flex-shrink-0 h-5 w-5 rounded-full bg-amber-500 text-white text-xs flex items-center justify-center font-semibold">
              {unread_count > 9 ? '9+' : unread_count}
            </span>
          )}
        </div>
      </div>
    </button>
  )
}

function MessageWindow({ hotelSlug, conversation }: { hotelSlug: string; conversation: Conversation }) {
  const { party } = conversation
  if (party.type !== 'user') return null

  const [text, setText] = useState('')
  const messagesEndRef = useRef<HTMLDivElement>(null)

  const { data: messages = [], isLoading } = useHotelAdminMessages(hotelSlug, party.uuid)
  const sendAsHotel = useSendAsHotel(hotelSlug, party.uuid)
  const markRead = useMarkHotelAdminRead(hotelSlug, party.uuid)

  useEffect(() => {
    markRead.mutate()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [party.uuid])

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  function handleSend() {
    const trimmed = text.trim()
    if (!trimmed || sendAsHotel.isPending) return
    sendAsHotel.mutate(trimmed)
    setText('')
  }

  function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend()
    }
  }

  const ordered = [...messages].reverse()

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center gap-3 px-4 py-3 border-b border-gray-100 bg-white flex-shrink-0">
        <ProfileAvatar name={party.name} src={party.avatar_url} uuid={party.uuid} size="md" />
        <UserLink uuid={party.uuid} name={party.name} className="text-sm font-semibold text-gray-900" />
      </div>

      <div className="flex-1 overflow-y-auto px-4 py-4 space-y-2">
        {isLoading ? (
          <div className="flex justify-center py-8">
            <div className="h-6 w-6 animate-spin rounded-full border-2 border-amber-500 border-t-transparent" />
          </div>
        ) : ordered.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-gray-400 gap-2">
            <MessageSquare className="h-10 w-10 text-gray-200" />
            <p className="text-sm">Nenhuma mensagem ainda.</p>
          </div>
        ) : (
          ordered.map((msg) => <MessageBubble key={msg.uuid} msg={msg} />)
        )}
        <div ref={messagesEndRef} />
      </div>

      <div className="flex items-end gap-2 px-4 py-3 border-t border-gray-100 bg-white flex-shrink-0">
        <textarea
          value={text}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Responder como o hotel..."
          rows={1}
          className="flex-1 resize-none rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:border-amber-400 max-h-32 overflow-auto"
          style={{ lineHeight: '1.5' }}
        />
        <button
          onClick={handleSend}
          disabled={!text.trim() || sendAsHotel.isPending}
          className="h-9 w-9 flex-shrink-0 flex items-center justify-center rounded-xl bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
        >
          <Send className="h-4 w-4" />
        </button>
      </div>
    </div>
  )
}

/**
 * HotelMessagesPage — caixa de entrada compartilhada do hotel. Qualquer admin
 * aprovado vê e responde as mesmas conversas; a mensagem sai como enviada
 * pelo hotel, não pelo admin que digitou.
 */
export default function HotelMessagesPage() {
  const { slug = '' } = useParams<{ slug: string }>()
  const [searchParams] = useSearchParams()
  const openUserUuid = searchParams.get('user')

  const [search, setSearch] = useState('')
  const [activeUuid, setActiveUuid] = useState<string | null>(openUserUuid)

  const { data: conversations = [], isLoading } = useHotelConversations(slug)

  useEffect(() => {
    if (openUserUuid) setActiveUuid(openUserUuid)
  }, [openUserUuid])

  const filtered = conversations.filter(
    (c) => c.party.type === 'user' && c.party.name.toLowerCase().includes(search.toLowerCase()),
  )

  const activeConversation = conversations.find((c) => c.party.type === 'user' && c.party.uuid === activeUuid)

  return (
    <div className="flex h-[calc(100vh-2rem)] overflow-hidden bg-white rounded-xl border border-gray-100 m-4">
      {/* Left column */}
      <div className="w-full max-w-[300px] flex-shrink-0 flex flex-col border-r border-gray-100">
        <div className="p-4 border-b border-gray-100">
          <h1 className="text-base font-semibold text-gray-900">Mensagens</h1>
          <p className="text-xs text-gray-500 mt-0.5">Caixa compartilhada — qualquer admin pode responder.</p>
        </div>
        <div className="p-3 border-b border-gray-100">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              type="text"
              placeholder="Buscar conversa..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-9 pr-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:border-amber-400"
            />
          </div>
        </div>

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
          ) : filtered.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-full py-12 text-center px-4 text-gray-400 gap-2">
              <MessageSquare className="h-10 w-10 text-gray-200" />
              <p className="text-sm">
                {search ? 'Nenhuma conversa encontrada.' : 'Nenhuma mensagem recebida ainda.'}
              </p>
            </div>
          ) : (
            filtered.map((conv) =>
              conv.party.type === 'user' ? (
                <ConversationItem
                  key={conv.party.uuid}
                  conversation={conv}
                  isActive={conv.party.uuid === activeUuid}
                  onClick={() => setActiveUuid(conv.party.uuid)}
                />
              ) : null,
            )
          )}
        </div>
      </div>

      {/* Right column */}
      <div className="flex-1 flex flex-col min-w-0">
        {activeConversation ? (
          <MessageWindow key={activeUuid} hotelSlug={slug} conversation={activeConversation} />
        ) : (
          <div className="flex flex-col items-center justify-center h-full text-gray-400 gap-3">
            <MessageSquare className="h-14 w-14 text-gray-200" />
            <p className="text-base font-medium text-gray-500">Selecione uma conversa</p>
          </div>
        )}
      </div>
    </div>
  )
}
