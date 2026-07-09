import api from './api'
import type { ApiResponse, PaginatedResponse } from '@/types/api.types'
import type { Job, JobApplication, CreateJobData, ApplyJobData } from '@/types/job.types'

export interface JobFiltersParams {
  page?: number
  contract_type?: string
  modality?: string
  city?: string
  state?: string
}

/**
 * Job service — wraps all /jobs and /me/applications API calls.
 * Requirements: 5.1, 5.2, 5.5, 5.8, 5.9
 */
const jobService = {
  /** GET /jobs — list active jobs (paginated) */
  async getJobs(params: JobFiltersParams = {}): Promise<PaginatedResponse<Job>> {
    const res = await api.get<PaginatedResponse<Job>>('/jobs', { params })
    return res.data
  },

  /** GET /jobs/:uuid — job detail */
  async getJob(uuid: string): Promise<ApiResponse<Job>> {
    const res = await api.get<ApiResponse<Job>>(`/jobs/${uuid}`)
    return res.data
  },

  /** POST /jobs — publish a new job (HotelAdmin) */
  async createJob(data: CreateJobData): Promise<ApiResponse<Job>> {
    const res = await api.post<ApiResponse<Job>>('/jobs', data)
    return res.data
  },

  /** PUT /jobs/:uuid — edit a job (HotelAdmin) */
  async updateJob(uuid: string, data: Partial<CreateJobData>): Promise<ApiResponse<Job>> {
    const res = await api.put<ApiResponse<Job>>(`/jobs/${uuid}`, data)
    return res.data
  },

  /** PATCH /jobs/:uuid/status — change job status (HotelAdmin) */
  async updateJobStatus(
    uuid: string,
    status: 'active' | 'paused' | 'closed',
  ): Promise<ApiResponse<Job>> {
    const res = await api.patch<ApiResponse<Job>>(`/jobs/${uuid}/status`, { status })
    return res.data
  },

  /** POST /jobs/:uuid/apply — apply to a job */
  async applyToJob(uuid: string, data: ApplyJobData = {}): Promise<ApiResponse<JobApplication>> {
    const res = await api.post<ApiResponse<JobApplication>>(`/jobs/${uuid}/apply`, data)
    return res.data
  },

  /** GET /jobs/:uuid/applications — list applicants for a job (HotelAdmin) */
  async getJobApplications(
    uuid: string,
    page = 1,
  ): Promise<PaginatedResponse<JobApplication>> {
    const res = await api.get<PaginatedResponse<JobApplication>>(
      `/jobs/${uuid}/applications`,
      { params: { page } },
    )
    return res.data
  },

  /** PATCH /jobs/:uuid/applications/:applicationUuid/status — change an application's status (HotelAdmin) */
  async updateApplicationStatus(
    jobUuid: string,
    applicationUuid: string,
    status: 'pending' | 'viewed' | 'rejected' | 'hired',
  ): Promise<ApiResponse<JobApplication>> {
    const res = await api.patch<ApiResponse<JobApplication>>(
      `/jobs/${jobUuid}/applications/${applicationUuid}/status`,
      { status },
    )
    return res.data
  },

  /** GET /hotels/:slug/jobs/manage — all jobs (any status) for the hotel admin panel */
  async getManageJobs(slug: string, page = 1): Promise<PaginatedResponse<Job>> {
    const res = await api.get<PaginatedResponse<Job>>(`/hotels/${slug}/jobs/manage`, { params: { page } })
    return res.data
  },

  /** GET /me/applications — authenticated user's own applications */
  async getMyApplications(page = 1): Promise<PaginatedResponse<JobApplication>> {
    const res = await api.get<PaginatedResponse<JobApplication>>('/me/applications', {
      params: { page },
    })
    return res.data
  },
}

export default jobService
