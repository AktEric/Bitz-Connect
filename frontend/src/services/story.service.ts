import api from './api'
import type { ApiResponse } from '@/types/api.types'
import type { Story, StoryGroup, CreateStoryData } from '@/types/story.types'

export interface ToggleStoryLikeResult { liked: boolean; likes_count: number }

/**
 * Serviço de stories — encapsula as chamadas de /stories.
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
const storyService = {
  /** GET /stories — feed ativo, agrupado por dono (hotel ou consultor) */
  async getStories(): Promise<ApiResponse<StoryGroup[]>> {
    const res = await api.get<ApiResponse<StoryGroup[]>>('/stories')
    return res.data
  },

  /** POST /stories — publica um story a partir de uma vaga/mentoria existente (multipart) */
  async createStory(data: CreateStoryData): Promise<ApiResponse<Story>> {
    const form = new FormData()
    form.append('story_type', data.story_type)
    if (data.job_uuid) form.append('job_uuid', data.job_uuid)
    if (data.mentorship_uuid) form.append('mentorship_uuid', data.mentorship_uuid)
    if (data.caption) form.append('caption', data.caption)
    if (data.image) form.append('image', data.image)

    const res = await api.post<ApiResponse<Story>>('/stories', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return res.data
  },

  /** DELETE /stories/:uuid — remove (dono apenas) */
  async deleteStory(uuid: string): Promise<ApiResponse<null>> {
    const res = await api.delete<ApiResponse<null>>(`/stories/${uuid}`)
    return res.data
  },

  /** POST /stories/:uuid/view — registra visualização */
  async registerView(uuid: string): Promise<void> {
    await api.post(`/stories/${uuid}/view`)
  },

  /** POST /stories/:uuid/like — alterna curtida */
  async toggleLike(uuid: string): Promise<ApiResponse<ToggleStoryLikeResult>> {
    const res = await api.post<ApiResponse<ToggleStoryLikeResult>>(`/stories/${uuid}/like`)
    return res.data
  },

  /** POST /stories/:uuid/share — registra compartilhamento */
  async share(uuid: string): Promise<ApiResponse<{ shares_count: number }>> {
    const res = await api.post<ApiResponse<{ shares_count: number }>>(`/stories/${uuid}/share`)
    return res.data
  },
}

export default storyService
