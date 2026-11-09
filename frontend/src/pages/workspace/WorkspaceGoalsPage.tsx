import { useCan } from './permissions'
import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { Goal, GoalStatus } from '@/types/workspace.types'
import { Empty, Modal, PageHeader, Panel, Pill, Spinner, Tabs, btnGhost, btnPrimary, errMsg, fmtDate, inputCls, labelCls, useWs } from './ui'

const STATUS: Record<GoalStatus, { label: string; tone: 'brand' | 'green' | 'red' | 'gray' }> = {
  active: { label: 'Em andamento', tone: 'brand' }, achieved: { label: 'Batida', tone: 'green' },
  missed: { label: 'Não batida', tone: 'red' }, cancelled: { label: 'Cancelada', tone: 'gray' },
}
const BAR: Record<GoalStatus, string> = { active: 'bg-brand-500', achieved: 'bg-emerald-500', missed: 'bg-red-400', cancelled: 'bg-gray-300' }

function NewGoalModal({ slug, onClose }: { slug: string; onClose: () => void }) {
  const qc = useQueryClient()
  const { data: members } = useQuery({ queryKey: ['ws', slug, 'members'], queryFn: () => workspaceService.members(slug) })
  const today = new Date().toISOString().slice(0, 10)
  const [owner, setOwner] = useState('')
  const [title, setTitle] = useState('')
  const [target, setTarget] = useState('')
  const [unit, setUnit] = useState('%')
  const [start, setStart] = useState(today)
  const [end, setEnd] = useState('')
  const [error, setError] = useState('')

  const create = useMutation({
    mutationFn: () => workspaceService.createGoal(slug, { owner_uuid: owner, title: title.trim(), unit: unit.trim() || '%', target_value: Number(target.replace(',', '.')), period_start: start, period_end: end }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'goals'] }); onClose() },
    onError: (e) => setError(errMsg(e)),
  })

  return (
    <Modal title="Nova meta" onClose={onClose}>
      <div className="space-y-3">
        <div>
          <label className={labelCls}>Responsável</label>
          <select className={inputCls} value={owner} onChange={(e) => setOwner(e.target.value)}>
            <option value="">Selecione…</option>
            {members?.map((m) => <option key={m.uuid} value={m.uuid}>{m.name}</option>)}
          </select>
        </div>
        <div><label className={labelCls}>Meta</label><input className={inputCls} value={title} onChange={(e) => setTitle(e.target.value)} maxLength={160} placeholder="Ex.: Satisfação no check-in" /></div>
        <div className="grid grid-cols-2 gap-3">
          <div><label className={labelCls}>Alvo</label><input className={inputCls} inputMode="decimal" value={target} onChange={(e) => setTarget(e.target.value)} /></div>
          <div><label className={labelCls}>Unidade</label><input className={inputCls} value={unit} onChange={(e) => setUnit(e.target.value)} maxLength={30} /></div>
          <div><label className={labelCls}>Início</label><input type="date" className={inputCls} value={start} onChange={(e) => setStart(e.target.value)} /></div>
          <div><label className={labelCls}>Prazo</label><input type="date" className={inputCls} value={end} onChange={(e) => setEnd(e.target.value)} /></div>
        </div>
        {error && <p className="text-sm text-red-600">{error}</p>}
        <div className="flex justify-end gap-2">
          <button className={btnGhost} onClick={onClose}>Cancelar</button>
          <button className={btnPrimary} disabled={!owner || !title.trim() || !target || !end || create.isPending} onClick={() => create.mutate()}>Criar</button>
        </div>
      </div>
    </Modal>
  )
}

