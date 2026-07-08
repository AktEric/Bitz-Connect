export interface PostAuthor {
  uuid: string
  name: string
  avatar_url: string | null
  plan?: import('./subscription.types').PlanTier | null
  is_pro?: boolean | null
}

export type PostType = 'post' | 'job_share'

export interface Post {
  uuid: string
  content: string
  image_url: string | null
  edited: boolean
  likes_count: number
  comments_count: number
  liked_by_me: boolean
  created_at: string
  updated_at: string
  user: PostAuthor
  hotel: { uuid: string; name: string; slug: string; logo_url: string | null } | null
  type?: PostType
  metadata?: Record<string, unknown> | null
}

export interface Comment {
  uuid: string
  content: string
  parent_id: number | null
  created_at: string
  user: PostAuthor
  replies?: Comment[]
}

export interface FeedCursorPage {
  data: Post[]
  next_cursor: string | null
  next_page_url: string | null
}
