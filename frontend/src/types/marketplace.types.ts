import type { WorkspacePerson } from './workspace.types'

export type ListingStatus = 'active' | 'sold' | 'hidden'

/** Cartão do anúncio (grade, mural e chat). */
export interface Listing {
  uuid: string
  title: string
  price_cents: number | null
  category: string
  condition: string
  location: string | null
  status: ListingStatus
  cover_url: string | null
  images_count: number
  likes_count: number
  reposts_count: number
  liked: boolean
  is_mine: boolean
  seller: WorkspacePerson | null
  created_at: string
}

export interface ListingDetail extends Listing {
  description: string
  views_count: number | null
  sold_at: string | null
  images: { uuid: string; url: string }[]
  can_edit: boolean
  can_moderate: boolean
}

export interface MarketplaceFilters {
  q?: string
  category?: string
  condition?: string
  min?: string
  max?: string
  sort?: 'recent' | 'price_asc' | 'price_desc' | 'popular'
  scope?: 'all' | 'mine' | 'liked'
  status?: 'active' | 'sold'
}

export interface MarketplacePage {
  items: Listing[]
  page: number
  last_page: number
  total: number
  categories: Record<string, string>
  conditions: Record<string, string>
  max_images: number
}

export interface ListingInput {
  title: string
  description: string
  price: string
  category: string
  condition: string
  location: string
}
