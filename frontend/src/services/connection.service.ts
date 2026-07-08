import api from './api'
import type { ApiResponse } from '@/types/api.types'
import type { ChatUser } from '@/types/api.types'

/**
 * Raw connection record returned by the backend.
 */
export interface ConnectionRecord {
  uuid: string
  status: 'pending' | 'accepted' | 'rejected'
  requester: ChatUser
  addressee: ChatUser
  created_at: string
}

/**
 * Connection service — wraps all /connections API calls.
 */
const connectionService = {
  /** POST /connections/:uuid — send a connection invite */
  async send(userUuid: string): Promise<void> {
    await api.post(`/connections/${userUuid}`)
  },

  /** DELETE /connections/:uuid — cancel invite or disconnect */
  async remove(userUuid: string): Promise<void> {
    await api.delete(`/connections/${userUuid}`)
  },

  /** PATCH /connections/:uuid/accept — accept a pending invite */
  async accept(userUuid: string): Promise<void> {
    await api.patch(`/connections/${userUuid}/accept`)
  },

  /** PATCH /connections/:uuid/reject — reject a pending invite */
  async reject(userUuid: string): Promise<void> {
    await api.patch(`/connections/${userUuid}/reject`)
  },

  /** GET /connections — list accepted connections */
  async getConnections(): Promise<ApiResponse<ConnectionRecord[]>> {
    const res = await api.get<ApiResponse<ConnectionRecord[]>>('/connections')
    return res.data
  },

  /** GET /connections/pending — list pending invites received */
  async getPending(): Promise<ApiResponse<ConnectionRecord[]>> {
    const res = await api.get<ApiResponse<ConnectionRecord[]>>('/connections/pending')
    return res.data
  },
}

export default connectionService
