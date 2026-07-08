import api from './api'
import type { ApiResponse, PaginatedResponse } from '@/types/api.types'
import type { Video, CreateVideoData, VideoComment } from '@/types/video.types'

export interface ToggleLikeResult { liked: boolean; likes_count: number }

/**
 * Serviço de vídeos — encapsula as chamadas de /videos.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
const videoService = {
  /** GET /videos — feed paginado, filtro opcional por categoria */
  async getVideos(params: { page?: number; category?: string } = {}): Promise<PaginatedResponse<Video>> {
    const res = await api.get<PaginatedResponse<Video>>('/videos', { params })
    return res.data
  },

  /** GET /videos/:uuid — detalhe */
  async getVideo(uuid: string): Promise<ApiResponse<Video>> {
    const res = await api.get<ApiResponse<Video>>(`/videos/${uuid}`)
    return res.data
  },

  /** POST /videos — publica um vídeo (multipart; exclusivo Consultor PRO) */
  async createVideo(data: CreateVideoData): Promise<ApiResponse<Video>> {
    const form = new FormData()
    form.append('title', data.title)
    if (data.description) form.append('description', data.description)
    if (data.category) form.append('category', data.category)
    if (data.duration_seconds != null) form.append('duration_seconds', String(data.duration_seconds))
    for (const tag of data.tags ?? []) form.append('tags[]', tag)
    form.append('video', data.video)
    if (data.thumbnail) form.append('thumbnail', data.thumbnail)

    const res = await api.post<ApiResponse<Video>>('/videos', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return res.data
  },

  /** POST /videos/:uuid/view — registra visualização */
  async registerView(uuid: string): Promise<void> {
    await api.post(`/videos/${uuid}/view`)
  },

  /** GET /users/:uuid/videos — vídeos de um criador */
  async getCreatorVideos(userUuid: string, page = 1): Promise<PaginatedResponse<Video>> {
    const res = await api.get<PaginatedResponse<Video>>(`/users/${userUuid}/videos`, { params: { page } })
    return res.data
  },

  /** POST /videos/:uuid/like — alterna curtida */
  async toggleLike(uuid: string): Promise<ApiResponse<ToggleLikeResult>> {
    const res = await api.post<ApiResponse<ToggleLikeResult>>(`/videos/${uuid}/like`)
    return res.data
  },

  /** GET /videos/:uuid/comments — comentários paginados */
  async getComments(uuid: string, page = 1): Promise<PaginatedResponse<VideoComment>> {
    const res = await api.get<PaginatedResponse<VideoComment>>(`/videos/${uuid}/comments`, { params: { page } })
    return res.data
  },

  /** POST /videos/:uuid/comments — adiciona comentário */
  async addComment(uuid: string, content: string): Promise<ApiResponse<VideoComment>> {
    const res = await api.post<ApiResponse<VideoComment>>(`/videos/${uuid}/comments`, { content })
    return res.data
  },

  /** DELETE /videos/:uuid/comments/:commentUuid — remove comentário */
  async deleteComment(uuid: string, commentUuid: string): Promise<ApiResponse<null>> {
    const res = await api.delete<ApiResponse<null>>(`/videos/${uuid}/comments/${commentUuid}`)
    return res.data
  },

  /** POST /videos/:uuid/share — registra compartilhamento */
  async share(uuid: string): Promise<ApiResponse<{ shares_count: number }>> {
    const res = await api.post<ApiResponse<{ shares_count: number }>>(`/videos/${uuid}/share`)
    return res.data
  },
}

export default videoService
