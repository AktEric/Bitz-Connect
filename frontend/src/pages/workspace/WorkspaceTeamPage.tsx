import { useMemo, useState } from 'react'
import { useCan } from './permissions'
import { Link, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Copy, Link2, Pencil, Plus, Search, Trash2 } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { WorkspaceMember } from '@/types/workspace.types'
import { Empty, Modal, PageHeader, Panel, Pill, Spinner, Tabs, btnGhost, btnPrimary, errMsg, fmtDate, inputCls, labelCls, useWs } from './ui'

function EditMemberModal({ slug, member, members, onClose }: { slug: string; member: WorkspaceMember; members: WorkspaceMember[]; onClose: () => void }) {
  const qc = useQueryClient()
  const { data: departments } = useQuery({ queryKey: ['ws', slug, 'departments'], queryFn: () => workspaceService.departments(slug) })
  const [jobTitle, setJobTitle] = useState(member.job_title ?? '')
  const [hiredAt, setHiredAt] = useState(member.hired_at ?? '')
  const [dept, setDept] = useState(member.department?.uuid ?? '')
  const [manager, setManager] = useState(member.manager?.uuid ?? '')
  const [error, setError] = useState('')

  const save = useMutation({
    mutationFn: () => workspaceService.updateMember(slug, member.uuid, {
      job_title: jobTitle.trim() || null, hired_at: hiredAt || null, department_uuid: dept || null, manager_uuid: manager || null,
    }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'members'] }); qc.invalidateQueries({ queryKey: ['workspace', slug] }); onClose() },
    onError: (e) => setError(errMsg(e)),
  })

  return (
    <Modal title={`Perfil de ${member.name}`} onClose={onClose}>
      <div className="space-y-3">
        <div><label className={labelCls}>Cargo</label><input className={inputCls} value={jobTitle} onChange={(e) => setJobTitle(e.target.value)} maxLength={120} /></div>
        <div><label className={labelCls}>Data de admissão</label><input type="date" className={inputCls} value={hiredAt} onChange={(e) => setHiredAt(e.target.value)} /></div>
        <div>
          <label className={labelCls}>Setor</label>
          <select className={inputCls} value={dept} onChange={(e) => setDept(e.target.value)}>
            <option value="">Sem setor</option>
            {departments?.map((d) => <option key={d.uuid} value={d.uuid}>{d.name}</option>)}
          </select>
        </div>
        <div>
          <label className={labelCls}>Gestor direto</label>
          <select className={inputCls} value={manager} onChange={(e) => setManager(e.target.value)}>
            <option value="">Sem gestor</option>
            {members.filter((m) => m.uuid !== member.uuid).map((m) => <option key={m.uuid} value={m.uuid}>{m.name}</option>)}
          </select>
        </div>
        {error && <p className="text-sm text-red-600">{error}</p>}
        <div className="flex justify-end gap-2 pt-2">
          <button className={btnGhost} onClick={onClose}>Cancelar</button>
          <button className={btnPrimary} disabled={save.isPending} onClick={() => save.mutate()}>Salvar</button>
        </div>
      </div>
    </Modal>
  )
}

