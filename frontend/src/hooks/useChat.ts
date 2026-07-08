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
}

/**
 * Fetches the list of conversations with polling every 30s.
 * Requirements: 5.3, 5.5
 */
export function useConversations() {
  return useQuery<Conversation[]>({
    queryKey: chatKeys.conversations,
    queryFn: () => chatService.getConversations(),
    refetchInterval: CHAT_CONVERSATIONS_POLL_MS,
  })
}

/**
 * Fetches messages for a conversation, polling every 5s when the window is visible.
 * Requirements: 5.2, 5.4, 5.5
 */
export function useMessages(userUuid: string) {
  return useQuery<Message[]>({
    queryKey: chatKeys.messages(userUuid),
    queryFn: () => chatService.getMessages(userUuid),
    enabled: !!userUuid,
    refetchInterval: CHAT_MESSAGES_POLL_MS,
    refetchIntervalInBackground: false,
  })
}

/**
 * Fetches the count of unread messages, polling every 30s.
 * Requirements: 4.5, 5.5
 */
export function useUnreadMessages() {
  return useQuery<{ count: number }>({
    queryKey: chatKeys.unread,
    queryFn: () => chatService.getUnreadCount(),
    refetchInterval: UNREAD_MESSAGES_POLL_MS,
  })
}

/**
 * Mutation to send a message with optimistic update.
 * Requirements: 7.1
 */
export function useSendMessage(userUuid: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (content: string) => chatService.sendMessage(userUuid, content),

    onMutate: async (content) => {
      await qc.cancelQueries({ queryKey: chatKeys.messages(userUuid) })

      const previous = qc.getQueryData<Message[]>(chatKeys.messages(userUuid))

      // Optimistically prepend the new message (server returns newest-first)
      const optimisticMsg: Message = {
        uuid: `optimistic-${Date.now()}`,
        content,
        is_mine: true,
        read_at: null,
        created_at: new Date().toISOString(),
        sender: { uuid: '', name: '', avatar_url: null },
        type: 'text',
        metadata: null,
      }

      qc.setQueryData<Message[]>(chatKeys.messages(userUuid), (old = []) => [
        optimisticMsg,
        ...old,
      ])

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

/**
 * Mutation to mark messages from a user as read.
 * Invalidates both conversations and unread count on success.
 * Requirements: 4.6, 9.7
 */
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
