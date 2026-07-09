import type { Hotel } from './hotel.types'
import type { User } from './auth.types'

export type ContractType = 'clt' | 'pj' | 'freelance' | 'internship'
export type Modality = 'on_site' | 'remote' | 'hybrid'
export type JobStatus = 'active' | 'paused' | 'closed'
export type ApplicationStatus = 'pending' | 'viewed' | 'rejected' | 'hired'

export interface Job {
  uuid: string
  title: string
  description: string
  requirements: string | null
  benefits: string | null
  contract_type: ContractType
  modality: Modality
  city: string
  state: string
  salary_range: string | null
  status: JobStatus
  applications_count?: number
  hotel: Hotel
  created_at: string
}

export interface JobApplication {
  uuid: string
  job: Job
  user: User
  cover_letter: string | null
  status: ApplicationStatus
  created_at: string
}

export interface CreateJobData {
  hotel_uuid: string
  title: string
  description: string
  requirements?: string
  benefits?: string
  contract_type: ContractType
  modality: Modality
  city: string
  state: string
  salary_range?: string
}

export interface ApplyJobData {
  cover_letter?: string
}
