import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Copy, MapPin, MessageCircle, Plus, Users } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { JobApplicationItem, JobItem } from '@/types/workspace.types'
import { Empty, Modal, PageHeader, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, fmtDate, inputCls, labelCls, useWs } from './ui'

const STATUS_LABEL: Record<JobApplicationItem['status'], string> = { new: 'Nova', reviewing: 'Em análise', rejected: 'Não avançou', hired: 'Contratado' }

function shareLinks(job: JobItem, companyName: string) {
  const url = job.share_url ? `${window.location.origin}${job.share_url}` : ''
  const text = `Vaga aberta em ${companyName}: ${job.title}${url ? `\n${url}` : ''}`
  return { url, whatsapp: `https://wa.me/?text=${encodeURIComponent(text)}` }
}

function JobForm({ slug, job, onClose }: { slug: string; job?: JobItem; onClose: () => void }) {
  const qc = useQueryClient()
  const { data: departments } = useQuery({ queryKey: ['ws', slug, 'departments'], queryFn: () => workspaceService.departments(slug) })
  const [f, setF] = useState({
    title: job?.title ?? '', description: job?.description ?? '', requirements: job?.requirements ?? '', location: job?.location ?? '',
    contract_type: job?.contract_type ?? '', contact: job?.contact ?? '', department_uuid: job?.department_uuid ?? '',
    public: job?.public ?? false, closes_on: job?.closes_on ?? '',
  })
  const [error, setError] = useState('')
  const set = (k: keyof typeof f, v: string | boolean) => setF((x) => ({ ...x, [k]: v }))
  const save = useMutation({
    mutationFn: () => {
      const body = { ...f, department_uuid: f.department_uuid || null, closes_on: f.closes_on || null }
      return job ? workspaceService.updateJob(slug, job.uuid, body) : workspaceService.createJob(slug, body)
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'jobs'] }); onClose() },
    onError: (e) => setError(errMsg(e)),
  })

  return (
    <Modal title={job ? 'Editar vaga' : 'Nova vaga'} onClose={onClose}>
      <div className="space-y-3">
        <div><label className={labelCls} htmlFor="j-title">Título da vaga</label><input id="j-title" className={inputCls} maxLength={120} value={f.title} onChange={(e) => set('title', e.target.value)} /></div>
        <div><label className={labelCls} htmlFor="j-desc">Descrição</label><textarea id="j-desc" rows={4} className={inputCls} maxLength={5000} value={f.description} onChange={(e) => set('description', e.target.value)} /></div>
        <div><label className={labelCls} htmlFor="j-req">Requisitos (opcional)</label><textarea id="j-req" rows={2} className={inputCls} maxLength={3000} value={f.requirements} onChange={(e) => set('requirements', e.target.value)} /></div>
        <div className="grid grid-cols-2 gap-3">
          <div><label className={labelCls} htmlFor="j-dept">Setor</label>
            <select id="j-dept" className={inputCls} value={f.department_uuid} onChange={(e) => set('department_uuid', e.target.value)}>
              <option value="">—</option>{departments?.map((d) => <option key={d.uuid} value={d.uuid}>{d.name}</option>)}
            </select></div>
          <div><label className={labelCls} htmlFor="j-type">Contratação</label><input id="j-type" className={inputCls} placeholder="CLT, PJ, estágio…" value={f.contract_type} onChange={(e) => set('contract_type', e.target.value)} /></div>
          <div><label className={labelCls} htmlFor="j-loc">Local</label><input id="j-loc" className={inputCls} value={f.location} onChange={(e) => set('location', e.target.value)} /></div>
          <div><label className={labelCls} htmlFor="j-close">Inscrições até</label><input id="j-close" type="date" className={inputCls} value={f.closes_on} onChange={(e) => set('closes_on', e.target.value)} /></div>
        </div>
        <label className="flex cursor-pointer items-start gap-2 text-sm text-gray-700">
          <input type="checkbox" className="mt-0.5 rounded border-gray-300 text-violet-600" checked={f.public} onChange={(e) => set('public', e.target.checked)} />
          <span>Divulgar por link público (WhatsApp, redes)<br /><span className="text-xs text-gray-400">Qualquer pessoa com o link vê a vaga, sem entrar no sistema. Candidatos de fora usam o contato abaixo.</span></span>
        </label>
        {f.public && <div><label className={labelCls} htmlFor="j-contact">Contato para candidatos externos</label><input id="j-contact" className={inputCls} placeholder="e-mail ou WhatsApp do RH" value={f.contact} onChange={(e) => set('contact', e.target.value)} /></div>}
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>Cancelar</button>
          <button type="button" className={btnPrimary} disabled={save.isPending || !f.title.trim() || !f.description.trim()} onClick={() => save.mutate()}>{job ? 'Salvar' : 'Publicar vaga'}</button>
        </div>
      </div>
    </Modal>
  )
}

