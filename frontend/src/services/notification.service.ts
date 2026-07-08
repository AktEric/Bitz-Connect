import api from './api'
import type { PaginatedResponse } from '@/types/api.types'
import type {
  AppNotification,
  NotificationCategory,
  NotificationUnreadCount,
} from '@/types/notification.types'

// Re-export for backward compat
export type { AppNotification }

export interface GetNotificationsParams {
  category?: NotificationCategory
  unread?: boolean
  page?: number
  per_page?: number
}

const notificationService = {
  async getAll(
    params: GetNotificationsParams = {},
  ): Promise<PaginatedResponse<AppNotification>> {
    const res = await api.get<PaginatedResponse<AppNotification>>('/notifications', {
      params: {
        ...(params.category  ? { category: params.category }   : {}),
        ...(params.unread    ? { unread: 1 }                   : {}),
        ...(params.page      ? { page: params.page }           : {}),
        ...(params.per_page  ? { per_page: params.per_page }   : {}),
      },
    })
    return res.data
  },

  async getUnreadCount(): Promise<NotificationUnreadCount> {
    const res = await api.get<{ success: true; message: string; data: NotificationUnreadCount }>(
      '/notifications/unread-count',
    )
    return res.data.data
  },

  async markRead(uuid: string): Promise<void> {
    await api.patch(`/notifications/${uuid}/read`)
  },

  async markAllRead(category?: NotificationCategory): Promise<void> {
    await api.patch('/notifications/read-all', undefined, {
      params: category ? { category } : {},
    })
  },

  async deleteNotification(uuid: string): Promise<void> {
    await api.delete(`/notifications/${uuid}`)
  },
}

export default notificationService
