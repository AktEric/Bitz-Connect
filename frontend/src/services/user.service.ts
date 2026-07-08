import api from './api'
import type { ApiResponse, PaginatedResponse } from '@/types/api.types'
import type { User } from '@/types/auth.types'
import type {
  UpdateProfileData,
  CreateExperienceData,
  Experience,
} from '@/types/user.types'
import type { Post } from '@/types/feed.types'

/**
 * User profile service — wraps all /me and /users API calls.
 * Requirements: 2.1, 2.2, 2.3, 2.5, 2.6
 */
const userService = {
  /** GET /me — authenticated user's full profile */
  async getMe(): Promise<ApiResponse<User>> {
    const res = await api.get<ApiResponse<User>>('/me')
    return res.data
  },

  /** GET /users/:uuid — public profile */
  async getUser(uuid: string): Promise<ApiResponse<User>> {
    const res = await api.get<ApiResponse<User>>(`/users/${uuid}`)
    return res.data
  },

  /** PUT /me — update profile fields */
  async updateProfile(data: UpdateProfileData): Promise<ApiResponse<User>> {
    const res = await api.put<ApiResponse<User>>('/me', data)
    return res.data
  },

  /** POST /me/avatar — upload avatar image */
  async uploadAvatar(file: File): Promise<ApiResponse<User>> {
    const form = new FormData()
    form.append('image', file)
    const res = await api.post<ApiResponse<User>>('/me/avatar', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return res.data
  },

  /** POST /me/banner — upload banner image */
  async uploadBanner(file: File): Promise<ApiResponse<User>> {
    const form = new FormData()
    form.append('image', file)
    const res = await api.post<ApiResponse<User>>('/me/banner', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return res.data
  },

  /** POST /me/experiences */
  async addExperience(data: CreateExperienceData): Promise<ApiResponse<Experience>> {
    const res = await api.post<ApiResponse<Experience>>('/me/experiences', data)
    return res.data
  },

  /** PUT /me/experiences/:id */
  async updateExperience(
    id: number,
    data: Partial<CreateExperienceData>,
  ): Promise<ApiResponse<Experience>> {
    const res = await api.put<ApiResponse<Experience>>(`/me/experiences/${id}`, data)
    return res.data
  },

  /** DELETE /me/experiences/:id */
  async deleteExperience(id: number): Promise<void> {
    await api.delete(`/me/experiences/${id}`)
  },

  /** GET /users/:uuid/posts — paginated posts from a user */
  async getUserPosts(uuid: string, page = 1): Promise<PaginatedResponse<Post>> {
    const res = await api.get<PaginatedResponse<Post>>(`/users/${uuid}/posts`, { params: { page } })
    return res.data
  },
}

export default userService
