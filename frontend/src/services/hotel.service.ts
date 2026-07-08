import api from './api'
import type { ApiResponse, PaginatedResponse } from '@/types/api.types'
import type { Hotel, CreateHotelData, UpdateHotelData, Employee, HotelClaim } from '@/types/hotel.types'
import type { Job } from '@/types/job.types'
import type { User } from '@/types/auth.types'

/**
 * Hotel service — wraps all /hotels and /me/hotels API calls.
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.8
 */
const hotelService = {
  /** GET /hotels/:slug — public hotel profile */
  async getHotel(slug: string): Promise<ApiResponse<Hotel>> {
    const res = await api.get<ApiResponse<Hotel>>(`/hotels/${slug}`)
    return res.data
  },

  /** POST /hotels — create a new hotel */
  async createHotel(data: CreateHotelData): Promise<ApiResponse<Hotel>> {
    const res = await api.post<ApiResponse<Hotel>>('/hotels', data)
    return res.data
  },

  /** PUT /hotels/:slug — update hotel (HotelAdmin only) */
  async updateHotel(slug: string, data: UpdateHotelData): Promise<ApiResponse<Hotel>> {
    const res = await api.put<ApiResponse<Hotel>>(`/hotels/${slug}`, data)
    return res.data
  },

  /** POST /hotels/:slug/logo — upload logo (HotelAdmin only) */
  async uploadLogo(slug: string, file: File): Promise<ApiResponse<Hotel>> {
    const form = new FormData()
    form.append('image', file)
    const res = await api.post<ApiResponse<Hotel>>(`/hotels/${slug}/logo`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return res.data
  },

  /** POST /hotels/:slug/cover — upload cover (HotelAdmin only) */
  async uploadCover(slug: string, file: File): Promise<ApiResponse<Hotel>> {
    const form = new FormData()
    form.append('image', file)
    const res = await api.post<ApiResponse<Hotel>>(`/hotels/${slug}/cover`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return res.data
  },

  /** GET /me/hotels — hotels managed by the authenticated user */
  async getMyHotels(page = 1): Promise<PaginatedResponse<Hotel>> {
    const res = await api.get<PaginatedResponse<Hotel>>('/me/hotels', { params: { page } })
    return res.data
  },

  /** GET /hotels/directory — public hotel directory (feed "Hotéis" tab) */
  async getDirectory(page = 1): Promise<PaginatedResponse<Hotel>> {
    const res = await api.get<PaginatedResponse<Hotel>>('/hotels/directory', { params: { page } })
    return res.data
  },

  /** GET /hotels/:slug/jobs — active jobs for a hotel (paginated) */
  async getHotelJobs(slug: string, page = 1): Promise<PaginatedResponse<Job>> {
    const res = await api.get<PaginatedResponse<Job>>(`/hotels/${slug}/jobs`, { params: { page } })
    return res.data
  },

  /** GET /hotels/:slug/admins — admin users of a hotel */
  async getHotelAdmins(slug: string): Promise<ApiResponse<User[]>> {
    const res = await api.get<ApiResponse<User[]>>(`/hotels/${slug}/admins`)
    return res.data
  },

  /** PATCH /hotels/:slug/promote/:userId — promote employee to admin */
  async promoteUser(slug: string, userId: number): Promise<ApiResponse<null>> {
    const res = await api.patch<ApiResponse<null>>(`/hotels/${slug}/promote/${userId}`)
    return res.data
  },

  /** PATCH /hotels/:slug/demote/:userId — demote admin to employee */
  async demoteUser(slug: string, userId: number): Promise<ApiResponse<null>> {
    const res = await api.patch<ApiResponse<null>>(`/hotels/${slug}/demote/${userId}`)
    return res.data
  },

  // ── Follow / Unfollow ────────────────────────────────────────

  /** POST /hotels/:slug/follow — follow a hotel (Req 8.2) */
  async followHotel(slug: string): Promise<ApiResponse<null>> {
    const res = await api.post<ApiResponse<null>>(`/hotels/${slug}/follow`)
    return res.data
  },

  /** DELETE /hotels/:slug/follow — unfollow a hotel (Req 8.3) */
  async unfollowHotel(slug: string): Promise<ApiResponse<null>> {
    const res = await api.delete<ApiResponse<null>>(`/hotels/${slug}/follow`)
    return res.data
  },

  // ── Employees ───────────────────────────────────────────────

  /** GET /hotels/:slug/employees — approved employees (Req 5.1) */
  async getEmployees(slug: string): Promise<ApiResponse<Employee[]>> {
    const res = await api.get<ApiResponse<Employee[]>>(`/hotels/${slug}/employees`)
    return res.data
  },

  /** GET /hotels/:slug/pending-employees — pending requests (Req 6.4) */
  async getPendingEmployees(slug: string): Promise<ApiResponse<Employee[]>> {
    const res = await api.get<ApiResponse<Employee[]>>(`/hotels/${slug}/pending-employees`)
    return res.data
  },

  /** PATCH /hotels/:slug/employees/:userId/approve — approve link (Req 6.6) */
  async approveEmployee(slug: string, userId: number): Promise<ApiResponse<null>> {
    const res = await api.patch<ApiResponse<null>>(`/hotels/${slug}/employees/${userId}/approve`)
    return res.data
  },

  /** PATCH /hotels/:slug/employees/:userId/reject — reject link (Req 6.7) */
  async rejectEmployee(slug: string, userId: number): Promise<ApiResponse<null>> {
    const res = await api.patch<ApiResponse<null>>(`/hotels/${slug}/employees/${userId}/reject`)
    return res.data
  },

  // ── Claim ───────────────────────────────────────────────────

  /** POST /hotels/:slug/claim — claim hotel ownership (Req 7.2) */
  async claimHotel(slug: string, message?: string): Promise<ApiResponse<HotelClaim>> {
    const res = await api.post<ApiResponse<HotelClaim>>(`/hotels/${slug}/claim`, { message })
    return res.data
  },
}

export default hotelService
