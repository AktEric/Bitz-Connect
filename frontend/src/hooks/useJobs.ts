import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import jobService, { type JobFiltersParams } from '@/services/job.service'
import type { CreateJobData, ApplyJobData } from '@/types/job.types'
import { useToast } from '@/contexts/ToastContext'
import { extractApiError } from '@/utils/api-error'

/**
 * Hook for job data fetching and mutations.
 * Requirements: 5.1, 5.2, 5.5, 5.8, 5.9
 */

export const jobKeys = {
  all: ['jobs'] as const,
  list: (params: JobFiltersParams) => ['jobs', 'list', params] as const,
  detail: (uuid: string) => ['jobs', 'detail', uuid] as const,
  applications: (uuid: string) => ['jobs', 'applications', uuid] as const,
  myApplications: ['jobs', 'my-applications'] as const,
}

/** Paginated list of active jobs with optional filters */
export function useJobs(params: JobFiltersParams = {}) {
  return useQuery({
    queryKey: jobKeys.list(params),
    queryFn: () => jobService.getJobs(params),
  })
}

/** Single job detail by UUID */
export function useJob(uuid: string) {
  return useQuery({
    queryKey: jobKeys.detail(uuid),
    queryFn: () => jobService.getJob(uuid).then((r) => r.data),
    enabled: !!uuid,
  })
}

/** Authenticated user's own applications */
export function useMyApplications(page = 1) {
  return useQuery({
    queryKey: [...jobKeys.myApplications, page],
    queryFn: () => jobService.getMyApplications(page),
  })
}

/** Applications for a specific job (HotelAdmin only) */
export function useJobApplications(jobUuid: string, page = 1) {
  return useQuery({
    queryKey: [...jobKeys.applications(jobUuid), page],
    queryFn: () => jobService.getJobApplications(jobUuid, page),
    enabled: !!jobUuid,
  })
}

/** Apply to a job */
export function useApplyToJob(jobUuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: (data: ApplyJobData) => jobService.applyToJob(jobUuid, data),
    onSuccess: () => {
      success('Candidatura enviada com sucesso.')
      qc.invalidateQueries({ queryKey: jobKeys.detail(jobUuid) })
      qc.invalidateQueries({ queryKey: jobKeys.myApplications })
    },
    onError: (err) => {
      error(extractApiError(err))
    },
  })
}

/** Create a new job (HotelAdmin) */
export function useCreateJob() {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: (data: CreateJobData) => jobService.createJob(data),
    onSuccess: () => {
      success('Vaga criada com sucesso.')
      qc.invalidateQueries({ queryKey: jobKeys.all })
    },
    onError: (err) => {
      error(extractApiError(err))
    },
  })
}

/** Update job status (HotelAdmin) */
export function useUpdateJobStatus(jobUuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()

  return useMutation({
    mutationFn: (status: 'active' | 'paused' | 'closed') =>
      jobService.updateJobStatus(jobUuid, status),
    onSuccess: (res) => {
      success('Status da vaga atualizado.')
      qc.setQueryData(jobKeys.detail(jobUuid), res.data)
      qc.invalidateQueries({ queryKey: jobKeys.all })
    },
    onError: (err) => {
      error(extractApiError(err))
    },
  })
}
