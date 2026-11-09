import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import {
  MapPin,
  Briefcase,
  Monitor,
  ArrowLeft,
  Building2,
  DollarSign,
  ChevronDown,
  ChevronUp,
  Share2,
} from 'lucide-react'
import { useJob, useApplyToJob, useMyApplications } from '@/hooks/useJobs'
import { useAuth } from '@/hooks/useAuth'
import ApplicationStatus from '@/components/job/ApplicationStatus'
import ShareJobModal from '@/components/job/ShareJobModal'
import { formatDate } from '@/utils/format'

const CONTRACT_LABELS: Record<string, string> = {
  clt: 'CLT',
  pj: 'PJ',
  freelance: 'Freelance',
  internship: 'Estágio',
}

const MODALITY_LABELS: Record<string, string> = {
  on_site: 'Presencial',
  remote: 'Remoto',
  hybrid: 'Híbrido',
}

/**
 * Job detail page with full description and application flow.
 * Requirements: 5.2, 5.5
 */
export default function JobDetailPage() {
  const { uuid } = useParams<{ uuid: string }>()
  const { user } = useAuth()

  const { data: job, isLoading, isError } = useJob(uuid ?? '')
  const { data: myApplications } = useMyApplications()
  const applyMutation = useApplyToJob(uuid ?? '')

  const [coverLetter, setCoverLetter] = useState('')
  const [showCoverLetter, setShowCoverLetter] = useState(false)
  const [applied, setApplied] = useState(false)
  const [showShare, setShowShare] = useState(false)

  // Check if user already applied to this job
  const existingApplication = myApplications?.data.find(
    (a) => a.job.uuid === uuid,
  )
  const hasApplied = applied || !!existingApplication

  function handleApply(e: React.FormEvent) {
    e.preventDefault()
    if (applyMutation.isPending) return

    applyMutation.mutate(
      { cover_letter: coverLetter.trim() || undefined },
      {
        onSuccess: () => {
          setApplied(true)
          setShowCoverLetter(false)
          setCoverLetter('')
        },
      },
    )
  }

  if (isLoading) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-8">
        <div className="animate-pulse space-y-4">
          <div className="h-5 w-32 rounded bg-gray-200" />
          <div className="rounded-xl border border-gray-100 bg-white p-6 space-y-4">
            <div className="h-6 w-64 rounded bg-gray-200" />
            <div className="h-4 w-40 rounded bg-gray-100" />
            <div className="flex gap-2">
              <div className="h-6 w-20 rounded-full bg-gray-100" />
              <div className="h-6 w-20 rounded-full bg-gray-100" />
            </div>
            <div className="space-y-2 pt-2">
              {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="h-3 w-full rounded bg-gray-100" />
              ))}
            </div>
          </div>
        </div>
      </div>
    )
  }

  if (isError || !job) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-8">
        <div className="flex h-64 items-center justify-center text-gray-500">
          Vaga não encontrada.
        </div>
      </div>
    )
  }

  const isActive = job.status === 'active'

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      {/* Back */}
      <Link
        to="/jobs"
        className="mb-6 flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800"
      >
        <ArrowLeft className="h-4 w-4" />
        Voltar às vagas
      </Link>

      {/* Job card */}
      <article className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
        {/* Hotel header */}
        <div className="flex items-start gap-4">
          <div className="h-14 w-14 flex-shrink-0 overflow-hidden rounded-xl bg-primary/10">
            {job.hotel.logo_url ? (
              <img
                src={job.hotel.logo_url}
                alt={job.hotel.name}
                className="h-full w-full object-cover"
              />
            ) : (
              <div className="flex h-full w-full items-center justify-center text-xl font-bold text-primary">
                {job.hotel.name.charAt(0).toUpperCase()}
              </div>
            )}
          </div>

          <div className="min-w-0 flex-1">
            <h1 className="text-xl font-bold text-gray-900">{job.title}</h1>
            <Link
              to={`/hotels/${job.hotel.slug}`}
              className="flex items-center gap-1 text-sm text-primary hover:underline"
            >
              <Building2 className="h-3.5 w-3.5" />
              {job.hotel.name}
            </Link>
          </div>

          {/* Job status badge (non-active) */}
          {!isActive && (
            <span className="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500">
              {job.status === 'paused' ? 'Pausada' : 'Encerrada'}
            </span>
          )}
        </div>

        {/* Share button */}
        {user && (
          <div className="mt-3 flex justify-end">
            <button
              onClick={() => setShowShare(true)}
              className="flex items-center gap-1.5 rounded-xl border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 transition-all"
            >
              <Share2 className="h-4 w-4" />
              Compartilhar vaga
            </button>
          </div>
        )}

        {/* Meta info */}
        <div className="mt-4 flex flex-wrap gap-3 text-sm">
          {(job.city || job.state) && (
            <span className="flex items-center gap-1 text-gray-500">
              <MapPin className="h-4 w-4 text-gray-400" />
              {[job.city, job.state].filter(Boolean).join(', ')}
            </span>
          )}
          <span className="flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
            <Briefcase className="h-3.5 w-3.5" />
            {CONTRACT_LABELS[job.contract_type] ?? job.contract_type}
          </span>
          <span className="flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
            <Monitor className="h-3.5 w-3.5" />
            {MODALITY_LABELS[job.modality] ?? job.modality}
          </span>
          {job.salary_range && (
            <span className="flex items-center gap-1 text-gray-500">
              <DollarSign className="h-4 w-4 text-gray-400" />
              {job.salary_range}
            </span>
          )}
        </div>

        <p className="mt-2 text-xs text-gray-400">
          Publicada em {formatDate(job.created_at)}
        </p>

        {/* Description */}
        <section className="mt-6">
          <h2 className="mb-2 font-semibold text-gray-900">Descrição</h2>
          <p className="whitespace-pre-wrap text-sm leading-relaxed text-gray-700">
            {job.description}
          </p>
        </section>

        {/* Requirements */}
        {job.requirements && (
          <section className="mt-6">
            <h2 className="mb-2 font-semibold text-gray-900">Requisitos</h2>
            <p className="whitespace-pre-wrap text-sm leading-relaxed text-gray-700">
              {job.requirements}
            </p>
          </section>
        )}

        {/* Benefits */}
        {job.benefits && (
          <section className="mt-6">
            <h2 className="mb-2 font-semibold text-gray-900">Benefícios</h2>
            <p className="whitespace-pre-wrap text-sm leading-relaxed text-gray-700">
              {job.benefits}
            </p>
          </section>
        )}

        {/* Apply section */}
        {user && (
          <div className="mt-8 border-t border-gray-100 pt-6">
            {hasApplied ? (
              // Already applied state
              <div className="flex items-center gap-3 rounded-xl border border-green-100 bg-green-50 p-4">
                <div className="flex-1">
                  <p className="font-medium text-green-800">
                    Candidatura enviada com sucesso!
                  </p>
                  {existingApplication && (
                    <div className="mt-1 flex items-center gap-2">
                      <span className="text-sm text-green-600">Status:</span>
                      <ApplicationStatus status={existingApplication.status} />
                    </div>
                  )}
                </div>
              </div>
            ) : isActive ? (
              // Apply form
              <div>
                <button
                  type="button"
                  onClick={() => setShowCoverLetter((v) => !v)}
                  className="flex w-full items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-left text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                >
                  <span>Carta de apresentação (opcional)</span>
                  {showCoverLetter ? (
                    <ChevronUp className="h-4 w-4 text-gray-400" />
                  ) : (
                    <ChevronDown className="h-4 w-4 text-gray-400" />
                  )}
                </button>

                {showCoverLetter && (
                  <form onSubmit={handleApply} className="mt-3">
                    <textarea
                      value={coverLetter}
                      onChange={(e) => setCoverLetter(e.target.value)}
                      rows={5}
                      placeholder="Escreva uma mensagem para o recrutador..."
                      className="w-full resize-none rounded-xl border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />
                    <div className="mt-3 flex justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => setShowCoverLetter(false)}
                        className="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100"
                      >
                        Cancelar
                      </button>
                      <button
                        type="submit"
                        disabled={applyMutation.isPending}
                        className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50"
                      >
                        {applyMutation.isPending ? 'Enviando...' : 'Enviar candidatura'}
                      </button>
                    </div>
                  </form>
                )}

                {!showCoverLetter && (
                  <form onSubmit={handleApply} className="mt-3">
                    <button
                      type="submit"
                      disabled={applyMutation.isPending}
                      className="w-full rounded-xl bg-primary py-3 text-sm font-semibold text-white transition hover:bg-primary/90 disabled:opacity-50"
                    >
                      {applyMutation.isPending ? 'Enviando...' : 'Candidatar-se agora'}
                    </button>
                  </form>
                )}

                {/* API error */}
                {applyMutation.isError && (
                  <p className="mt-2 text-center text-xs text-red-500">
                    Erro ao enviar candidatura. Tente novamente.
                  </p>
                )}
              </div>
            ) : (
              // Job not active
              <div className="rounded-xl border border-gray-100 bg-gray-50 p-4 text-center">
                <p className="text-sm text-gray-500">
                  Esta vaga está{' '}
                  <strong>{job.status === 'paused' ? 'pausada' : 'encerrada'}</strong>{' '}
                  e não aceita novas candidaturas.
                </p>
              </div>
            )}
          </div>
        )}
      </article>

      {/* Share modal */}
      {showShare && job && (
        <ShareJobModal job={job} onClose={() => setShowShare(false)} />
      )}
    </div>
  )
}
