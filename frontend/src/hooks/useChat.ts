import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import chatService from '@/services/chat.service'
import type { Conversation, Message } from '@/types/api.types'
import {
  CHAT_MESSAGES_POLL_MS,
  CHAT_CONVERSATIONS_POLL_MS,
  UNREAD_MESSAGES_POLL_MS,
} from '@/config/polling'

// ──────────────────────────────────────────────────────────
// Query keys
// ──────────────────────────────────────────────────────────

export const chatKeys = {
  conversations: ['chat', 'conversations'] as const,
  messages: (userUuid: string) => ['chat', 'messages', userUuid] as const,
  unread: ['chat', 'unread-count'] as const,
  hotelThread: (hotelSlug: string) => ['chat', 'hotel-thread', hotelSlug] as const,
  hotelConversations: (hotelSlug: string) => ['chat', 'hotel-conversations', hotelSlug] as const,
  hotelAdminMessages: (hotelSlug: string, userUuid: string) =>
    ['chat', 'hotel-admin-messages', hotelSlug, userUuid] as const,
  hotelUnread: (hotelSlug: string) => ['chat', 'hotel-unread', hotelSlug] as const,
}

function optimisticMessage(content: string): Message {
  return {
    uuid: `optimistic-${Date.now()}`,
    content,
    is_mine: true,
    read_at: null,
    created_at: new Date().toISOString(),
    sender: { type: 'user', uuid: '', name: '', avatar_url: null },
    type: 'text',
    metadata: null,
  }
}

// ──────────────────────────────────────────────────────────
// Lado pessoal — conversas com pessoas e com hotéis
// ──────────────────────────────────────────────────────────

/** Fetches the list of conversations with polling every 30s. */
export function useConversations() {
  return useQuery<Conversation[]>({
    queryKey: chatKeys.conversations,
    queryFn: () => chatService.getConversations(),
    refetchInterval: CHAT_CONVERSATIONS_POLL_MS,
  })
}

/** Fetches messages for a conversation with another person, polling every 5s. */
export function useMessages(userUuid: string) {
  return useQuery<Message[]>({
    queryKey: chatKeys.messages(userUuid),
    queryFn: () => chatService.getMessages(userUuid),
    enabled: !!userUuid,
    refetchInterval: CHAT_MESSAGES_POLL_MS,
    refetchIntervalInBackground: false,
  })
}

/** Fetches the count of unread messages, polling every 30s. */
export function useUnreadMessages() {
  return useQuery<{ count: number }>({
    queryKey: chatKeys.unread,
    queryFn: () => chatService.getUnreadCount(),
    refetchInterval: UNREAD_MESSAGES_POLL_MS,
  })
}

/** Mutation to send a message with optimistic update. */
export function useSendMessage(userUuid: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (content: string) => chatService.sendMessage(userUuid, content),

    onMutate: async (content) => {
      await qc.cancelQueries({ queryKey: chatKeys.messages(userUuid) })
      const previous = qc.getQueryData<Message[]>(chatKeys.messages(userUuid))
      qc.setQueryData<Message[]>(chatKeys.messages(userUuid), (old = []) => [optimisticMessage(content), ...old])
      return { previous }
    },

    onError: (_err, _vars, context) => {
      if (context?.previous !== undefined) {
        qc.setQueryData(chatKeys.messages(userUuid), context.previous)
      }
    },

    onSettled: () => {
      qc.invalidateQueries({ queryKey: chatKeys.messages(userUuid) })
      qc.invalidateQueries({ queryKey: chatKeys.conversations })
    },
  })
}

/** Mutation to mark messages from a user as read. */
export function useMarkRead(userUuid: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: () => chatService.markRead(userUuid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: chatKeys.conversations })
      qc.invalidateQueries({ queryKey: chatKeys.unread })
    },
  })
}

// ── Conversa pessoal com a caixa compartilhada de um hotel ────────────────

