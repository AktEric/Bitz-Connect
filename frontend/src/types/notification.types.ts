// ── Categories ────────────────────────────────────────────────
export type NotificationCategory =
  | 'connection'
  | 'message'
  | 'feed'
  | 'job'
  | 'hotel'
  | 'profile'
  | 'system'

// ── Types per category ────────────────────────────────────────
export type NotificationType =
  // connection
  | 'connection_invite'
  | 'connection_accepted'
  | 'connection_rejected'
  | 'connection_cancelled'
  // message
  | 'new_message'
  | 'unread_messages'
  // feed
  | 'post_liked'
  | 'post_commented'
  | 'comment_replied'
  | 'comment_liked'
  | 'mention_in_post'
  | 'mention_in_comment'
  // job
  | 'job_application_received'
  | 'job_application_updated'
  | 'job_application_accepted'
  | 'job_application_rejected'
  | 'new_job_from_hotel'
  | 'new_job_from_connection'
  // hotel
  | 'hotel_link_requested'
  | 'hotel_link_approved'
  | 'hotel_link_rejected'
  | 'hotel_admin_added'
  // profile
  | 'profile_viewed'
  | 'complete_profile'
  // generic fallback
  | string

export interface NotificationActor {
  uuid: string
  name: string
  avatar_url: string | null
  plan?: import('./subscription.types').PlanTier | null
  is_pro?: boolean | null
}

export interface AppNotification {
  uuid: string
  type: NotificationType
  category: NotificationCategory
  title: string
  description: string | null
  link: string | null
  read: boolean
  created_at: string
  actor: NotificationActor | null
}

export interface NotificationUnreadCount {
  count: number
  by_category: Partial<Record<NotificationCategory, number>>
}
