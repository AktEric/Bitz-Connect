import type { User } from './auth.types'
import type { Hotel } from './hotel.types'

export type { User }

export interface Experience {
  id: number
  uuid: string
  title: string
  company: string
  hotel_id: number | null
  hotel?: Hotel | null
  verified: boolean
  started_at: string
  ended_at: string | null
  description: string | null
  hotel_link_status?: 'pending' | 'approved' | 'rejected' | null
}

export interface UpdateProfileData {
  name?: string
  bio?: string
  city?: string
  state?: string
  website?: string
  social_links?: Record<string, string>
}

export interface CreateExperienceData {
  title: string
  company: string
  hotel_uuid?: string | null
  started_at: string
  ended_at?: string
  description?: string
}
