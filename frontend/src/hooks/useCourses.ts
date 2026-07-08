import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import courseService from '@/services/course.service'
import { useToast } from '@/contexts/ToastContext'
import { extractApiError } from '@/utils/api-error'
import { reviewKeys } from '@/hooks/useReviews'
import type { CreateCourseData, CreateLessonData } from '@/types/course.types'

/**
 * Hooks de cursos (§11).
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
export const courseKeys = {
  all: ['courses'] as const,
  list: (params: { page?: number; category?: string; q?: string }) => ['courses', 'list', params] as const,
  detail: (uuid: string) => ['courses', 'detail', uuid] as const,
  creator: (userUuid: string) => ['courses', 'creator', userUuid] as const,
  mine: ['courses', 'mine'] as const,
}

export function useCourses(params: { page?: number; category?: string; q?: string } = {}) {
  return useQuery({ queryKey: courseKeys.list(params), queryFn: () => courseService.getCourses(params) })
}

export function useCourse(uuid: string) {
  return useQuery({
    queryKey: courseKeys.detail(uuid),
    queryFn: () => courseService.getCourse(uuid).then((r) => r.data),
    enabled: !!uuid,
  })
}

export function useCreatorCourses(userUuid: string, enabled = true) {
  return useQuery({
    queryKey: courseKeys.creator(userUuid),
    queryFn: () => courseService.getCreatorCourses(userUuid),
    enabled: enabled && !!userUuid,
  })
}

export function useMyCourses() {
  return useQuery({ queryKey: courseKeys.mine, queryFn: () => courseService.getMyCourses() })
}

export function useCreateCourse() {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (data: CreateCourseData) => courseService.createCourse(data).then((r) => r.data),
    onSuccess: () => { success('Curso criado.'); qc.invalidateQueries({ queryKey: courseKeys.all }) },
    onError: (err) => error(extractApiError(err)),
  })
}

export function useUpdateCourse(uuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (data: Partial<CreateCourseData>) => courseService.updateCourse(uuid, data).then((r) => r.data),
    onSuccess: () => { success('Curso atualizado.'); qc.invalidateQueries({ queryKey: courseKeys.detail(uuid) }) },
    onError: (err) => error(extractApiError(err)),
  })
}

export function usePublishCourse(uuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: () => courseService.publishCourse(uuid).then((r) => r.data),
    onSuccess: () => { success('Curso publicado.'); qc.invalidateQueries({ queryKey: courseKeys.detail(uuid) }) },
    onError: (err) => error(extractApiError(err)),
  })
}

export function useEnrollCourse(uuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: () => courseService.enroll(uuid).then((r) => r.data),
    onSuccess: () => {
      success('Matrícula realizada.')
      qc.invalidateQueries({ queryKey: courseKeys.detail(uuid) })
      // Matricular muda a elegibilidade para avaliar o curso (can_review).
      qc.invalidateQueries({ queryKey: reviewKeys.list('course', uuid) })
    },
    onError: (err) => error(extractApiError(err)),
  })
}

export function useAddLesson(courseUuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (data: CreateLessonData) => courseService.addLesson(courseUuid, data).then((r) => r.data),
    onSuccess: () => { success('Aula adicionada.'); qc.invalidateQueries({ queryKey: courseKeys.detail(courseUuid) }) },
    onError: (err) => error(extractApiError(err)),
  })
}

export function useDeleteLesson(courseUuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: (lessonUuid: string) => courseService.deleteLesson(courseUuid, lessonUuid),
    onSuccess: () => { success('Aula removida.'); qc.invalidateQueries({ queryKey: courseKeys.detail(courseUuid) }) },
    onError: (err) => error(extractApiError(err)),
  })
}
