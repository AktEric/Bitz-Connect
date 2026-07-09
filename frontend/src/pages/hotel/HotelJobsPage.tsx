import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { Plus, MapPin, Users, Pencil, Pause, Play, XCircle, ChevronDown, ChevronUp, Briefcase } from 'lucide-react'
import { useHotel } from '@/hooks/useHotel'
import {
  useManageJobs, useCreateJob, useUpdateJob, useUpdateJobStatus,
  useJobApplications, useUpdateApplicationStatus,
} from '@/hooks/useJobs'
import JobForm from '@/components/hotel/JobForm'
import { ProfileAvatar } from '@/components/common/ProfileAvatar'
import { UserLink } from '@/components/common/UserLink'
import { EmptyState } from '@/components/ui/EmptyState'
import type { Job, ApplicationStatus, CreateJobData } from '@/types/job.types'

const CONTRACT_LABELS: Record<string, string> = {
  clt: 'CLT', pj: 'PJ', freelance: 'Freelance', internship: 'Estágio',
}
const MODALITY_LABELS: Record<string, string> = {
  on_site: 'Presencial', remote: 'Remoto', hybrid: 'Híbrido',
}
const STATUS_BADGE: Record<string, string> = {
  active: 'bg-emerald-50 text-emerald-700',
  paused: 'bg-amber-50 text-amber-700',
  closed: 'bg-gray-100 text-gray-500',
}
const STATUS_LABELS: Record<string, string> = {
  active: 'Ativa', paused: 'Pausada', closed: 'Encerrada',
}
const APPLICATION_STATUS_LABELS: Record<ApplicationStatus, string> = {
  pending: 'Pendente', viewed: 'Visualizada', rejected: 'Recusada', hired: 'Contratado',
}

/**
 * HotelJobsPage — gestão das vagas do hotel: criar, editar, pausar/reativar/
 * encerrar e ver/gerenciar candidaturas por vaga.
 */
export default function HotelJobsPage() {
  const { slug = '' } = useParams<{ slug: string }>()
  const { data: hotel } = useHotel(slug)
  const { data: jobsData, isLoading } = useManageJobs(slug)
  const createJob = useCreateJob()
  const [showCreate, setShowCreate] = useState(false)
  const [editingJob, setEditingJob] = useState<Job | null>(null)
  const [expandedJob, setExpandedJob] = useState<string | null>(null)

  const jobs = jobsData?.data ?? []

  async function handleCreate(data: Omit<CreateJobData, 'hotel_uuid'>) {
    if (!hotel) return
    await createJob.mutateAsync({ ...data, hotel_uuid: hotel.uuid })
    setShowCreate(false)
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Gerenciar Vagas</h1>
          <p className="mt-2 text-muted-foreground">Publique, edite e acompanhe as candidaturas das vagas do hotel.</p>
        </div>
        {!showCreate && (
          <button
            onClick={() => setShowCreate(true)}
            className="flex items-center gap-1.5 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90"
          >
            <Plus className="h-4 w-4" /> Nova vaga
          </button>
        )}
      </div>

      {showCreate && (
        <div className="rounded-xl border border-dashed border-gray-300 bg-white p-5">
          <h2 className="mb-4 text-base font-semibold text-gray-900">Publicar nova vaga</h2>
          <JobForm onSubmit={handleCreate} onCancel={() => setShowCreate(false)} loading={createJob.isPending} />
        </div>
      )}

      {isLoading && (
        <div className="space-y-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <div key={i} className="h-24 animate-pulse rounded-xl bg-gray-100" />
          ))}
        </div>
      )}

      {!isLoading && jobs.length === 0 && !showCreate && (
        <EmptyState
          title="Nenhuma vaga publicada"
          description="Publique a primeira vaga para começar a receber candidaturas."
          icon={<Briefcase className="h-8 w-8" />}
        />
      )}

      <div className="space-y-3">
        {jobs.map((job) =>
          editingJob?.uuid === job.uuid ? (
            <div key={job.uuid} className="rounded-xl border border-dashed border-gray-300 bg-white p-5">
              <h2 className="mb-4 text-base font-semibold text-gray-900">Editar vaga</h2>
              <JobEditForm job={job} onDone={() => setEditingJob(null)} />
            </div>
          ) : (
            <JobRow
              key={job.uuid}
              job={job}
              expanded={expandedJob === job.uuid}
              onToggleExpand={() => setExpandedJob(expandedJob === job.uuid ? null : job.uuid)}
              onEdit={() => setEditingJob(job)}
            />
          ),
        )}
      </div>
    </div>
  )
}

