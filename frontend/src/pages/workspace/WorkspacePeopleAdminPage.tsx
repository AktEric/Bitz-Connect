import { useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Gift, Briefcase, ExternalLink, FileText, Search, ShieldCheck, Wallet, Building2 } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { WorkspaceMember } from '@/types/workspace.types'
import { cn } from '@/utils/cn'
import { RecordSection } from './PersonCardModal'
import ProfileCareer from './ProfileCareer'
import PersonBenefits from './benefits/PersonBenefits'
import { SalaryAdminField } from './ProfileSalary'
import { Empty, PageHeader, Panel, Spinner, btnPrimary, errMsg, inputCls, labelCls, useWs } from './ui'

type Tab = 'vinculo' | 'ficha' | 'salario' | 'beneficios' | 'carreira'
const TABS: { key: Tab; label: string; Icon: typeof Briefcase }[] = [
  { key: 'vinculo', label: 'Vínculo', Icon: Building2 },
  { key: 'ficha', label: 'Ficha cadastral', Icon: FileText },
  { key: 'salario', label: 'Salário', Icon: Wallet },
  { key: 'beneficios', label: 'Benefícios', Icon: Gift },
  { key: 'carreira', label: 'Carreira', Icon: Briefcase },
]

/** Vínculo: cargo, admissão, setor e gestor direto. */
function JobForm({ slug, member, members }: { slug: string; member: WorkspaceMember; members: WorkspaceMember[] }) {
  const qc = useQueryClient()
  const { data: departments } = useQuery({ queryKey: ['ws', slug, 'departments'], queryFn: () => workspaceService.departments(slug) })
  const [jobTitle, setJobTitle] = useState(member.job_title ?? '')
  const [hiredAt, setHiredAt] = useState(member.hired_at ?? '')
  const [dept, setDept] = useState(member.department?.uuid ?? '')
  const [manager, setManager] = useState(member.manager?.uuid ?? '')
  const [msg, setMsg] = useState('')
  const save = useMutation({
    mutationFn: () => workspaceService.updateMember(slug, member.uuid, { job_title: jobTitle.trim() || null, hired_at: hiredAt || null, department_uuid: dept || null, manager_uuid: manager || null }),
    onSuccess: () => { setMsg('Vínculo salvo.'); qc.invalidateQueries({ queryKey: ['ws', slug] }); qc.invalidateQueries({ queryKey: ['workspace', slug] }) },
    onError: (e) => setMsg(errMsg(e)),
  })

  return (
    <fieldset>
      <legend className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Vínculo na empresa</legend>
      <div className="grid gap-3 sm:grid-cols-2">
        <div><label className={labelCls} htmlFor="pa-job">Cargo</label><input id="pa-job" className={inputCls} value={jobTitle} maxLength={120} onChange={(e) => setJobTitle(e.target.value)} /></div>
        <div><label className={labelCls} htmlFor="pa-hired">Data de admissão</label><input id="pa-hired" type="date" className={inputCls} value={hiredAt} onChange={(e) => setHiredAt(e.target.value)} /></div>
        <div><label className={labelCls} htmlFor="pa-dept">Setor</label>
          <select id="pa-dept" className={inputCls} value={dept} onChange={(e) => setDept(e.target.value)}><option value="">Sem setor</option>{departments?.map((d) => <option key={d.uuid} value={d.uuid}>{d.name}</option>)}</select></div>
        <div><label className={labelCls} htmlFor="pa-mgr">Gestor direto</label>
          <select id="pa-mgr" className={inputCls} value={manager} onChange={(e) => setManager(e.target.value)}><option value="">Sem gestor</option>{members.filter((m) => m.uuid !== member.uuid).map((m) => <option key={m.uuid} value={m.uuid}>{m.name}</option>)}</select></div>
      </div>
      <div className="mt-3 flex items-center justify-end gap-3">
        {msg && <span className="text-sm text-gray-500" role="status">{msg}</span>}
        <button type="button" className={btnPrimary} disabled={save.isPending} onClick={() => save.mutate()}>Salvar vínculo</button>
      </div>
    </fieldset>
  )
}

/**
 * Cadastro de pessoas: a ÚNICA tela onde dono e RH editam vínculo, ficha cadastral, salário e carreira de alguém.
 * O perfil da pessoa nunca mostra esses campos de edição.
 */
