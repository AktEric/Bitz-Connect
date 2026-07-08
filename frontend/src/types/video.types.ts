import type { PlanTier } from './subscription.types'

/**
 * Tipos de vídeo (Sprint: monetization-hotelconnect-pro — vertical de Vídeos).
 */

export interface VideoCreator {
  uuid: string
  name: string
  avatar_url: string | null
  plan: PlanTier | null
  is_pro: boolean | null
}

export interface Video {
  uuid: string
  title: string
  description: string | null
  video_url: string | null
  thumbnail_url: string | null
  duration_seconds: number | null
  category: string | null
  tags: string[]
  views_count: number
  likes_count: number
  comments_count: number
  shares_count: number
  liked_by_me?: boolean
  created_at: string
  creator: VideoCreator
}

export interface VideoComment {
  uuid: string
  content: string
  created_at: string
  author: VideoCreator
}

export interface CreateVideoData {
  title: string
  description?: string
  category?: string
  tags?: string[]
  duration_seconds?: number
  video: File
  thumbnail?: File | null
}
