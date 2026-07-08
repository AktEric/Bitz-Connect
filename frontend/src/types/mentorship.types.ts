import type { PlanTier } from './subscription.types'

/**
 * Tipos de mentoria (§11).
 */
export type MentorshipStatus = 'draft' | 'published'
export type SessionType = 'online' | 'presential'
export type BookingStatus = 'requested' | 'accepted' | 'declined' | 'completed'

export interface MentorshipMentor {
  uuid: string
  name: string
  avatar_url: string | null
  plan: PlanTier | null
  is_pro: boolean | null
}

export interface Mentorship {
  uuid: string
  title: string
  description: string | null
  category: string | null
  duration_minutes: number
  session_type: SessionType
  price_cents: number
  status: MentorshipStatus
  bookings_count: number
  rating_avg: number
  reviews_count: number
  created_at: string
  my_booking_status?: BookingStatus | null
  is_owner?: boolean
  mentor?: MentorshipMentor
}

export interface MentorshipBooking {
  uuid: string
  message: string | null
  status: BookingStatus
  scheduled_at: string | null
  created_at: string
  user?: MentorshipMentor
  mentorship?: Mentorship
}

export interface CreateMentorshipData {
  title: string
  description?: string
  category?: string
  duration_minutes?: number
  session_type?: SessionType
  price_cents?: number
}