function JobEditForm({ job, onDone }: { job: Job; onDone: () => void }) {
  const updateJob = useUpdateJob(job.uuid)

  async function handleSubmit(data: Omit<CreateJobData, 'hotel_uuid'>) {
    await updateJob.mutateAsync(data)
    onDone()
  }

  return <JobForm initial={job} onSubmit={handleSubmit} onCancel={onDone} loading={updateJob.isPending} />
}

function JobRow({
  job, expanded, onToggleExpand, onEdit,
}: {
  job: Job
  expanded: boolean
  onToggleExpand: () => void
  onEdit: () => void
}) {
  const updateStatus = useUpdateJobStatus(job.uuid)

  return (
    <div className="rounded-xl border border-gray-100 bg-white">
      <div className="flex items-start gap-3 p-4">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="font-semibold text-gray-900">{job.title}</h3>
            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_BADGE[job.status]}`}>
              {STATUS_LABELS[job.status]}
            </span>
          </div>
          <div className="mt-1.5 flex flex-wrap gap-2 text-xs text-gray-500">
            {(job.city || job.state) && (
              <span className="flex items-center gap-1"><MapPin className="h-3 w-3" />{[job.city, job.state].filter(Boolean).join(', ')}</span>
            )}
            <span className="rounded-full bg-blue-50 px-2 py-0.5 text-blue-600">{CONTRACT_LABELS[job.contract_type] ?? job.contract_type}</span>
            <span className="rounded-full bg-green-50 px-2 py-0.5 text-green-600">{MODALITY_LABELS[job.modality] ?? job.modality}</span>
          </div>
        </div>

        <div className="flex flex-shrink-0 items-center gap-1">
          <button onClick={onEdit} className="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-primary" title="Editar">
            <Pencil className="h-4 w-4" />
          </button>
          {job.status === 'active' && (
            <button onClick={() => updateStatus.mutate('paused')} className="rounded-md p-1.5 text-gray-400 hover:bg-amber-50 hover:text-amber-600" title="Pausar">
              <Pause className="h-4 w-4" />
            </button>
          )}
          {job.status === 'paused' && (
            <button onClick={() => updateStatus.mutate('active')} className="rounded-md p-1.5 text-gray-400 hover:bg-emerald-50 hover:text-emerald-600" title="Reativar">
              <Play className="h-4 w-4" />
            </button>
          )}
          {job.status !== 'closed' && (
            <button onClick={() => updateStatus.mutate('closed')} className="rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500" title="Encerrar">
              <XCircle className="h-4 w-4" />
            </button>
          )}
        </div>
      </div>

      <button
        onClick={onToggleExpand}
        className="flex w-full items-center justify-between border-t border-gray-100 px-4 py-2.5 text-xs font-medium text-gray-500 hover:bg-gray-50"
      >
        <span className="flex items-center gap-1.5">
          <Users className="h-3.5 w-3.5" /> {job.applications_count ?? 0} candidatura{job.applications_count === 1 ? '' : 's'}
        </span>
        {expanded ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
      </button>

      {expanded && <ApplicationsPanel jobUuid={job.uuid} />}
    </div>
  )
}

function ApplicationsPanel({ jobUuid }: { jobUuid: string }) {
  const { data, isLoading } = useJobApplications(jobUuid)
  const updateStatus = useUpdateApplicationStatus(jobUuid)
  const applications = data?.data ?? []

  if (isLoading) {
    return <div className="border-t border-gray-100 p-4 text-sm text-gray-400">Carregando candidaturas...</div>
  }

  if (applications.length === 0) {
    return <div className="border-t border-gray-100 p-4 text-sm text-gray-400">Nenhuma candidatura ainda.</div>
  }

  return (
    <div className="space-y-2 border-t border-gray-100 bg-gray-50/50 p-4">
      {applications.map((app) => (
        <div key={app.uuid} className="flex items-center gap-3 rounded-lg bg-white p-3">
          <ProfileAvatar uuid={app.user.uuid} name={app.user.name} src={app.user.avatar_url} size="sm" />
          <div className="min-w-0 flex-1">
            <UserLink uuid={app.user.uuid} name={app.user.name} className="text-sm font-medium text-gray-900" />
            {app.cover_letter && <p className="mt-0.5 line-clamp-1 text-xs text-gray-500">{app.cover_letter}</p>}
          </div>
          <select
            value={app.status}
            onChange={(e) => updateStatus.mutate({ applicationUuid: app.uuid, status: e.target.value as ApplicationStatus })}
            disabled={updateStatus.isPending}
            className="flex-shrink-0 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-primary"
          >
            {(Object.keys(APPLICATION_STATUS_LABELS) as ApplicationStatus[]).map((s) => (
              <option key={s} value={s}>{APPLICATION_STATUS_LABELS[s]}</option>
            ))}
          </select>
        </div>
      ))}
    </div>
  )
}
