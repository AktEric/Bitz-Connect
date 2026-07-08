import type { PlanTier } from './subscription.types'

/**
 * Tipos de curso (§11).
 */
export type CourseStatus = 'draft' | 'published'
export type CourseLevel = 'beginner' | 'intermediate' | 'advanced'

export interface CourseCreator {
  uuid: string
  name: string
  avatar_url: string | null
  plan: PlanTier | null
  is_pro: boolean | null
}

export interface CourseLesson {
  uuid: string
  title: string
  description: string | null
  position: number
  duration_seconds: number | null
  locked: boolean
  content: string | null
  video_url: string | null
}

export interface Course {
  uuid: string
  title: string
  description: string | null
  thumbnail_url: string | null
  category: string | null
  level: CourseLevel | null
  price_cents: number
  status: CourseStatus
  lessons_count: number
  enrollments_count: number
  rating_avg: number
  reviews_count: number
  created_at: string
  is_enrolled?: boolean
  is_owner?: boolean
  lessons?: CourseLesson[]
  creator?: CourseCreator
}

export interface CreateCourseData {
  title: string
  description?: string
  category?: string
  level?: CourseLevel
  price_cents?: number
}

export interface CreateLessonData {
  title: string
  description?: string
  content?: string
  video_url?: string
  position?: number
  duration_seconds?: number
}
