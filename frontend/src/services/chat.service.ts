import api from './api'
import type { ApiResponse, Message, Conversation } from '@/types/api.types'

/**
 * Chat service — wraps all /chat API calls, tanto o lado pessoal quanto a
 * caixa de entrada compartilhada de um hotel.
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

  /** POST /chat/:uuid/messages — foto, arquivo ou mensagem de voz (multipart) */
  async sendFile(userUuid: string, file: File, opts: { voice?: boolean; duration?: number; caption?: string } = {}): Promise<Message> {
    const fd = new FormData()
    fd.append('file', file)
    if (opts.voice) fd.append('voice', '1')
    if (opts.duration) fd.append('duration', String(opts.duration))
    if (opts.caption) fd.append('content', opts.caption)
    const res = await api.post<ApiResponse<Message>>(`/chat/${userUuid}/messages`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    return res.data.data
  },

  /** POST /chat/:uuid/messages — figurinha ou GIF */
  async sendRich(userUuid: string, payload: { type: 'sticker' | 'gif' | 'listing'; metadata: Record<string, string>; content?: string }): Promise<Message> {
    const res = await api.post<ApiResponse<Message>>(`/chat/${userUuid}/messages`, payload)
    return res.data.data
  },

  /** POST /chat/messages/:uuid/react — reage (ou remove a reação, com null) a uma mensagem */
  async react(messageUuid: string, emoji: string | null): Promise<void> {
    await api.post(`/chat/messages/${messageUuid}/react`, { emoji })
  },

  /** GET/PUT /me/chat-settings — confirmação de leitura (visualizado) */
  async getSettings(): Promise<{ read_receipts: boolean }> {
    return (await api.get<ApiResponse<{ read_receipts: boolean }>>('/me/chat-settings')).data.data
  },
  async setReadReceipts(on: boolean): Promise<{ read_receipts: boolean }> {
    return (await api.put<ApiResponse<{ read_receipts: boolean }>>('/me/chat-settings', { read_receipts: on })).data.data
  },

  /** Figurinhas próprias */
  async myStickers(): Promise<{ items: { uuid: string }[]; max: number }> {
    return (await api.get<ApiResponse<{ items: { uuid: string }[]; max: number }>>('/stickers')).data.data
  },
  async addSticker(file: File): Promise<{ uuid: string }> {
    const fd = new FormData()
    fd.append('image', file)
    return (await api.post<ApiResponse<{ uuid: string }>>('/stickers', fd, { headers: { 'Content-Type': 'multipart/form-data' } })).data.data
  },
  async saveSticker(uuid: string): Promise<void> {
    await api.post(`/stickers/${uuid}/save`)
  },
  async removeSticker(uuid: string): Promise<void> {
    await api.delete(`/stickers/${uuid}`)
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

  // ── Lado pessoal falando com a caixa compartilhada de um hotel ──────────

  /** GET /chat/hotels/:slug/messages */
  async getHotelThreadMessages(hotelSlug: string): Promise<Message[]> {
    const res = await api.get<ApiResponse<Message[]>>(`/chat/hotels/${hotelSlug}/messages`)
    return res.data.data
  },

  /** POST /chat/hotels/:slug/messages */
  async sendToHotel(hotelSlug: string, content: string): Promise<Message> {
    const res = await api.post<ApiResponse<Message>>(`/chat/hotels/${hotelSlug}/messages`, { content })
    return res.data.data
  },

  /** PATCH /chat/hotels/:slug/read */
  async markHotelThreadRead(hotelSlug: string): Promise<void> {
    await api.patch(`/chat/hotels/${hotelSlug}/read`)
  },

  // ── Caixa de entrada compartilhada do hotel (painel do hotel) ──────────

  /** GET /hotels/:slug/chat/unread-count */
  async getHotelUnreadCount(hotelSlug: string): Promise<{ count: number }> {
    const res = await api.get<ApiResponse<{ count: number }>>(`/hotels/${hotelSlug}/chat/unread-count`)
    return res.data.data
  },

  /** GET /hotels/:slug/chat/conversations */
  async getHotelConversations(hotelSlug: string): Promise<Conversation[]> {
    const res = await api.get<ApiResponse<Conversation[]>>(`/hotels/${hotelSlug}/chat/conversations`)
    return res.data.data
  },

  /** GET /hotels/:slug/chat/:userUuid/messages */
  async getHotelAdminMessages(hotelSlug: string, userUuid: string): Promise<Message[]> {
    const res = await api.get<ApiResponse<Message[]>>(`/hotels/${hotelSlug}/chat/${userUuid}/messages`)
    return res.data.data
  },

  /** POST /hotels/:slug/chat/:userUuid/messages — enviado como o hotel */
  async sendAsHotel(hotelSlug: string, userUuid: string, content: string): Promise<Message> {
    const res = await api.post<ApiResponse<Message>>(`/hotels/${hotelSlug}/chat/${userUuid}/messages`, { content })
    return res.data.data
  },

  /** PATCH /hotels/:slug/chat/:userUuid/read */
  async markHotelAdminRead(hotelSlug: string, userUuid: string): Promise<void> {
    await api.patch(`/hotels/${hotelSlug}/chat/${userUuid}/read`)
  },
}

export default chatService
