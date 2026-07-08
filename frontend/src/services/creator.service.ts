import api from './api'
import type { ApiResponse } from '@/types/api.types'
import type { User } from '@/types/auth.types'

export interface FollowResult { is_following: boolean; followers_count: number }

/**
 * Serviço da página do criador.
 * Sprint: monetization-hotelconnect-pro (Página do Criador)
 */
const creatorService = {
  /** GET /creators/:uuid — perfil público do criador */
  async getCreator(uuid: string): Promise<ApiResponse<User>> {
    const res = await api.get<ApiResponse<User>>(`/creators/${uuid}`)
    return res.data
  },

  /** POST /users/:uuid/follow */
  async follow(uuid: string): Promise<ApiResponse<FollowResult>> {
    const res = await api.post<ApiResponse<FollowResult>>(`/users/${uuid}/follow`)
    return res.data
  },

  /** DELETE /users/:uuid/follow */
  async unfollow(uuid: string): Promise<ApiResponse<FollowResult>> {
    const res = await api.delete<ApiResponse<FollowResult>>(`/users/${uuid}/follow`)
    return res.data
  },
}

export default creatorService
