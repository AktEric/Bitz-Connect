/**
 * Standard API response envelope for all successful responses.
 * Requirements: 8.1, 8.3
 */
export interface ApiResponse<T = unknown> {
  success: true
  message: string
  data: T
}

/**
 * Standard API response for paginated collections.
 * Requirements: 8.1, 8.3
 */
export interface PaginatedResponse<T = unknown> {
  success: true
  message: string
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
}

/**
 * Standard API response envelope for all error responses.
 * Requirements: 8.2, 8.3
 */
export interface ApiErrorResponse {
  success: false
  message: string
  errors: Record<string, string[]> | string[]
}

/**
 * Cursor-based pagination response for infinite scroll (Feed).
 */
export interface CursorPaginatedResponse<T = unknown> {
  success: true
  message: string
  data: T[]
  meta: {
    next_cursor: string | null
    prev_cursor: string | null
    per_page: number
  }
}

// ──────────────────────────────────────────────────────────
// Chat types
// ──────────────────────────────────────────────────────────

export type MessageType = 'text' | 'job' | 'post' | 'profile' | 'hotel' | 'img-stub' | 'file-stub'

export interface JobMetadata {
  job_uuid: string
  title: string
  hotel_name: string
  hotel_logo: string | null
  location: string | null
  contract_type: string | null
  is_active: boolean
}

export interface ChatUser {
  uuid: string
  name: string
  avatar_url: string | null
  city?: string | null
}

/**
 * Um dos lados de uma conversa — pode ser uma pessoa ou a caixa de entrada
 * compartilhada de um hotel (quando a mensagem foi enviada/endereçada em
 * nome do hotel, não de um admin específico).
 */
export type ChatParty =
  | { type: 'user'; uuid: string; name: string; avatar_url: string | null }
  | { type: 'hotel'; uuid: string; slug: string; name: string; avatar_url: string | null }

export interface Message {
  uuid: string
  content: string
  is_mine: boolean
  read_at: string | null
  created_at: string
  sender: ChatParty
  type: MessageType
  metadata: JobMetadata | Record<string, unknown> | null
}

export interface Conversation {
  party: ChatParty
  last_message: {
    content: string
    created_at: string
    is_mine: boolean
  }
  unread_count: number
}

// ──────────────────────────────────────────────────────────
// Connection types
// ──────────────────────────────────────────────────────────

export type ConnectionStatus =
  | 'pending_sent'
  | 'pending_received'
  | 'accepted'
  | 'rejected'
  | null

export interface Connection {
  uuid: string
  user: ChatUser // the other person
  status: 'pending' | 'accepted'
  created_at: string
}
