import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import chatGroupService from '@/services/chatGroup.service'
import type {
  AddGroupMembersPayload,
  ChatGroup,
  ChatGroupDetail,
  ChatGroupMessage,
  CompanyMember,
  CreateChatGroupPayload,
  Department,
} from '@/types/chatGroup.types'
import {
  CHAT_CONVERSATIONS_POLL_MS,
  CHAT_MESSAGES_POLL_MS,
  UNREAD_MESSAGES_POLL_MS,
} from '@/config/polling'

export const groupKeys = {
  all: ['chat-groups'] as const,
  list: ['chat-groups', 'list'] as const,
  unread: ['chat-groups', 'unread'] as const,
  detail: (uuid: string) => ['chat-groups', 'detail', uuid] as const,
  messages: (uuid: string) => ['chat-groups', 'messages', uuid] as const,
  departments: (slug: string) => ['company', 'departments', slug] as const,
  members: (slug: string) => ['company', 'members', slug] as const,
}

/** Grupos dos quais o usuário participa (polling leve). */
export function useChatGroups(enabled = true) {
  return useQuery<ChatGroup[]>({
    queryKey: groupKeys.list,
    queryFn: () => chatGroupService.list(),
    refetchInterval: CHAT_CONVERSATIONS_POLL_MS,
    enabled,
  })
}

export function useChatGroupsUnread() {
  return useQuery<{ count: number }>({
    queryKey: groupKeys.unread,
    queryFn: () => chatGroupService.unreadCount(),
    refetchInterval: UNREAD_MESSAGES_POLL_MS,
  })
}

export function useChatGroup(groupUuid: string | null) {
  return useQuery<ChatGroupDetail>({
    queryKey: groupKeys.detail(groupUuid ?? ''),
    queryFn: () => chatGroupService.get(groupUuid as string),
    enabled: !!groupUuid,
  })
}

export function useGroupMessages(groupUuid: string | null) {
  return useQuery<ChatGroupMessage[]>({
    queryKey: groupKeys.messages(groupUuid ?? ''),
    queryFn: () => chatGroupService.messages(groupUuid as string),
    enabled: !!groupUuid,
    refetchInterval: CHAT_MESSAGES_POLL_MS,
    refetchIntervalInBackground: false,
  })
}

export function useSendGroupMessage(groupUuid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ content, isAnnouncement }: { content: string; isAnnouncement?: boolean }) =>
      chatGroupService.send(groupUuid, content, isAnnouncement),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: groupKeys.messages(groupUuid) })
      qc.invalidateQueries({ queryKey: groupKeys.list })
      qc.invalidateQueries({ queryKey: groupKeys.unread })
    },
  })
}

export function useMarkGroupRead(groupUuid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => chatGroupService.markRead(groupUuid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: groupKeys.list })
      qc.invalidateQueries({ queryKey: groupKeys.unread })
    },
  })
}

export function useCreateChatGroup(hotelSlug: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateChatGroupPayload) => chatGroupService.create(hotelSlug, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: groupKeys.all }),
  })
}

export function useUpdateChatGroup(groupUuid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: Parameters<typeof chatGroupService.update>[1]) =>
      chatGroupService.update(groupUuid, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: groupKeys.all }),
  })
}

export function useDeleteChatGroup(groupUuid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => chatGroupService.remove(groupUuid),
    onSuccess: () => qc.invalidateQueries({ queryKey: groupKeys.all }),
  })
}

export function useAddGroupMembers(groupUuid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: AddGroupMembersPayload) => chatGroupService.addMembers(groupUuid, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: groupKeys.all }),
  })
}

export function useRemoveGroupMember(groupUuid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (userUuid: string) => chatGroupService.removeMember(groupUuid, userUuid),
    onSuccess: () => qc.invalidateQueries({ queryKey: groupKeys.all }),
  })
}

export function useSetGroupMemberRole(groupUuid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ userUuid, role }: { userUuid: string; role: 'admin' | 'member' }) =>
      chatGroupService.setMemberRole(groupUuid, userUuid, role),
    onSuccess: () => qc.invalidateQueries({ queryKey: groupKeys.all }),
  })
}

export function useLeaveGroup(groupUuid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => chatGroupService.leave(groupUuid),
    onSuccess: () => qc.invalidateQueries({ queryKey: groupKeys.all }),
  })
}

export function useMuteGroup(groupUuid: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (muted: boolean) => chatGroupService.mute(groupUuid, muted),
    onSuccess: () => qc.invalidateQueries({ queryKey: groupKeys.all }),
  })
}

// ── Setores e membros da empresa ─────────────────────────────

export function useDepartments(hotelSlug: string | null) {
  return useQuery<Department[]>({
    queryKey: groupKeys.departments(hotelSlug ?? ''),
    queryFn: () => chatGroupService.departments(hotelSlug as string),
    enabled: !!hotelSlug,
  })
}

export function useCompanyMembers(hotelSlug: string | null) {
  return useQuery<CompanyMember[]>({
    queryKey: groupKeys.members(hotelSlug ?? ''),
    queryFn: () => chatGroupService.companyMembers(hotelSlug as string),
    enabled: !!hotelSlug,
  })
}

export function useCreateDepartment(hotelSlug: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (name: string) => chatGroupService.createDepartment(hotelSlug, name),
    onSuccess: () => qc.invalidateQueries({ queryKey: groupKeys.departments(hotelSlug) }),
  })
}

export function useSetMemberDepartment(hotelSlug: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ userUuid, departmentUuid }: { userUuid: string; departmentUuid: string | null }) =>
      chatGroupService.setMemberDepartment(hotelSlug, userUuid, departmentUuid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: groupKeys.members(hotelSlug) })
      qc.invalidateQueries({ queryKey: groupKeys.departments(hotelSlug) })
    },
  })
}
