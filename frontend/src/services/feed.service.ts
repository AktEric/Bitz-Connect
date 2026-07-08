import api from './api'
import type { ApiResponse } from '@/types/api.types'
import type { Post, Comment, FeedCursorPage } from '@/types/feed.types'

// Shape do response raw do /feed (data + meta no mesmo nível)
interface FeedApiResponse {
  success: boolean
  message: string
  data: Post[]
  meta: {
    per_page: number
    next_cursor: string | null
    prev_cursor: string | null
    next_page_url: string | null
    prev_page_url: string | null
  }
}

/**
 * Feed service — wraps all /feed, /posts and /comments API calls.
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8
 */
const feedService = {
  /** GET /feed?cursor=... — cursor-based paginated feed */
  async getFeed(cursor?: string): Promise<FeedCursorPage> {
    const res = await api.get<FeedApiResponse>('/feed', {
      params: cursor ? { cursor } : undefined,
    })
    // O backend retorna data (array) + meta (cursors) no mesmo nível
    return {
      data: res.data.data,
      next_cursor: res.data.meta?.next_cursor ?? null,
      next_page_url: res.data.meta?.next_page_url ?? null,
    }
  },

  /** POST /posts — create a new post (with optional image and type/metadata) */
  async createPost(
    content: string,
    hotel_id?: number,
    image?: File,
    type?: string,
    metadata?: Record<string, unknown>,
  ): Promise<Post> {
    if (image) {
      const form = new FormData()
      form.append('content', content)
      if (hotel_id) form.append('hotel_id', String(hotel_id))
      form.append('image', image)
      if (type) form.append('type', type)
      if (metadata) form.append('metadata', JSON.stringify(metadata))
      const res = await api.post<ApiResponse<Post>>('/posts', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return res.data.data
    }
    const res = await api.post<ApiResponse<Post>>('/posts', {
      content,
      hotel_id,
      ...(type ? { type } : {}),
      ...(metadata ? { metadata } : {}),
    })
    return res.data.data
  },

  /** POST /posts/:uuid/image — upload or replace post image */
  async uploadPostImage(uuid: string, image: File): Promise<Post> {
    const form = new FormData()
    form.append('image', image)
    const res = await api.post<ApiResponse<Post>>(`/posts/${uuid}/image`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return res.data.data
  },

  /** PUT /posts/:uuid — edit a post (author only) */
  async updatePost(uuid: string, content: string): Promise<Post> {
    const res = await api.put<ApiResponse<Post>>(`/posts/${uuid}`, { content })
    return res.data.data
  },

  /** DELETE /posts/:uuid — delete a post (author only) */
  async deletePost(uuid: string): Promise<void> {
    await api.delete(`/posts/${uuid}`)
  },

  /** POST /posts/:uuid/like — like a post */
  async likePost(uuid: string): Promise<{ likes_count: number }> {
    const res = await api.post<ApiResponse<{ likes_count: number }>>(`/posts/${uuid}/like`)
    return res.data.data
  },

  /** DELETE /posts/:uuid/like — unlike a post */
  async unlikePost(uuid: string): Promise<{ likes_count: number }> {
    const res = await api.delete<ApiResponse<{ likes_count: number }>>(`/posts/${uuid}/like`)
    return res.data.data
  },

  /** GET /posts/:uuid/comments — fetch comments for a post */
  async getComments(postUuid: string): Promise<Comment[]> {
    const res = await api.get<ApiResponse<Comment[]>>(`/posts/${postUuid}/comments`)
    return res.data.data
  },

  /** POST /posts/:uuid/comments — add a comment (optionally a reply) */
  async addComment(postUuid: string, content: string, parentUuid?: string): Promise<Comment> {
    const res = await api.post<ApiResponse<Comment>>(`/posts/${postUuid}/comments`, {
      content,
      parent_uuid: parentUuid,
    })
    return res.data.data
  },

  /** DELETE /posts/:uuid/comments/:id — delete a comment (author only) */
  async deleteComment(postUuid: string, commentUuid: string): Promise<void> {
    await api.delete(`/posts/${postUuid}/comments/${commentUuid}`)
  },
}

export default feedService