function GoalCard({ slug, goal }: { slug: string; goal: Goal }) {
  const qc = useQueryClient()
  const [editing, setEditing] = useState(false)
  const [value, setValue] = useState(String(goal.current_value))
  const [note, setNote] = useState('')
  const [error, setError] = useState('')
  const st = STATUS[goal.status]
  const save = useMutation({
    mutationFn: () => workspaceService.addGoalProgress(slug, goal.uuid, Number(value.replace(',', '.')), note.trim() || undefined),
    onSuccess: () => { setEditing(false); setNote(''); setError(''); qc.invalidateQueries({ queryKey: ['ws', slug, 'goals'] }) },
    onError: (e) => setError(errMsg(e)),
  })

  return (
    <Panel>
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="font-semibold text-gray-900">{goal.title}</p>
          {goal.owner && (
            <p className="mt-0.5 flex items-center gap-1.5 text-xs text-gray-400"><Avatar src={goal.owner.avatar_url} name={goal.owner.name} size="xs" /> {goal.owner.name}</p>
          )}
        </div>
        <Pill tone={st.tone}>{st.label}</Pill>
      </div>
      <div className="mt-3 flex justify-between text-sm text-gray-600">
        <span>{goal.current_value} / {goal.target_value} {goal.unit}</span>
        <span className="font-medium">{Math.round(goal.progress)}%</span>
      </div>
      <div className="mt-1 h-2 overflow-hidden rounded-full bg-gray-100">
        <div className={`h-full rounded-full ${BAR[goal.status]}`} style={{ width: `${Math.min(100, goal.progress)}%` }} />
      </div>
      <p className="mt-1 text-xs text-gray-400">{fmtDate(goal.period_start)} → {fmtDate(goal.period_end)}</p>
      {goal.status !== 'cancelled' && (editing ? (
        <div className="mt-3 space-y-2">
          <div className="flex gap-2">
            <input className={inputCls} inputMode="decimal" value={value} onChange={(e) => setValue(e.target.value)} aria-label="Valor atual" />
            <input className={inputCls} value={note} onChange={(e) => setNote(e.target.value)} placeholder="Observação" maxLength={500} />
          </div>
          {error && <p className="text-sm text-red-600">{error}</p>}
          <div className="flex justify-end gap-2">
            <button className={btnGhost} onClick={() => setEditing(false)}>Cancelar</button>
            <button className={btnPrimary} disabled={save.isPending} onClick={() => save.mutate()}>Salvar</button>
          </div>
        </div>
      ) : (
        <button className={`${btnGhost} mt-3 w-full`} onClick={() => setEditing(true)}>Atualizar progresso</button>
      ))}
    </Panel>
  )
}

export default function WorkspaceGoalsPage() {
  const { slug } = useWs()
  const canManage = useCan('goals.manage')
  const [scope, setScope] = useState<'mine' | 'team'>('mine')
  const [creating, setCreating] = useState(false)

  const { data: goals, isLoading } = useQuery({ queryKey: ['ws', slug, 'goals', scope], queryFn: () => workspaceService.goals(slug, { scope }) })
  const { data: summary } = useQuery({ queryKey: ['ws', slug, 'goals-summary'], queryFn: () => workspaceService.goalsSummary(slug), enabled: canManage && scope === 'team' })

  return (
    <div>
      <PageHeader
        title="Metas"
        subtitle="Acompanhe o que foi batido e o que ainda está em andamento."
        actions={canManage ? <button className={btnPrimary} onClick={() => setCreating(true)}><Plus className="h-4 w-4" /> Nova meta</button> : undefined}
      />
      {canManage && <Tabs value={scope} onChange={setScope} options={[{ value: 'mine', label: 'Minhas' }, { value: 'team', label: 'Equipe' }]} />}

      {scope === 'team' && !!summary?.length && (
        <Panel className="mb-4 overflow-x-auto">
          <table className="w-full min-w-[480px] text-sm">
            <thead><tr className="text-left text-xs text-gray-400"><th className="pb-2 font-medium">Pessoa</th><th className="pb-2 text-center font-medium">Batidas</th><th className="pb-2 text-center font-medium">Não batidas</th><th className="pb-2 text-center font-medium">Em andamento</th><th className="pb-2 text-right font-medium">Média</th></tr></thead>
            <tbody>
              {summary.map((r) => (
                <tr key={r.person.uuid} className="border-t border-gray-50">
                  <td className="py-2"><span className="flex items-center gap-2"><Avatar src={r.person.avatar_url} name={r.person.name} size="xs" />{r.person.name}</span></td>
                  <td className="text-center text-emerald-600">{r.achieved}</td>
                  <td className="text-center text-red-500">{r.missed}</td>
                  <td className="text-center text-gray-600">{r.active}</td>
                  <td className="text-right font-medium">{r.avg_progress}%</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Panel>
      )}

      {isLoading ? <Spinner /> : !goals?.length ? <Empty text={scope === 'team' ? 'Sua equipe ainda não tem metas.' : 'Você não tem metas no momento.'} /> : (
        <div className="grid gap-3 md:grid-cols-2">{goals.map((g) => <GoalCard key={g.uuid} slug={slug} goal={g} />)}</div>
      )}

      {creating && <NewGoalModal slug={slug} onClose={() => setCreating(false)} />}
    </div>
  )
}
