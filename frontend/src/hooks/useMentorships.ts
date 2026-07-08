import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import mentorshipService from '@/services/mentorship.service'
import { useToast } from '@/contexts/ToastContext'
import { extractApiError } from '@/utils/api-error'
import type { CreateMentorshipData, BookingStatus } from '@/types/mentorship.types'

/**
 * Hooks de mentorias (§11).
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
export const mentorshipKeys = {
  all: ['mentorships'] as const,
  list: (params: { page?: number; category?: string; q?: string }) => ['mentorships', 'list', params] as const,
  detail: (uuid: string) => ['mentorships', 'detail', uuid] as const,
  mentor: (userUuid: string) => ['mentorships', 'mentor', userUuid] as const,
  bookings: (uuid: string) => ['mentorships', 'bookings', uuid] as const,
}

export function useMentorships(params: { page?: number; category?: string; q?: string } = {}) {
  return useQuery({ queryKey: mentorshipKeys.list(params), queryFn: () => mentorshipService.getMentorships(params) })
}

export function useMentorship(uuid: string) {
  return useQuery({
    queryKey: mentorshipKeys.detail(uuid),
    queryFn: () => mentorshipService.getMentorship(uuid).then((r) => r.data),
    enabled: !!uuid,
  })
}

export function useMentorMentorships(userUuid: string, enabled = true) {
  return useQuery({
    queryKey: mentorshipKeys.mentor(userUuid),
    queryFn: () => mentorshipService.getMentorMentorships(userUuid),
    enabled: enabled && !!userUuid,
  })
}

export function useMentorshipBookings(uuid: string, enabled = true) {
  return useQuery({
    queryKey: mentorshipKeys.bookings(uuid),
    queryFn: () => mentorshipService.getBookings(uuid),
    enabled: enabled && !!uuid,
  })
}

export function useCreateMentorship() {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (data: CreateMentorshipData) => mentorshipService.createMentorship(data).then((r) => r.data),
    onSuccess: () => { success('Mentoria criada.'); qc.invalidateQueries({ queryKey: mentorshipKeys.all }) },
    onError: (err) => error(extractApiError(err)),
  })
}

export function useUpdateMentorship(uuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (data: Partial<CreateMentorshipData>) => mentorshipService.updateMentorship(uuid, data).then((r) => r.data),
    onSuccess: () => { success('Mentoria atualizada.'); qc.invalidateQueries({ queryKey: mentorshipKeys.detail(uuid) }) },
    onError: (err) => error(extractApiError(err)),
  })
}

export function usePublishMentorship(uuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: () => mentorshipService.publishMentorship(uuid).then((r) => r.data),
    onSuccess: () => { success('Mentoria publicada.'); qc.invalidateQueries({ queryKey: mentorshipKeys.detail(uuid) }) },
    onError: (err) => error(extractApiError(err)),
  })
}

export function useBookMentorship(uuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (message?: string) => mentorshipService.book(uuid, message).then((r) => r.data),
    onSuccess: () => { success('Solicitação enviada.'); qc.invalidateQueries({ queryKey: mentorshipKeys.detail(uuid) }) },
    onError: (err) => error(extractApiError(err)),
  })
}

export function useUpdateBooking(mentorshipUuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (vars: { bookingUuid: string; status: BookingStatus }) =>
      mentorshipService.updateBooking(mentorshipUuid, vars.bookingUuid, vars.status).then((r) => r.data),
    onSuccess: () => { success('Solicitação atualizada.'); qc.invalidateQueries({ queryKey: mentorshipKeys.bookings(mentorshipUuid) }) },
    onError: (err) => error(extractApiError(err)),
  })
}
