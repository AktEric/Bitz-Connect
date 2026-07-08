/**
 * Centralized polling interval constants.
 * Change these values here to affect all polling consumers across the app.
 * When WebSockets are adopted, set these to `false` to disable polling without
 * touching the hooks themselves.
 *
 * Requirements: 5.5, 5.6, 10.4
 */
export const CHAT_MESSAGES_POLL_MS      = 5_000
export const CHAT_CONVERSATIONS_POLL_MS = 30_000
export const NOTIFICATIONS_POLL_MS      = 30_000
export const UNREAD_MESSAGES_POLL_MS    = 30_000
