import api from './api'
import type { ApiResponse } from '@/types/api.types'
import type {
  AddGroupMembersPayload,
  ChatGroup,
  ChatGroupDetail,
  ChatGroupMessage,
  CompanyMember,
  CreateChatGroupPayload,
  Department,
} from '@/types/chatGroup.types'

/**
 * Chat corporativo — grupos da empresa, setores e membros.
 */
const chatGroupService = {
  async list(): Promise<ChatGroup[]> {
    const res = await api.get<ApiResponse<ChatGroup[]>>('/chat-groups')
    return res.data.data
  },

  async unreadCount(): Promise<{ count: number }> {
    const res = await api.get<ApiResponse<{ count: number }>>('/chat-groups/unread-count')
    return res.data.data
  },

  async get(groupUuid: string): Promise<ChatGroupDetail> {
    const res = await api.get<ApiResponse<ChatGroupDetail>>(`/chat-groups/${groupUuid}`)
    return res.data.data
  },

  async create(hotelSlug: string, payload: CreateChatGroupPayload): Promise<ChatGroup> {
    const res = await api.post<ApiResponse<ChatGroup>>(`/hotels/${hotelSlug}/chat-groups`, payload)
    return res.data.data
  },

  async update(
    groupUuid: string,
    payload: Partial<Pick<ChatGroup, 'name' | 'description' | 'only_admins_can_post'>>,
  ): Promise<ChatGroup> {
    const res = await api.patch<ApiResponse<ChatGroup>>(`/chat-groups/${groupUuid}`, payload)
    return res.data.data
  },

  async remove(groupUuid: string): Promise<void> {
    await api.delete(`/chat-groups/${groupUuid}`)
  },

  async addMembers(groupUuid: string, payload: AddGroupMembersPayload): Promise<{ added: number }> {
    const res = await api.post<ApiResponse<{ added: number }>>(`/chat-groups/${groupUuid}/members`, payload)
    return res.data.data
  },

  async removeMember(groupUuid: string, userUuid: string): Promise<void> {
    await api.delete(`/chat-groups/${groupUuid}/members/${userUuid}`)
  },

  async setMemberRole(groupUuid: string, userUuid: string, role: 'admin' | 'member'): Promise<void> {
    await api.patch(`/chat-groups/${groupUuid}/members/${userUuid}/role`, { role })
  },

  async leave(groupUuid: string): Promise<void> {
    await api.delete(`/chat-groups/${groupUuid}/membership`)
  },

  async mute(groupUuid: string, muted: boolean): Promise<void> {
    await api.patch(`/chat-groups/${groupUuid}/mute`, { muted })
  },

  /** Mensagens vêm da mais nova para a mais antiga — inverter para exibir. */
  async messages(groupUuid: string): Promise<ChatGroupMessage[]> {
    const res = await api.get<ApiResponse<ChatGroupMessage[]>>(`/chat-groups/${groupUuid}/messages`)
    return res.data.data
  },

  async send(groupUuid: string, content: string, isAnnouncement = false): Promise<ChatGroupMessage> {
    const res = await api.post<ApiResponse<ChatGroupMessage>>(`/chat-groups/${groupUuid}/messages`, {
      content,
      is_announcement: isAnnouncement,
    })
    return res.data.data
  },

  async markRead(groupUuid: string): Promise<void> {
    await api.patch(`/chat-groups/${groupUuid}/read`)
  },

  // ── Setores e membros da empresa ─────────────────────────

  async departments(hotelSlug: string): Promise<Department[]> {
    const res = await api.get<ApiResponse<Department[]>>(`/hotels/${hotelSlug}/departments`)
    return res.data.data
  },

  async createDepartment(hotelSlug: string, name: string): Promise<Department> {
    const res = await api.post<ApiResponse<Department>>(`/hotels/${hotelSlug}/departments`, { name })
    return res.data.data
  },

  async companyMembers(hotelSlug: string): Promise<CompanyMember[]> {
    const res = await api.get<ApiResponse<CompanyMember[]>>(`/hotels/${hotelSlug}/members`)
    return res.data.data
  },

  async setMemberDepartment(hotelSlug: string, userUuid: string, departmentUuid: string | null): Promise<void> {
    await api.patch(`/hotels/${hotelSlug}/members/${userUuid}/department`, { department_uuid: departmentUuid })
  },
}

export default chatGroupService
