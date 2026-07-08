import type { PlanTier } from './subscription.types'

export interface Hotel {
  uuid: string
  name: string
  slug: string
  cnpj: string | null
  segment: string | null
  description: string | null
  logo_url: string | null
  cover_url: string | null
  city: string | null
  state: string | null
  country: string | null
  website: string | null
  phone: string | null
  contact_email: string | null
  followers_count: number
  employees_count: number
  jobs_count: number
  is_following: boolean
  has_admin: boolean
  is_admin: boolean
  plan?: PlanTier
  is_pro?: boolean
  created_at: string
}

export interface Employee {
  uuid: string
  user_id: number
  name: string
  avatar_url: string | null
  title: string | null
  started_at: string | null
  verified: boolean
  hotel_user: {
    role: 'admin' | 'employee'
    status: 'pending' | 'approved' | 'rejected'
    created_at: string
  }
}

export interface HotelClaim {
  id: number
  hotel_id: number
  user_id: number
  status: 'pending' | 'approved' | 'rejected'
  message: string | null
  reviewed_at: string | null
}

export interface CreateHotelData {
  name: string
  description: string
  cnpj?: string
  city?: string
  state?: string
  country?: string
  website?: string
  phone?: string
  contact_email?: string
}

export interface UpdateHotelData extends Partial<CreateHotelData> {}