function InvitesPanel({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const { data: departments } = useQuery({ queryKey: ['ws', slug, 'departments'], queryFn: () => workspaceService.departments(slug) })
  const { data: invites, isLoading } = useQuery({ queryKey: ['ws', slug, 'invites'], queryFn: () => workspaceService.invites(slug) })
  const [email, setEmail] = useState('')
  const [dept, setDept] = useState('')
  const [title, setTitle] = useState('')
  const [copied, setCopied] = useState('')
  const [error, setError] = useState('')

  const create = useMutation({
    mutationFn: () => workspaceService.createInvite(slug, { email: email.trim() || null, department_uuid: dept || null, job_title: title.trim() || null }),
    onSuccess: () => { setEmail(''); setTitle(''); setDept(''); setError(''); qc.invalidateQueries({ queryKey: ['ws', slug, 'invites'] }) },
    onError: (e) => setError(errMsg(e)),
  })
  const revoke = useMutation({
    mutationFn: (uuid: string) => workspaceService.revokeInvite(slug, uuid),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['ws', slug, 'invites'] }),
  })

  const copy = async (path: string) => {
    const url = `${window.location.origin}${path}`
    try { await navigator.clipboard.writeText(url) } catch { window.prompt('Copie o link:', url) }
    setCopied(path)
    setTimeout(() => setCopied(''), 2000)
  }

  return (
    <div className="space-y-4">
      <Panel>
        <h3 className="mb-3 font-semibold text-gray-900">Novo convite</h3>
        <div className="grid gap-3 sm:grid-cols-3">
          <div><label className={labelCls}>E-mail (opcional — trava o convite)</label><input className={inputCls} type="email" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
          <div><label className={labelCls}>Cargo inicial</label><input className={inputCls} value={title} onChange={(e) => setTitle(e.target.value)} /></div>
          <div>
            <label className={labelCls}>Setor</label>
            <select className={inputCls} value={dept} onChange={(e) => setDept(e.target.value)}>
              <option value="">—</option>
              {departments?.map((d) => <option key={d.uuid} value={d.uuid}>{d.name}</option>)}
            </select>
          </div>
        </div>
        {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
        <button className={`${btnPrimary} mt-3`} disabled={create.isPending} onClick={() => create.mutate()}><Link2 className="h-4 w-4" /> Gerar link</button>
        <p className="mt-2 text-xs text-gray-400">Quem abrir o link e entrar com a própria conta passa a fazer parte da empresa com o cargo e setor definidos.</p>
      </Panel>

      {isLoading ? <Spinner /> : !invites?.length ? <Empty text="Nenhum convite criado." /> : (
        <div className="space-y-2">
          {invites.map((i) => (
            <Panel key={i.uuid} className="flex flex-wrap items-center gap-3 !p-3">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-gray-900">{i.email ?? 'Link aberto'} {i.job_title && <span className="text-gray-400">· {i.job_title}</span>}</p>
                <p className="text-xs text-gray-400">{i.uses ?? 0}/{i.max_uses} usos · expira {fmtDate(i.expires_at)}</p>
              </div>
              {i.usable ? <Pill tone="green">Ativo</Pill> : <Pill tone="gray">{i.revoked ? 'Revogado' : 'Encerrado'}</Pill>}
              {i.usable && (
                <>
                  <button className={btnGhost} onClick={() => copy(i.path)}><Copy className="h-4 w-4" /> {copied === i.path ? 'Copiado!' : 'Copiar link'}</button>
                  <button className="text-gray-400 hover:text-red-500" aria-label="Revogar" onClick={() => revoke.mutate(i.uuid)}><Trash2 className="h-4 w-4" /></button>
                </>
              )}
            </Panel>
          ))}
        </div>
      )}
    </div>
  )
}