function Applicants({ slug, job, onClose }: { slug: string; job: JobItem; onClose: () => void }) {
  const qc = useQueryClient()
  const key = ['ws', slug, 'jobs', job.uuid, 'apps']
  const { data, isLoading } = useQuery({ queryKey: key, queryFn: () => workspaceService.jobApplications(slug, job.uuid) })
  const set = useMutation({
    mutationFn: ({ app, status }: { app: string; status: JobApplicationItem['status'] }) => workspaceService.setJobApplication(slug, job.uuid, app, status),
    onSuccess: () => qc.invalidateQueries({ queryKey: key }),
  })
  return (
    <Modal title={`Candidatos — ${job.title}`} onClose={onClose}>
      {isLoading ? <Spinner /> : !data?.length ? <Empty text="Ninguém se candidatou ainda." /> : (
        <ul className="space-y-3">
          {data.map((a) => (
            <li key={a.uuid} className="rounded-xl border border-gray-100 p-3">
              <div className="flex items-center gap-3">
                <Avatar src={a.person?.avatar_url} name={a.person?.name ?? '?'} size="sm" />
                <div className="min-w-0 flex-1"><p className="truncate text-sm font-semibold text-gray-900">{a.person?.name}</p><p className="truncate text-xs text-gray-500">{[a.person?.job_title, a.department].filter(Boolean).join(' · ')}</p></div>
                <select className="rounded-lg border border-gray-200 bg-white px-2 py-1 text-xs" aria-label="Situação" value={a.status} onChange={(e) => set.mutate({ app: a.uuid, status: e.target.value as JobApplicationItem['status'] })}>
                  {Object.entries(STATUS_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
              </div>
              {a.message && <p className="mt-2 flex gap-1.5 text-sm text-gray-600"><MessageCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-gray-400" /> {a.message}</p>}
            </li>
          ))}
        </ul>
      )}
      <p className="mt-3 text-[11px] text-gray-400">Candidaturas são confidenciais: só quem abriu a vaga e o RH leem. O gestor atual do candidato não é avisado.</p>
    </Modal>
  )
}

function JobCard({ slug, job, company, onEdit, onApplicants }: { slug: string; job: JobItem; company: string; onEdit: () => void; onApplicants: () => void }) {
  const qc = useQueryClient()
  const [applying, setApplying] = useState(false)
  const [msg, setMsg] = useState('')
  const [copied, setCopied] = useState(false)
  const refresh = () => qc.invalidateQueries({ queryKey: ['ws', slug, 'jobs'] })
  const apply = useMutation({ mutationFn: () => workspaceService.applyJob(slug, job.uuid, msg.trim() || undefined), onSuccess: () => { setApplying(false); refresh() } })
  const withdraw = useMutation({ mutationFn: () => workspaceService.withdrawJob(slug, job.uuid), onSuccess: refresh })
  const toggle = useMutation({ mutationFn: () => workspaceService.updateJob(slug, job.uuid, { status: job.status === 'open' ? 'closed' : 'open' }), onSuccess: refresh })
  const remove = useMutation({ mutationFn: () => workspaceService.deleteJob(slug, job.uuid), onSuccess: refresh })
  const share = shareLinks(job, company)
  const copy = async () => { try { await navigator.clipboard.writeText(share.url); setCopied(true); setTimeout(() => setCopied(false), 2000) } catch { /* sem permissão */ } }

  return (
    <Panel>
      <div className="flex flex-wrap items-start gap-2">
        <div className="min-w-0 flex-1">
          <h2 className="font-semibold text-gray-900">{job.title}</h2>
          <p className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
            {job.department && <span className="inline-flex items-center gap-1"><Users className="h-3 w-3" /> {job.department}</span>}
            {job.location && <span className="inline-flex items-center gap-1"><MapPin className="h-3 w-3" /> {job.location}</span>}
            {job.contract_type && <span>{job.contract_type}</span>}
            {job.closes_on && <span>até {fmtDate(job.closes_on)}</span>}
          </p>
        </div>
        {job.status === 'closed' && <Pill tone="gray">Encerrada</Pill>}
        {job.public && <Pill tone="blue">Divulgada</Pill>}
        {job.applied && <Pill tone="green">Você se candidatou</Pill>}
      </div>
      <p className="mt-2 whitespace-pre-wrap text-sm text-gray-700">{job.description}</p>
      {job.requirements && <p className="mt-2 whitespace-pre-wrap text-sm text-gray-500"><b className="text-gray-700">Requisitos: </b>{job.requirements}</p>}

      <div className="mt-3 flex flex-wrap items-center gap-2">
        {job.status === 'open' && !job.can_edit && (job.applied
          ? <button type="button" className={btnGhost} disabled={withdraw.isPending} onClick={() => withdraw.mutate()}>Retirar candidatura</button>
          : <button type="button" className={btnPrimary} onClick={() => setApplying(true)}>Quero me candidatar</button>)}
        {job.can_edit && (
          <>
            <button type="button" className={btnGhost} onClick={onApplicants}>Candidatos ({job.applications_count ?? 0})</button>
            <button type="button" className={btnGhost} onClick={onEdit}>Editar</button>
            <button type="button" className={btnGhost} onClick={() => toggle.mutate()}>{job.status === 'open' ? 'Encerrar' : 'Reabrir'}</button>
            <button type="button" className="text-xs text-gray-400 hover:text-red-500" onClick={() => { if (confirm('Remover esta vaga e as candidaturas?')) remove.mutate() }}>Remover</button>
          </>
        )}
        {job.share_url && job.status === 'open' && (
          <span className="ml-auto flex gap-2">
            <a className={btnGhost} href={share.whatsapp} target="_blank" rel="noopener noreferrer">Compartilhar no WhatsApp</a>
            <button type="button" className={btnGhost} onClick={copy}><Copy size={14} /> {copied ? 'Copiado!' : 'Copiar link'}</button>
          </span>
        )}
      </div>

      {applying && (
        <Modal title={`Candidatar-se: ${job.title}`} onClose={() => setApplying(false)}>
          <p className="mb-2 text-sm text-gray-600">Quem abriu a vaga e o RH vão ler sua candidatura. Seu gestor atual não é avisado.</p>
          <textarea rows={4} className={inputCls} maxLength={1000} placeholder="Conte por que você quer essa vaga (opcional)" value={msg} onChange={(e) => setMsg(e.target.value)} />
          {apply.error && <p className="mt-2 text-sm text-red-600" role="alert">{errMsg(apply.error)}</p>}
          <div className="mt-3 flex justify-end gap-2">
            <button type="button" className={btnGhost} onClick={() => setApplying(false)}>Cancelar</button>
            <button type="button" className={btnPrimary} disabled={apply.isPending} onClick={() => apply.mutate()}>Enviar candidatura</button>
          </div>
        </Modal>
      )}
    </Panel>
  )
}

export default function WorkspaceJobsPage() {
  const { slug, info } = useWs()
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'jobs'], queryFn: () => workspaceService.jobs(slug) })
  const [form, setForm] = useState<JobItem | 'new' | null>(null)
  const [apps, setApps] = useState<JobItem | null>(null)

  return (
    <div>
      <PageHeader
        title="Vagas"
        subtitle="Oportunidades abertas na empresa: candidate-se ou divulgue as da sua área."
        actions={data?.can_create ? <button type="button" className={btnPrimary} onClick={() => setForm('new')}><Plus size={16} /> Nova vaga</button> : undefined}
      />
      {isLoading ? <Spinner /> : !data?.items.length ? <Empty text="Nenhuma vaga aberta no momento." /> : (
        <div className="space-y-3">
          {data.items.map((j) => <JobCard key={j.uuid} slug={slug} job={j} company={info.hotel.name} onEdit={() => setForm(j)} onApplicants={() => setApps(j)} />)}
        </div>
      )}
      {form && <JobForm slug={slug} job={form === 'new' ? undefined : form} onClose={() => setForm(null)} />}
      {apps && <Applicants slug={slug} job={apps} onClose={() => setApps(null)} />}
    </div>
  )
}
