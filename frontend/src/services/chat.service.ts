import api from './api'
import type { ApiResponse, Message, Conversation } from '@/types/api.types'

/**
 * Chat service — wraps all /chat API calls.
 * Requirements: 7.1
 */
const chatService = {
  /** GET /chat/conversations — list of conversations (one per interlocutor) */
  async getConversations(): Promise<Conversation[]> {
    const res = await api.get<ApiResponse<Conversation[]>>('/chat/conversations')
    return res.data.data
  },

  /** GET /chat/:uuid/messages — messages with a specific user (newest first — reverse to display) */
  async getMessages(userUuid: string): Promise<Message[]> {
    const res = await api.get<ApiResponse<Message[]>>(`/chat/${userUuid}/messages`)
    return res.data.data
  },

  /** POST /chat/:uuid/messages — send a message to a user */
  async sendMessage(userUuid: string, content: string): Promise<Message> {
    const res = await api.post<ApiResponse<Message>>(`/chat/${userUuid}/messages`, { content })
    return res.data.data
  },

  /** PATCH /chat/:uuid/read — mark messages from a user as read */
  async markRead(userUuid: string): Promise<void> {
    await api.patch(`/chat/${userUuid}/read`)
  },

  /** GET /chat/unread-count — total unread messages for the authenticated user */
  async getUnreadCount(): Promise<{ count: number }> {
    const res = await api.get<ApiResponse<{ count: number }>>('/chat/unread-count')
    return res.data.data
  },

  /** POST /chat/:uuid/messages — send a job share card to a user */
  async sendJobShare(userUuid: string, jobUuid: string): Promise<Message> {
    const res = await api.post<ApiResponse<Message>>(`/chat/${userUuid}/messages`, {
      content: 'Compartilhou uma vaga com você',
      type: 'job',
      metadata: { job_uuid: jobUuid },
    })
    return res.data.data
  },
}

export default chatService
