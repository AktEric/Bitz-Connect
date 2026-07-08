import api from './api'
import type { ApiResponse, PaginatedResponse } from '@/types/api.types'
import type { Course, CourseLesson, CreateCourseData, CreateLessonData } from '@/types/course.types'

/**
 * Serviço de cursos (§11).
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
const courseService = {
  /** GET /courses — catálogo publicado (com busca ?q e filtro ?category) */
  async getCourses(params: { page?: number; category?: string; q?: string } = {}): Promise<PaginatedResponse<Course>> {
    const res = await api.get<PaginatedResponse<Course>>('/courses', { params })
    return res.data
  },

  /** GET /courses/:uuid — detalhe + aulas */
  async getCourse(uuid: string): Promise<ApiResponse<Course>> {
    const res = await api.get<ApiResponse<Course>>(`/courses/${uuid}`)
    return res.data
  },

  /** GET /users/:uuid/courses — cursos de um criador */
  async getCreatorCourses(userUuid: string, page = 1): Promise<PaginatedResponse<Course>> {
    const res = await api.get<PaginatedResponse<Course>>(`/users/${userUuid}/courses`, { params: { page } })
    return res.data
  },

  /** GET /me/courses — cursos matriculados */
  async getMyCourses(page = 1): Promise<PaginatedResponse<Course>> {
    const res = await api.get<PaginatedResponse<Course>>('/me/courses', { params: { page } })
    return res.data
  },

  /** POST /courses — cria (Consultor PRO) */
  async createCourse(data: CreateCourseData): Promise<ApiResponse<Course>> {
    const res = await api.post<ApiResponse<Course>>('/courses', data)
    return res.data
  },

  /** PUT /courses/:uuid — edita (dono) */
  async updateCourse(uuid: string, data: Partial<CreateCourseData>): Promise<ApiResponse<Course>> {
    const res = await api.put<ApiResponse<Course>>(`/courses/${uuid}`, data)
    return res.data
  },

  /** PATCH /courses/:uuid/publish */
  async publishCourse(uuid: string): Promise<ApiResponse<Course>> {
    const res = await api.patch<ApiResponse<Course>>(`/courses/${uuid}/publish`)
    return res.data
  },

  /** POST /courses/:uuid/enroll */
  async enroll(uuid: string): Promise<ApiResponse<{ is_enrolled: boolean; enrollments_count: number }>> {
    const res = await api.post<ApiResponse<{ is_enrolled: boolean; enrollments_count: number }>>(`/courses/${uuid}/enroll`)
    return res.data
  },

  /** POST /courses/:uuid/lessons */
  async addLesson(courseUuid: string, data: CreateLessonData): Promise<ApiResponse<CourseLesson>> {
    const res = await api.post<ApiResponse<CourseLesson>>(`/courses/${courseUuid}/lessons`, data)
    return res.data
  },

  /** PUT /courses/:uuid/lessons/:lessonUuid */
  async updateLesson(courseUuid: string, lessonUuid: string, data: Partial<CreateLessonData>): Promise<ApiResponse<CourseLesson>> {
    const res = await api.put<ApiResponse<CourseLesson>>(`/courses/${courseUuid}/lessons/${lessonUuid}`, data)
    return res.data
  },

  /** DELETE /courses/:uuid/lessons/:lessonUuid */
  async deleteLesson(courseUuid: string, lessonUuid: string): Promise<ApiResponse<null>> {
    const res = await api.delete<ApiResponse<null>>(`/courses/${courseUuid}/lessons/${lessonUuid}`)
    return res.data
  },
}

export default courseService
