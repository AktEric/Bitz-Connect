import { useQuery, useMutation, useQueryClient, useInfiniteQuery } from '@tanstack/react-query'
import notificationService, { type GetNotificationsParams } from '@/services/notification.service'
import type { NotificationCategory } from '@/types/notification.types'

export const notificationKeys = {
  all:     ['notifications'] as const,
  list:    (params: GetNotificationsParams) => ['notifications', 'list', params] as const,
  unread:  ['notifications', 'unread-count'] as const,
}

/** Paginated list — used by the full Notifications page. */
export function useNotifications(params: GetNotificationsParams = {}) {
  return useQuery({
    queryKey: notificationKeys.list(params),
    queryFn:  () => notificationService.getAll(params),
  })
}

/** Infinite scroll variant — used by the Notifications page infinite list. */
export function useInfiniteNotifications(params: Omit<GetNotificationsParams, 'page'> = {}) {
  return useInfiniteQuery({
    queryKey: [...notificationKeys.list(params), 'infinite'],
    queryFn: ({ pageParam = 1 }) =>
      notificationService.getAll({ ...params, page: pageParam as number, per_page: 20 }),
    initialPageParam: 1,
    getNextPageParam: (lastPage) =>
      lastPage.meta.current_page < lastPage.meta.last_page
        ? lastPage.meta.current_page + 1
        : undefined,
  })
}

/** Dropdown bell — fetches only the latest 20 notifications (all categories). */
export function useNotificationsBell() {
  return useQuery({
    queryKey: notificationKeys.list({ per_page: 20 }),
    queryFn:  () => notificationService.getAll({ per_page: 20 }),
    refetchInterval: 30_000,
    select: (data) => data.data,
  })
}

export function useUnreadCount() {
  return useQuery({
    queryKey: notificationKeys.unread,
    queryFn:  () => notificationService.getUnreadCount(),
    refetchInterval: 30_000,
  })
}

export function useMarkNotificationRead() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (uuid: string) => notificationService.markRead(uuid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: notificationKeys.all })
      qc.invalidateQueries({ queryKey: notificationKeys.unread })
    },
  })
}

export function useMarkAllRead(category?: NotificationCategory) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => notificationService.markAllRead(category),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: notificationKeys.all })
      qc.invalidateQueries({ queryKey: notificationKeys.unread })
    },
  })
}

export function useDeleteNotification() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (uuid: string) => notificationService.deleteNotification(uuid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: notificationKeys.all })
      qc.invalidateQueries({ queryKey: notificationKeys.unread })
    },
  })
}