function DepartmentsPanel({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'departments'], queryFn: () => workspaceService.departments(slug) })
  const [name, setName] = useState('')
  const [error, setError] = useState('')
  const refresh = () => qc.invalidateQueries({ queryKey: ['ws', slug, 'departments'] })
  const create = useMutation({ mutationFn: () => workspaceService.createDepartment(slug, name.trim()), onSuccess: () => { setName(''); setError(''); refresh() }, onError: (e) => setError(errMsg(e)) })
  const remove = useMutation({ mutationFn: (uuid: string) => workspaceService.deleteDepartment(slug, uuid), onSuccess: refresh })

  return (
    <Panel>
      <h3 className="mb-3 font-semibold text-gray-900">Setores</h3>
      <form className="mb-3 flex gap-2" onSubmit={(e) => { e.preventDefault(); if (name.trim()) create.mutate() }}>
        <input className={inputCls} value={name} onChange={(e) => setName(e.target.value)} placeholder="Novo setor (ex.: Financeiro)" maxLength={100} />
        <button className={btnPrimary} disabled={!name.trim() || create.isPending}><Plus className="h-4 w-4" /> Adicionar</button>
      </form>
      {error && <p className="mb-2 text-sm text-red-600">{error}</p>}
      {isLoading ? <Spinner /> : (
        <ul className="divide-y divide-gray-100">
          {data?.map((d) => (
            <li key={d.uuid} className="flex items-center justify-between py-2 text-sm">
              <span className="text-gray-800">{d.name} <span className="text-xs text-gray-400">· {d.members_count ?? 0} pessoas</span></span>
              <button className="text-gray-400 hover:text-red-500" aria-label={`Remover ${d.name}`} onClick={() => { if (confirm(`Remover o setor ${d.name}? As pessoas ficam sem setor.`)) remove.mutate(d.uuid) }}><Trash2 className="h-4 w-4" /></button>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  )
}

export default function WorkspaceTeamPage() {
  const { slug, info } = useWs()
  const canInvite = useCan('invites.manage')
  const canAreas = useCan('areas.manage')
  const canEditPeople = useCan('people.manage')
  const [tab, setTab] = useState<'people' | 'invites' | 'departments'>('people')
  const [params] = useSearchParams()
  const [q, setQ] = useState(params.get('q') ?? '')
  const [dept, setDept] = useState('')
  const [editing, setEditing] = useState<WorkspaceMember | null>(null)

  const { data: members, isLoading } = useQuery({ queryKey: ['ws', slug, 'members'], queryFn: () => workspaceService.members(slug) })
  const departments = useMemo(() => [...new Set((members ?? []).map((m) => m.department?.name).filter(Boolean) as string[])].sort(), [members])
  const filtered = useMemo(() => (members ?? []).filter((m) =>
    (!dept || m.department?.name === dept) &&
    (!q.trim() || `${m.name} ${m.job_title ?? ''}`.toLowerCase().includes(q.trim().toLowerCase()))), [members, q, dept])

  return (
    <div>
      <PageHeader title="Equipe" subtitle={`${members?.length ?? info.counts.members} pessoas na empresa`} />

      {(canInvite || canAreas) && (
        <Tabs value={tab} onChange={setTab} options={[{ value: 'people', label: 'Pessoas' }, ...(canInvite ? [{ value: 'invites' as const, label: 'Convites' }] : []), ...(canAreas ? [{ value: 'departments' as const, label: 'Setores' }] : [])]} />
      )}

      {tab === 'invites' && canInvite && <InvitesPanel slug={slug} />}
      {tab === 'departments' && canAreas && <DepartmentsPanel slug={slug} />}

      {tab === 'people' && (
        <>
          <div className="mb-4 flex flex-wrap gap-2">
            <div className="relative min-w-[200px] flex-1">
              <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
              <input className={`${inputCls} !pl-9`} placeholder="Buscar por nome ou cargo" value={q} onChange={(e) => setQ(e.target.value)} />
            </div>
            <select className={`${inputCls} !w-auto`} value={dept} onChange={(e) => setDept(e.target.value)} aria-label="Setor">
              <option value="">Todos os setores</option>
              {departments.map((d) => <option key={d} value={d}>{d}</option>)}
            </select>
          </div>
          {isLoading ? <Spinner /> : !filtered.length ? <Empty text="Ninguém encontrado." /> : (
            <div className="grid gap-3 sm:grid-cols-2">
              {filtered.map((m) => (
                <Panel key={m.uuid} className="flex items-start gap-3 !p-3">
                  <Link to={`/w/${slug}/pessoas/${m.uuid}`} aria-label={`Ver perfil de ${m.name}`}><Avatar src={m.avatar_url} name={m.name} size="lg" /></Link>
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-semibold text-gray-900">{m.name} {m.role === 'admin' && <Pill tone="brand">RH/Gestão</Pill>}</p>
                    <p className="truncate text-sm text-gray-600">{m.job_title ?? 'Cargo não informado'}</p>
                    <p className="truncate text-xs text-gray-400">{[m.department?.name, m.manager ? `Gestor: ${m.manager.name}` : null].filter(Boolean).join(' · ')}</p>
                    {m.hired_at && <p className="text-xs text-gray-400">Desde {fmtDate(m.hired_at)}</p>}
                  </div>
                  {canEditPeople && (
                    <button className="text-gray-400 hover:text-brand-600" aria-label={`Editar ${m.name}`} onClick={() => setEditing(m)}><Pencil className="h-4 w-4" /></button>
                  )}
                </Panel>
              ))}
            </div>
          )}
        </>
      )}

      {editing && members && <EditMemberModal slug={slug} member={editing} members={members} onClose={() => setEditing(null)} />}
    </div>
  )
}
