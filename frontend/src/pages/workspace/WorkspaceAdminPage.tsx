import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, LayoutTemplate, ShieldCheck } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { AccessRole } from '@/types/workspace.types'
import WorkspaceSchedulesSection from './WorkspaceSchedulesSection'
import { Empty, PageHeader, Panel, Pill, Spinner, btnPrimary, errMsg, inputCls, useWs } from './ui'

const ROLE_INFO: Record<AccessRole, { label: string; hint: string }> = {
  owner: { label: 'Dono', hint: 'Controle total, inclusive papéis de acesso.' },
  hr: { label: 'RH', hint: 'Pessoas, aprovações, mural, metas, ponto e holerites.' },
  finance: { label: 'Financeiro', hint: 'Anexa e consulta holerites e recebimentos de todos.' },
  manager: { label: 'Gestor', hint: 'Vê e aprova a própria equipe.' },
  employee: { label: 'Colaborador', hint: 'Acessa apenas os próprios dados.' },
}
const ROLE_ORDER: AccessRole[] = ['owner', 'hr', 'finance', 'manager', 'employee']

function RolesSection({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const [error, setError] = useState('')
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'roles'], queryFn: () => workspaceService.roles(slug) })

  const change = useMutation({
    mutationFn: ({ uuid, role }: { uuid: string; role: AccessRole }) => workspaceService.updateRole(slug, uuid, role),
    onSuccess: () => {
      setError('')
      qc.invalidateQueries({ queryKey: ['ws', slug, 'roles'] })
      qc.invalidateQueries({ queryKey: ['workspace', slug] })
    },
    onError: (e) => setError(errMsg(e)),
  })

  if (isLoading) return <Spinner />
  const items = data?.items ?? []
  const canEdit = data?.can_edit ?? false

  return (
    <section className="mb-8">
      <div className="mb-3 flex items-center gap-2">
        <ShieldCheck className="h-5 w-5 text-violet-600" />
        <h2 className="text-lg font-semibold text-gray-900">Papéis de acesso</h2>
      </div>
      <div className="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        {ROLE_ORDER.map((r) => (
          <div key={r} className="rounded-xl border border-gray-100 bg-white p-3">
            <div className="text-sm font-semibold text-gray-900">{ROLE_INFO[r].label}</div>
            <div className="text-xs text-gray-500">{ROLE_INFO[r].hint}</div>
          </div>
        ))}
      </div>
      {!canEdit && <p className="mb-3 text-sm text-gray-500">Somente o dono da empresa pode alterar papéis.</p>}
      {error && <p className="mb-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
      {items.length === 0 ? (
        <Empty text="Nenhuma pessoa na empresa ainda." />
      ) : (
        <Panel className="divide-y divide-gray-100 p-0">
          {items.map((row) => (
            <div key={row.user.uuid} className="flex flex-wrap items-center gap-3 px-4 py-3">
              <Avatar src={row.user.avatar_url} name={row.user.name} size="md" />
              <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-medium text-gray-900">{row.user.name}</div>
                <div className="truncate text-xs text-gray-500">{row.user.job_title ?? row.email ?? '—'}</div>
              </div>
              {canEdit ? (
                <select
                  className={`${inputCls} !w-auto`}
                  value={row.access_role}
                  disabled={change.isPending}
                  onChange={(e) => change.mutate({ uuid: row.user.uuid, role: e.target.value as AccessRole })}
                  aria-label={`Papel de ${row.user.name}`}
                >
                  {ROLE_ORDER.map((r) => <option key={r} value={r}>{ROLE_INFO[r].label}</option>)}
                </select>
              ) : (
                <Pill tone="violet">{ROLE_INFO[row.access_role].label}</Pill>
              )}
            </div>
          ))}
        </Panel>
      )}
    </section>
  )
}

function TemplateSection({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const { info } = useWs()
  const { data: templates, isLoading } = useQuery({ queryKey: ['segment-templates'], queryFn: workspaceService.segmentTemplates, staleTime: 3_600_000 })
  const [selected, setSelected] = useState<string>('')
  const [result, setResult] = useState<string[] | null>(null)
  const [error, setError] = useState('')

  const apply = useMutation({
    mutationFn: () => workspaceService.applySegmentTemplate(slug, selected),
    onSuccess: (r) => {
      setError('')
      setResult(r.created)
      qc.invalidateQueries({ queryKey: ['ws', slug, 'departments'] })
    },
    onError: (e) => setError(errMsg(e)),
  })

  if (isLoading) return <Spinner />
  const current = templates?.find((t) => t.key === selected)

  return (
    <section>
      <div className="mb-1 flex items-center gap-2">
        <LayoutTemplate className="h-5 w-5 text-violet-600" />
        <h2 className="text-lg font-semibold text-gray-900">Estrutura por segmento</h2>
      </div>
      <p className="mb-3 text-sm text-gray-500">
        Cria os setores sugeridos para o ramo de {info.hotel.name}. Setores que já existem são mantidos.
      </p>
      <div className="mb-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        {(templates ?? []).map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => { setSelected(t.key); setResult(null); setError('') }}
            className={`rounded-xl border p-3 text-left transition-colors ${selected === t.key ? 'border-violet-400 bg-violet-50' : 'border-gray-100 bg-white hover:border-gray-200'}`}
          >
            <div className="text-sm font-semibold text-gray-900">{t.label}</div>
            <div className="text-xs text-gray-500">{t.departments.length} setores sugeridos</div>
          </button>
        ))}
      </div>
      {current && (
        <Panel>
          <div className="mb-2 text-sm font-medium text-gray-900">Setores de “{current.label}”</div>
          <div className="mb-4 flex flex-wrap gap-2">
            {current.departments.map((d) => <Pill key={d} tone="gray">{d}</Pill>)}
          </div>
          {error && <p className="mb-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
          {result && (
            <p className="mb-3 flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
              <Check className="h-4 w-4" />
              {result.length ? `Criados: ${result.join(', ')}.` : 'Todos esses setores já existiam.'}
            </p>
          )}
          <button type="button" className={btnPrimary} disabled={apply.isPending} onClick={() => apply.mutate()}>
            {apply.isPending ? 'Aplicando…' : 'Aplicar modelo'}
          </button>
        </Panel>
      )}
    </section>
  )
}

export default function WorkspaceAdminPage() {
  const { slug } = useWs()
  return (
    <>
      <PageHeader title="Papéis e estrutura" subtitle="Defina quem pode o quê, as jornadas de trabalho e os setores da empresa." />
      <RolesSection slug={slug} />
      <WorkspaceSchedulesSection slug={slug} />
      <TemplateSection slug={slug} />
    </>
  )
}
