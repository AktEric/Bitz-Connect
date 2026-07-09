import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import hotelService from '@/services/hotel.service'
import type { UpdateHotelData } from '@/types/hotel.types'
import { useToast } from '@/contexts/ToastContext'
import { extractApiError } from '@/utils/api-error'

/**
 * Hook for hotel data fetching and mutations.
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.8
 */

export const hotelKeys = {
  hotel: (slug: string) => ['hotel', slug] as const,
  myHotels: ['hotel', 'mine'] as const,
}

/** Public hotel profile by slug */
export function useHotel(slug: string) {
  return useQuery({
    queryKey: hotelKeys.hotel(slug),
    queryFn: () => hotelService.getHotel(slug).then((r) => r.data),
    enabled: !!slug,
  })
}

/** Hotels managed by the authenticated user */
export function useMyHotels() {
  return useQuery({
    queryKey: hotelKeys.myHotels,
    queryFn: () => hotelService.getMyHotels(),
  })
}

/** Diretório público de hotéis (aba Hotéis do feed §10). */
export function useHotelDirectory(enabled = true) {
  return useQuery({
    queryKey: ['hotels', 'directory'],
    queryFn: () => hotelService.getDirectory(),
    enabled,
  })
}

/** Update hotel */
export function useUpdateHotel(slug: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: (data: UpdateHotelData) => hotelService.updateHotel(slug, data),
    onSuccess: (res) => {
      success('Hotel atualizado com sucesso.')
      qc.setQueryData(hotelKeys.hotel(slug), res.data)
      qc.invalidateQueries({ queryKey: hotelKeys.myHotels })
    },
    onError: (err) => {
      error(extractApiError(err))
    },
  })
}

/** Upload hotel logo */
export function useUploadLogo(slug: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (file: File) => hotelService.uploadLogo(slug, file),
    onSuccess: (res) => qc.setQueryData(hotelKeys.hotel(slug), res.data),
  })
}

/** Upload hotel cover */
export function useUploadCover(slug: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (file: File) => hotelService.uploadCover(slug, file),
    onSuccess: (res) => qc.setQueryData(hotelKeys.hotel(slug), res.data),
  })
}

// ── Follow / Unfollow ───────────────────────────────────────────────────────

/** Follow a hotel with optimistic update (Req 8.2) */
export function useFollowHotel(slug: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => hotelService.followHotel(slug),
    onMutate: async () => {
      await qc.cancelQueries({ queryKey: hotelKeys.hotel(slug) })
      const previous = qc.getQueryData(hotelKeys.hotel(slug))
      qc.setQueryData(hotelKeys.hotel(slug), (old: any) => {
        if (!old) return old
        return {
          ...old,
          is_following: true,
          followers_count: (old.followers_count ?? 0) + 1,
        }
      })
      return { previous }
    },
    onError: (_err, _vars, context) => {
      if (context?.previous) {
        qc.setQueryData(hotelKeys.hotel(slug), context.previous)
      }
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: hotelKeys.hotel(slug) })
    },
  })
}

/** Unfollow a hotel with optimistic update (Req 8.3) */
export function useUnfollowHotel(slug: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => hotelService.unfollowHotel(slug),
    onMutate: async () => {
      await qc.cancelQueries({ queryKey: hotelKeys.hotel(slug) })
      const previous = qc.getQueryData(hotelKeys.hotel(slug))
      qc.setQueryData(hotelKeys.hotel(slug), (old: any) => {
        if (!old) return old
        return {
          ...old,
          is_following: false,
          followers_count: Math.max(0, (old.followers_count ?? 1) - 1),
        }
      })
      return { previous }
    },
    onError: (_err, _vars, context) => {
      if (context?.previous) {
        qc.setQueryData(hotelKeys.hotel(slug), context.previous)
      }
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: hotelKeys.hotel(slug) })
    },
  })
}

// ── Employees ───────────────────────────────────────────────────────────────

export const employeeKeys = {
  employees: (slug: string) => ['hotel', slug, 'employees'] as const,
  pending:   (slug: string) => ['hotel', slug, 'pending-employees'] as const,
}

/** Approved employees of a hotel (Req 5.1) */
export function useHotelEmployees(slug: string) {
  return useQuery({
    queryKey: employeeKeys.employees(slug),
    queryFn: () => hotelService.getEmployees(slug).then((r) => r.data),
    enabled: !!slug,
  })
}

/** Pending employee link requests for admins (Req 6.4) */
export function usePendingEmployees(slug: string, enabled = true) {
  return useQuery({
    queryKey: employeeKeys.pending(slug),
    queryFn: () => hotelService.getPendingEmployees(slug).then((r) => r.data),
    enabled: !!slug && enabled,
  })
}

/** Approve an employee link (Req 6.6) */
export function useApproveEmployee(slug: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (userId: number) => hotelService.approveEmployee(slug, userId),
    onSuccess: () => {
      success('Vínculo aprovado com sucesso.')
      qc.invalidateQueries({ queryKey: employeeKeys.employees(slug) })
      qc.invalidateQueries({ queryKey: employeeKeys.pending(slug) })
    },
    onError: (err) => {
      error(extractApiError(err))
    },
  })
}

/** Reject an employee link (Req 6.7) */
export function useRejectEmployee(slug: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (userId: number) => hotelService.rejectEmployee(slug, userId),
    onSuccess: () => {
      success('Solicitação de vínculo rejeitada.')
      qc.invalidateQueries({ queryKey: employeeKeys.pending(slug) })
    },
    onError: (err) => {
      error(extractApiError(err))
    },
  })
}

// ── Claim ────────────────────────────────────────────────────────────────────

/** Claim hotel ownership (Req 7.2) */
export function useClaimHotel(slug: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (message?: string) => hotelService.claimHotel(slug, message),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: hotelKeys.hotel(slug) })
    },
  })
}

// ── Jobs / Admins ────────────────────────────────────────────────────────────

/** Hotel jobs */
export const hotelJobsKeys = {
  jobs: (slug: string) => ['hotel', slug, 'jobs'] as const,
  admins: (slug: string) => ['hotel', slug, 'admins'] as const,
}

export function useHotelJobs(slug: string, page = 1) {
  return useQuery({
    queryKey: [...hotelJobsKeys.jobs(slug), page],
    queryFn: () => hotelService.getHotelJobs(slug, page),
    enabled: !!slug,
  })
}

export function useHotelAdmins(slug: string) {
  return useQuery({
    queryKey: hotelJobsKeys.admins(slug),
    queryFn: () => hotelService.getHotelAdmins(slug).then((r) => r.data),
    enabled: !!slug,
  })
}

/** Promote an employee to admin (Req 9.6) */
export function usePromoteUser(slug: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (userId: number) => hotelService.promoteUser(slug, userId),
    onSuccess: () => {
      success('Colaborador promovido a admin com sucesso.')
      qc.invalidateQueries({ queryKey: hotelJobsKeys.admins(slug) })
      qc.invalidateQueries({ queryKey: employeeKeys.employees(slug) })
    },
    onError: (err) => error(extractApiError(err)),
  })
}

/** Demote an admin to employee (Req 9.7, 9.8) */
export function useDemoteUser(slug: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (userId: number) => hotelService.demoteUser(slug, userId),
    onSuccess: () => {
      success('Admin rebaixado a colaborador com sucesso.')
      qc.invalidateQueries({ queryKey: hotelJobsKeys.admins(slug) })
      qc.invalidateQueries({ queryKey: employeeKeys.employees(slug) })
    },
    onError: (err) => error(extractApiError(err)),
  })
}