export default function WorkspacePeopleAdminPage() {
  const { slug } = useWs()
  const [params, setParams] = useSearchParams()
  const [q, setQ] = useState('')
  const selected = params.get('pessoa')
  const [tab, setTab] = useState<Tab>('vinculo')
  const { data: members, isLoading } = useQuery({ queryKey: ['ws', slug, 'members'], queryFn: () => workspaceService.members(slug) })
  const filtered = useMemo(() => (members ?? []).filter((m) => !q.trim() || `${m.name} ${m.job_title ?? ''}`.toLowerCase().includes(q.trim().toLowerCase())), [members, q])
  const person = members?.find((m) => m.uuid === selected)

  return (
    <div>
      <PageHeader title="Cadastro de pessoas" subtitle="Edição de vínculo, ficha cadastral, salário e carreira. Só dono e RH têm acesso a esta tela." />
      <div className="grid gap-5 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <aside className="ws-card h-fit rounded-2xl bg-white p-3 lg:sticky lg:top-20" aria-label="Pessoas">
          <div className="relative mb-2">
            <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
            <input type="search" aria-label="Buscar pessoa" className={cn(inputCls, 'pl-9')} placeholder="Buscar pessoa" value={q} onChange={(e) => setQ(e.target.value)} />
          </div>
          {isLoading ? <Spinner /> : (
            <ul className="max-h-[60vh] space-y-0.5 overflow-y-auto">
              {filtered.map((m) => (
                <li key={m.uuid}>
                  <button type="button" onClick={() => setParams({ pessoa: m.uuid }, { replace: true })} aria-current={m.uuid === selected}
                    className={cn('flex w-full items-center gap-2.5 rounded-xl p-2 text-left', m.uuid === selected ? 'bg-brand-50' : 'hover:bg-gray-50')}>
                    <Avatar src={m.avatar_url} name={m.name} size="sm" />
                    <span className="min-w-0"><span className="block truncate text-sm font-medium text-gray-900">{m.name}</span><span className="block truncate text-xs text-gray-500">{m.job_title ?? 'Sem cargo'}</span></span>
                  </button>
                </li>
              ))}
              {!filtered.length && <li className="px-2 py-6 text-center text-sm text-gray-400">Ninguém encontrado.</li>}
            </ul>
          )}
        </aside>

        {!person ? <Empty text="Escolha uma pessoa na lista para editar o cadastro." /> : (
          <div className="min-w-0 space-y-4" key={person.uuid}>
            <Panel className="flex items-center gap-3">
              <Avatar src={person.avatar_url} name={person.name} size="xl" />
              <div className="min-w-0 flex-1">
                <h2 className="truncate text-lg font-bold text-gray-900">{person.name}</h2>
                <p className="text-sm text-gray-600">{[person.job_title, person.department?.name].filter(Boolean).join(' · ') || 'Sem cargo'}</p>
              </div>
              <Link to={`/w/${slug}/pessoas/${person.uuid}`} className="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:underline"><ExternalLink className="h-4 w-4" /> Ver perfil</Link>
            </Panel>

            <div role="tablist" aria-label="Seções do cadastro" className="flex gap-1 overflow-x-auto border-b border-gray-200">
              {TABS.map((t) => (
                <button key={t.key} role="tab" type="button" id={`tab-${t.key}`} aria-selected={tab === t.key} aria-controls={`panel-${t.key}`} onClick={() => setTab(t.key)}
                  className={cn('inline-flex items-center gap-1.5 whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium transition', tab === t.key ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-800')}>
                  <t.Icon className="h-4 w-4" /> {t.label}
                </button>
              ))}
            </div>

            <div role="tabpanel" id={`panel-${tab}`} aria-labelledby={`tab-${tab}`}>
              {tab === 'vinculo' && members && <Panel><JobForm slug={slug} member={person} members={members} /></Panel>}
              {tab === 'ficha' && (
                <Panel>
                  <p className="mb-3 flex items-center gap-1.5 text-xs text-gray-400"><ShieldCheck className="h-4 w-4 text-brand-500" /> Dados sensíveis: cada abertura desta ficha fica registrada na auditoria.</p>
                  <RecordSection slug={slug} uuid={person.uuid} editable />
                </Panel>
              )}
              {tab === 'salario' && <Panel><SalaryAdminField slug={slug} uuid={person.uuid} /></Panel>}
              {tab === 'beneficios' && <PersonBenefits uuid={person.uuid} />}
              {tab === 'carreira' && <ProfileCareer slug={slug} uuid={person.uuid} editable />}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
