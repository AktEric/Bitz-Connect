import type { Job } from './job.types'
import type { Mentorship } from './mentorship.types'

/**
 * Tipos de story (destaque de vaga/mentoria — contas PRO). MVP sem expiração.
 */
export type StoryType = 'job' | 'mentorship'

export interface StoryOwner {
  type: 'hotel' | 'user'
  uuid: string
  slug?: string
  name: string
  avatar_url: string | null
  is_pro: boolean
}

export interface Story {
  uuid: string
  story_type: StoryType
  caption: string | null
  image_url: string | null
  views_count: number
  likes_count: number
  shares_count: number
  liked_by_me?: boolean
  created_at: string
  job: Job | null
  mentorship: Mentorship | null
}

export interface StoryGroup {
  owner: StoryOwner
  stories: Story[]
}

export interface CreateStoryData {
  story_type: StoryType
  job_uuid?: string
  mentorship_uuid?: string
  caption?: string
  image?: File
}
