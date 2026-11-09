import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Briefcase, Pencil, Plus, Trash2 } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { CareerEntry, CareerInput, CareerPayload } from '@/types/workspace.types'
import { Empty, Modal, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, inputCls, labelCls } from './ui'

const MONTHS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']
const ym = (d: string) => `${MONTHS[Number(d.slice(5, 7)) - 1]}/${d.slice(0, 4)}`
const year = (d: string) => d.slice(0, 4)

/** "2014 – 2016" (ou "2022 – atual"); anos iguais viram um só. */
function period(e: CareerEntry) {
  if (!e.ended_on) return `${year(e.started_on)} – atual`
  return year(e.started_on) === year(e.ended_on) ? year(e.started_on) : `${year(e.started_on)} – ${year(e.ended_on)}`
}

/** Tempo na função, em anos/meses (ex.: "3 anos e 2 meses"). */
function duration(e: CareerEntry) {
  const from = new Date(e.started_on + 'T12:00:00')
  const to = e.ended_on ? new Date(e.ended_on + 'T12:00:00') : new Date()
  const months = Math.max(1, Math.round((to.getTime() - from.getTime()) / (30.44 * 86_400_000)))
  const y = Math.floor(months / 12)
  const m = months % 12
  return [y ? `${y} ${y === 1 ? 'ano' : 'anos'}` : '', m ? `${m} ${m === 1 ? 'mês' : 'meses'}` : ''].filter(Boolean).join(' e ')
}

function EntryForm({ entry, onSave, onClose, saving, error }: { entry?: CareerEntry; onSave: (b: CareerInput) => void; onClose: () => void; saving: boolean; error: string }) {
  const [f, setF] = useState({ title: entry?.title ?? '', department: entry?.department ?? '', started_on: entry?.started_on ?? '', ended_on: entry?.ended_on ?? '', note: entry?.note ?? '' })
  const set = (k: keyof typeof f, v: string) => setF((x) => ({ ...x, [k]: v }))

  return (
    <Modal title={entry ? 'Editar etapa da carreira' : 'Nova etapa da carreira'} onClose={onClose}>
      <div className="space-y-3">
        <div><label className={labelCls} htmlFor="c-title">Cargo</label><input id="c-title" className={inputCls} maxLength={120} value={f.title} onChange={(e) => set('title', e.target.value)} /></div>
        <div><label className={labelCls} htmlFor="c-dept">Setor (opcional)</label><input id="c-dept" className={inputCls} maxLength={80} value={f.department} onChange={(e) => set('department', e.target.value)} /></div>
        <div className="grid grid-cols-2 gap-3">
          <div><label className={labelCls} htmlFor="c-from">Início</label><input id="c-from" type="date" className={inputCls} value={f.started_on} onChange={(e) => set('started_on', e.target.value)} /></div>
          <div><label className={labelCls} htmlFor="c-to">Fim (vazio = atual)</label><input id="c-to" type="date" className={inputCls} value={f.ended_on} onChange={(e) => set('ended_on', e.target.value)} /></div>
        </div>
        <div><label className={labelCls} htmlFor="c-note">Observação (opcional)</label><input id="c-note" className={inputCls} maxLength={300} value={f.note} onChange={(e) => set('note', e.target.value)} /></div>
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>Cancelar</button>
          <button type="button" className={btnPrimary} disabled={saving || !f.title.trim() || !f.started_on}
            onClick={() => onSave({ title: f.title.trim(), department: f.department.trim() || null, started_on: f.started_on, ended_on: f.ended_on || null, note: f.note.trim() || null })}>Salvar</button>
        </div>
      </div>
    </Modal>
  )
}

/** Linha do tempo da pessoa na empresa. Todos leem; só o RH/dono edita (o servidor confirma). */
export default function ProfileCareer({ slug, uuid, editable = false }: { slug: string; uuid: string; editable?: boolean }) {
  const qc = useQueryClient()
  const key = ['ws', slug, 'career', uuid]
  const { data, isLoading } = useQuery({ queryKey: key, queryFn: () => workspaceService.career(slug, uuid) })
  const [form, setForm] = useState<CareerEntry | 'new' | null>(null)
  const [error, setError] = useState('')
  const done = (r: CareerPayload) => { qc.setQueryData(key, r); setForm(null); setError('') }
  const save = useMutation({
    mutationFn: (b: CareerInput) => (form && form !== 'new' ? workspaceService.updateCareer(slug, uuid, form.uuid, b) : workspaceService.addCareer(slug, uuid, b)),
    onSuccess: done, onError: (e) => setError(errMsg(e)),
  })
  const remove = useMutation({ mutationFn: (entry: string) => workspaceService.deleteCareer(slug, uuid, entry), onSuccess: (r) => qc.setQueryData(key, r) })

  if (isLoading || !data) return <Spinner />
  const canEdit = editable && data.can_edit

  return (
    <Panel>
      <div className="mb-4 flex items-center justify-between gap-3">
        <h2 className="flex items-center gap-2 font-semibold text-gray-900"><Briefcase className="h-4 w-4 text-brand-500" /> Carreira na empresa</h2>
        {canEdit && <button type="button" className={btnGhost} onClick={() => { setError(''); setForm('new') }}><Plus size={14} /> Adicionar etapa</button>}
      </div>

      {!data.items.length ? <Empty text="Ainda não há histórico de carreira registrado." /> : (
        <ol className="relative ml-2 space-y-5 border-l-2 border-gray-100 pl-6">
          {data.items.map((e) => (
            <li key={e.uuid} className="relative">
              <span className={`absolute -left-[33px] top-1.5 h-3.5 w-3.5 rounded-full border-2 border-white ${e.current ? 'bg-brand-600 ring-2 ring-brand-200' : 'bg-gray-300'}`} aria-hidden />
              <div className="flex flex-wrap items-center gap-2">
                <p className="font-semibold text-gray-900">{e.title}</p>
                {e.current && <Pill tone="green">Atual</Pill>}
                {e.source === 'job' && <Pill tone="blue">Vaga interna</Pill>}
              </div>
              <p className="text-sm text-gray-600">{[e.department, period(e)].filter(Boolean).join(' · ')}</p>
              <p className="text-xs text-gray-400" title={`${ym(e.started_on)} – ${e.ended_on ? ym(e.ended_on) : 'atual'}`}>{duration(e)}</p>
              {e.note && <p className="mt-1 text-sm text-gray-500">{e.note}</p>}
              {canEdit && (
                <div className="mt-1 flex gap-3 text-xs text-gray-400">
                  <button type="button" className="inline-flex items-center gap-1 hover:text-gray-700" onClick={() => { setError(''); setForm(e) }}><Pencil size={12} /> Editar</button>
                  <button type="button" className="inline-flex items-center gap-1 hover:text-red-500" onClick={() => { if (confirm('Remover esta etapa do histórico?')) remove.mutate(e.uuid) }}><Trash2 size={12} /> Remover</button>
                </div>
              )}
            </li>
          ))}
        </ol>
      )}
      {form && <EntryForm entry={form === 'new' ? undefined : form} saving={save.isPending} error={error} onSave={(b) => save.mutate(b)} onClose={() => setForm(null)} />}
    </Panel>
  )
}
