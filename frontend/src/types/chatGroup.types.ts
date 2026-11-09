/**
 * Tipos do chat corporativo (grupos da empresa), setores e membros.
 */

export interface ChatGroupHotel {
  uuid: string
  slug: string
  name: string
  avatar_url: string | null
}

/** Enviada, entregue ou visualizada (só nas minhas mensagens). */
export type DeliveryStatus = 'sent' | 'delivered' | 'read'
export interface ChatReaction { emoji: string; count: number; mine: boolean; people: string[] }

export interface ChatGroup {
  uuid: string
  name: string
  /** Foto própria do grupo (se não houver, usa a logo da empresa). */
  avatar_url?: string | null
  description: string | null
  /** "Modo aviso": só administradores enviam mensagens. */
  only_admins_can_post: boolean
  members_count: number
  created_at: string
  hotel: ChatGroupHotel | null
  is_member: boolean
  my_role: 'admin' | 'member' | null
  can_manage: boolean
  can_post: boolean
  notifications_muted: boolean
  unread_count: number
  last_message: {
    content: string
    sender_name: string | null
    created_at: string
  } | null
}

export interface ChatGroupMember {
  uuid: string
  name: string
  avatar_url: string | null
  role: 'admin' | 'member'
  department: string | null
  is_company_admin: boolean
}

export interface ChatGroupDetail extends ChatGroup {
  members: ChatGroupMember[]
}

export interface ChatGroupMessage {
  uuid: string
  content: string
  type: string
  metadata?: Record<string, unknown> | null
  status?: DeliveryStatus | null
  reactions?: ChatReaction[]
  is_announcement: boolean
  is_mine: boolean
  created_at: string
  sender: {
    uuid: string | null
    name: string
    avatar_url: string | null
  }
}

export interface Department {
  uuid: string
  name: string
  members_count: number
}

export interface CompanyMember {
  uuid: string
  name: string
  avatar_url: string | null
  role: 'admin' | 'employee'
  department: { uuid: string; name: string } | null
}

export interface CreateChatGroupPayload {
  name: string
  description?: string
  only_admins_can_post?: boolean
  member_uuids?: string[]
  department_uuids?: string[]
  all_employees?: boolean
}

export interface AddGroupMembersPayload {
  user_uuids?: string[]
  department_uuids?: string[]
  all_employees?: boolean
}
