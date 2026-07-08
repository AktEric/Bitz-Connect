import api from './api'
import type { PaginatedResponse } from '@/types/api.types'
import type { Hotel } from '@/types/hotel.types'
import type { Job } from '@/types/job.types'
import type { User } from '@/types/auth.types'

/**
 * Search service — wraps all /search/* API calls.
 * Requirements: 6.1, 6.2, 6.3
 */
const searchService = {
  /**
   * GET /search/hotels?q=&page=
   * Searches hotels by name, description, and location.
   * Requirements: 6.1
   */
  async searchHotels(q: string, page = 1): Promise<PaginatedResponse<Hotel>> {
    const res = await api.get<PaginatedResponse<Hotel>>('/search/hotels', {
      params: { q, page },
    })
    return res.data
  },

  /**
   * GET /search/jobs?q=&page=
   * Searches active jobs by title, description, and location.
   * Requirements: 6.2
   */
  async searchJobs(q: string, page = 1): Promise<PaginatedResponse<Job>> {
    const res = await api.get<PaginatedResponse<Job>>('/search/jobs', {
      params: { q, page },
    })
    return res.data
  },

  /**
   * GET /search/users?q=&page=
   * Searches users by name.
   * Requirements: 6.3
   */
  async searchUsers(q: string, page = 1): Promise<PaginatedResponse<User>> {
    const res = await api.get<PaginatedResponse<User>>('/search/users', {
      params: { q, page },
    })
    return res.data
  },
}

export default searchService
