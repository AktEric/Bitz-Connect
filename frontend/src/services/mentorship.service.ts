import api from './api'
import type { ApiResponse, PaginatedResponse } from '@/types/api.types'
import type { Mentorship, MentorshipBooking, CreateMentorshipData, BookingStatus } from '@/types/mentorship.types'

/**
 * Serviço de mentorias (§11).
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
const mentorshipService = {
  async getMentorships(params: { page?: number; category?: string; q?: string } = {}): Promise<PaginatedResponse<Mentorship>> {
    const res = await api.get<PaginatedResponse<Mentorship>>('/mentorships', { params })
    return res.data
  },

  async getMentorship(uuid: string): Promise<ApiResponse<Mentorship>> {
    const res = await api.get<ApiResponse<Mentorship>>(`/mentorships/${uuid}`)
    return res.data
  },

  async getMentorMentorships(userUuid: string, page = 1): Promise<PaginatedResponse<Mentorship>> {
    const res = await api.get<PaginatedResponse<Mentorship>>(`/users/${userUuid}/mentorships`, { params: { page } })
    return res.data
  },

  async createMentorship(data: CreateMentorshipData): Promise<ApiResponse<Mentorship>> {
    const res = await api.post<ApiResponse<Mentorship>>('/mentorships', data)
    return res.data
  },

  async updateMentorship(uuid: string, data: Partial<CreateMentorshipData>): Promise<ApiResponse<Mentorship>> {
    const res = await api.put<ApiResponse<Mentorship>>(`/mentorships/${uuid}`, data)
    return res.data
  },

  async publishMentorship(uuid: string): Promise<ApiResponse<Mentorship>> {
    const res = await api.patch<ApiResponse<Mentorship>>(`/mentorships/${uuid}/publish`)
    return res.data
  },

  async book(uuid: string, message?: string): Promise<ApiResponse<MentorshipBooking>> {
    const res = await api.post<ApiResponse<MentorshipBooking>>(`/mentorships/${uuid}/book`, { message })
    return res.data
  },

  async getBookings(uuid: string, page = 1): Promise<PaginatedResponse<MentorshipBooking>> {
    const res = await api.get<PaginatedResponse<MentorshipBooking>>(`/mentorships/${uuid}/bookings`, { params: { page } })
    return res.data
  },

  async updateBooking(mentorshipUuid: string, bookingUuid: string, status: BookingStatus): Promise<ApiResponse<MentorshipBooking>> {
    const res = await api.patch<ApiResponse<MentorshipBooking>>(`/mentorships/${mentorshipUuid}/bookings/${bookingUuid}`, { status })
    return res.data
  },

  async getMyBookings(page = 1): Promise<PaginatedResponse<MentorshipBooking>> {
    const res = await api.get<PaginatedResponse<MentorshipBooking>>('/me/mentorship-bookings', { params: { page } })
    return res.data
  },
}

export default mentorshipService
