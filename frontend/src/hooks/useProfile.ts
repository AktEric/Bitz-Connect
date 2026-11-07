import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import userService from '@/services/user.service'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/contexts/ToastContext'
import { extractApiError } from '@/utils/api-error'
import type { UpdateProfileData, CreateExperienceData, Experience } from '@/types/user.types'

function hotelLinkMessage(experience?: Experience): string {
  switch (experience?.hotel_link_status) {
    case 'approved':
      return 'Você já possui um vínculo aprovado com esta empresa.'
    case 'pending':
      return 'Solicitação de vínculo enviada. Aguarde a aprovação do administrador da empresa.'
    case 'rejected':
      return 'Experiência salva. O vínculo com esta empresa havia sido recusado anteriormente.'
    default:
      return 'Experiência salva com sucesso.'
  }
}

/**
 * Hook for profile data fetching and mutations.
 * Requirements: 2.1, 2.2, 2.3, 2.5, 2.6
 */

export const profileKeys = {
  me: ['profile', 'me'] as const,
  user: (uuid: string) => ['profile', 'user', uuid] as const,
}

/** Authenticated user's own profile */
export function useMyProfile() {
  return useQuery({
    queryKey: profileKeys.me,
    queryFn: () => userService.getMe().then((r) => r.data),
  })
}

/** Public profile of any user by UUID */
export function useUserProfile(uuid: string) {
  return useQuery({
    queryKey: profileKeys.user(uuid),
    queryFn: () => userService.getUser(uuid).then((r) => r.data),
    enabled: !!uuid,
  })
}

/** Paginated posts from a specific user */
export function useUserPosts(uuid: string, page = 1) {
  return useQuery({
    queryKey: ['user-posts', uuid, page],
    queryFn: () => userService.getUserPosts(uuid, page),
    enabled: !!uuid,
  })
}

/** Update profile fields */
export function useUpdateProfile() {
  const qc = useQueryClient()
  const { setUser } = useAuth()

  return useMutation({
    mutationFn: (data: UpdateProfileData) => userService.updateProfile(data),
    onSuccess: (res) => {
      qc.setQueryData(profileKeys.me, res.data)
      setUser(res.data)
    },
  })
}

/** Upload avatar */
export function useUploadAvatar() {
  const qc = useQueryClient()
  const { setUser } = useAuth()

  return useMutation({
    mutationFn: (file: File) => userService.uploadAvatar(file),
    onSuccess: (res) => {
      qc.setQueryData(profileKeys.me, res.data)
      setUser(res.data)
    },
  })
}

/** Upload banner */
export function useUploadBanner() {
  const qc = useQueryClient()
  const { setUser } = useAuth()

  return useMutation({
    mutationFn: (file: File) => userService.uploadBanner(file),
    onSuccess: (res) => {
      qc.setQueryData(profileKeys.me, res.data)
      setUser(res.data)
    },
  })
}

/** Add experience */
export function useAddExperience() {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: (data: CreateExperienceData) => userService.addExperience(data),
    onSuccess: (res) => {
      success(hotelLinkMessage(res.data))
      qc.invalidateQueries({ queryKey: profileKeys.me })
    },
    onError: (err) => error(extractApiError(err)),
  })
}

/** Update experience */
export function useUpdateExperience() {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<CreateExperienceData> }) =>
      userService.updateExperience(id, data),
    onSuccess: (res) => {
      success(hotelLinkMessage(res.data))
      qc.invalidateQueries({ queryKey: profileKeys.me })
    },
    onError: (err) => error(extractApiError(err)),
  })
}

/** Delete experience */
export function useDeleteExperience() {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: (id: number) => userService.deleteExperience(id),
    onSuccess: () => {
      success('Experiência removida com sucesso.')
      qc.invalidateQueries({ queryKey: profileKeys.me })
    },
    onError: (err) => error(extractApiError(err)),
  })
}