export function useHotelThreadMessages(hotelSlug: string) {
  return useQuery<Message[]>({
    queryKey: chatKeys.hotelThread(hotelSlug),
    queryFn: () => chatService.getHotelThreadMessages(hotelSlug),
    enabled: !!hotelSlug,
    refetchInterval: CHAT_MESSAGES_POLL_MS,
    refetchIntervalInBackground: false,
  })
}

export function useSendToHotel(hotelSlug: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (content: string) => chatService.sendToHotel(hotelSlug, content),

    onMutate: async (content) => {
      await qc.cancelQueries({ queryKey: chatKeys.hotelThread(hotelSlug) })
      const previous = qc.getQueryData<Message[]>(chatKeys.hotelThread(hotelSlug))
      qc.setQueryData<Message[]>(chatKeys.hotelThread(hotelSlug), (old = []) => [optimisticMessage(content), ...old])
      return { previous }
    },

    onError: (_err, _vars, context) => {
      if (context?.previous !== undefined) {
        qc.setQueryData(chatKeys.hotelThread(hotelSlug), context.previous)
      }
    },

    onSettled: () => {
      qc.invalidateQueries({ queryKey: chatKeys.hotelThread(hotelSlug) })
      qc.invalidateQueries({ queryKey: chatKeys.conversations })
    },
  })
}

export function useMarkHotelThreadRead(hotelSlug: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: () => chatService.markHotelThreadRead(hotelSlug),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: chatKeys.conversations })
      qc.invalidateQueries({ queryKey: chatKeys.unread })
    },
  })
}

// ── Caixa de entrada compartilhada do hotel (painel do hotel) ─────────────

export function useHotelUnreadCount(hotelSlug: string) {
  return useQuery<{ count: number }>({
    queryKey: chatKeys.hotelUnread(hotelSlug),
    queryFn: () => chatService.getHotelUnreadCount(hotelSlug),
    enabled: !!hotelSlug,
    refetchInterval: UNREAD_MESSAGES_POLL_MS,
  })
}

export function useHotelConversations(hotelSlug: string) {
  return useQuery<Conversation[]>({
    queryKey: chatKeys.hotelConversations(hotelSlug),
    queryFn: () => chatService.getHotelConversations(hotelSlug),
    enabled: !!hotelSlug,
    refetchInterval: CHAT_CONVERSATIONS_POLL_MS,
  })
}

export function useHotelAdminMessages(hotelSlug: string, userUuid: string) {
  return useQuery<Message[]>({
    queryKey: chatKeys.hotelAdminMessages(hotelSlug, userUuid),
    queryFn: () => chatService.getHotelAdminMessages(hotelSlug, userUuid),
    enabled: !!hotelSlug && !!userUuid,
    refetchInterval: CHAT_MESSAGES_POLL_MS,
    refetchIntervalInBackground: false,
  })
}

export function useSendAsHotel(hotelSlug: string, userUuid: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (content: string) => chatService.sendAsHotel(hotelSlug, userUuid, content),

    onMutate: async (content) => {
      const key = chatKeys.hotelAdminMessages(hotelSlug, userUuid)
      await qc.cancelQueries({ queryKey: key })
      const previous = qc.getQueryData<Message[]>(key)
      qc.setQueryData<Message[]>(key, (old = []) => [optimisticMessage(content), ...old])
      return { previous }
    },

    onError: (_err, _vars, context) => {
      if (context?.previous !== undefined) {
        qc.setQueryData(chatKeys.hotelAdminMessages(hotelSlug, userUuid), context.previous)
      }
    },

    onSettled: () => {
      qc.invalidateQueries({ queryKey: chatKeys.hotelAdminMessages(hotelSlug, userUuid) })
      qc.invalidateQueries({ queryKey: chatKeys.hotelConversations(hotelSlug) })
    },
  })
}

export function useMarkHotelAdminRead(hotelSlug: string, userUuid: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: () => chatService.markHotelAdminRead(hotelSlug, userUuid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: chatKeys.hotelConversations(hotelSlug) })
      qc.invalidateQueries({ queryKey: chatKeys.hotelUnread(hotelSlug) })
    },
  })
}
