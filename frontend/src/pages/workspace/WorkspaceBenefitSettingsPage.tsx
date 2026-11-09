import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { BenefitColor, BenefitPackage, BenefitTypeRow } from '@/types/benefits.types'
import { cn } from '@/utils/cn'
import { BENEFIT_COLORS } from './benefits/benefitColors'
import { Modal, PageHeader, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, fmtMoney, inputCls, labelCls, useWs } from './ui'

function ColorPicker({ value, colors, onChange }: { value: BenefitColor; colors: BenefitColor[]; onChange: (c: BenefitColor) => void }) {
  return (
    <div role="radiogroup" aria-label="Cor" className="flex flex-wrap gap-1.5">
      {colors.map((c) => (
        <button key={c} type="button" role="radio" aria-checked={value === c} aria-label={BENEFIT_COLORS[c].label} onClick={() => onChange(c)}
          className={cn('h-7 w-7 rounded-lg ring-offset-2', BENEFIT_COLORS[c].tile, value === c ? 'ring-2 ring-brand-500' : 'opacity-80 hover:opacity-100')} />
      ))}
    </div>
  )
}

function TypesPanel({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'benefit-types'], queryFn: () => workspaceService.benefitTypes(slug) })
  const [name, setName] = useState('')
  const [color, setColor] = useState<BenefitColor>('green')
  const [error, setError] = useState('')
  const sync = (d: Awaited<ReturnType<typeof workspaceService.benefitTypes>>) => { qc.setQueryData(['ws', slug, 'benefit-types'], d); setError('') }
  const create = useMutation({ mutationFn: () => workspaceService.createBenefitType(slug, { name: name.trim(), color }), onSuccess: (d) => { sync(d); setName('') }, onError: (e) => setError(errMsg(e)) })
  const update = useMutation({ mutationFn: ({ t, body }: { t: BenefitTypeRow; body: { name?: string; color?: string; active?: boolean } }) => workspaceService.updateBenefitType(slug, t.uuid, body), onSuccess: sync, onError: (e) => setError(errMsg(e)) })

  return (
    <Panel>
      <h2 className="mb-3 font-semibold text-gray-900">Tipos de benefício</h2>
      {isLoading || !data ? <Spinner /> : (
        <>
          <ul className="divide-y divide-gray-50">
            {data.types.map((t) => (
              <li key={t.uuid} className={cn('flex flex-wrap items-center gap-3 py-2.5', !t.active && 'opacity-60')}>
                <input aria-label={`Nome: ${t.name}`} className={`${inputCls} !w-52`} defaultValue={t.name} maxLength={60} onBlur={(e) => { const v = e.target.value.trim(); if (v && v !== t.name) update.mutate({ t, body: { name: v } }) }} />
                <ColorPicker value={t.color} colors={data.colors} onChange={(c) => update.mutate({ t, body: { color: c } })} />
                <label className="ml-auto flex cursor-pointer items-center gap-2 text-sm text-gray-600"><input type="checkbox" className="rounded border-gray-300 text-brand-600" checked={t.active} onChange={(e) => update.mutate({ t, body: { active: e.target.checked } })} /> Ativo</label>
              </li>
            ))}
          </ul>
          <div className="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
            <div><label className={labelCls} htmlFor="bt-name">Novo tipo</label><input id="bt-name" className={`${inputCls} !w-52`} value={name} maxLength={60} onChange={(e) => setName(e.target.value)} placeholder="Ex.: Auxílio home office" /></div>
            <div><span className={labelCls}>Cor</span><ColorPicker value={color} colors={data.colors} onChange={setColor} /></div>
            <button type="button" className={btnPrimary} disabled={name.trim().length < 2 || create.isPending} onClick={() => create.mutate()}><Plus className="h-4 w-4" /> Criar</button>
          </div>
          {error && <p className="mt-2 text-sm text-red-600" role="alert">{error}</p>}
          <p className="mt-3 text-xs text-gray-400">Tipos nunca são apagados: desative para esconder de novos cadastros sem perder o histórico de quem já recebeu.</p>
        </>
      )}
    </Panel>
  )
}

type Row = { type: string; amount: string; day: string }

function PackageForm({ slug, pkg, types, onClose }: { slug: string; pkg?: BenefitPackage; types: BenefitTypeRow[]; onClose: () => void }) {
  const qc = useQueryClient()
  const [name, setName] = useState(pkg?.name ?? '')
  const [rows, setRows] = useState<Row[]>(pkg?.items.map((i) => ({ type: i.benefit_type_uuid, amount: i.amount_cents != null ? (i.amount_cents / 100).toFixed(2).replace('.', ',') : '', day: i.deposit_day?.toString() ?? '' })) ?? [{ type: '', amount: '', day: '' }])
  const [error, setError] = useState('')
  const setRow = (i: number, p: Partial<Row>) => setRows((r) => r.map((x, k) => (k === i ? { ...x, ...p } : x)))
  const body = () => ({ name: name.trim(), items: rows.filter((r) => r.type).map((r) => ({ benefit_type_uuid: r.type, amount: r.amount.trim() ? Number(r.amount.replace(/\./g, '').replace(',', '.')) : null, deposit_day: r.day ? Number(r.day) : null })) })
  const save = useMutation({
    mutationFn: () => (pkg ? workspaceService.updateBenefitPackage(slug, pkg.uuid, body()) : workspaceService.createBenefitPackage(slug, body())),
    onSuccess: (d) => { qc.setQueryData(['ws', slug, 'benefit-packages'], d); onClose() }, onError: (e) => setError(errMsg(e)),
  })
  const ok = name.trim().length >= 2 && rows.some((r) => r.type) && rows.every((r) => !r.type || (!Number.isNaN(Number(r.amount.replace(/\./g, '').replace(',', '.'))) && (!r.day || (Number(r.day) >= 1 && Number(r.day) <= 31))))

  return (
    <Modal title={pkg ? 'Editar pacote' : 'Novo pacote de benefícios'} onClose={onClose}>
      <div className="space-y-3">
        <div><label className={labelCls} htmlFor="pk-name">Nome do pacote</label><input id="pk-name" className={inputCls} value={name} maxLength={80} onChange={(e) => setName(e.target.value)} placeholder="Ex.: Operacional CLT" /></div>
        <div className="space-y-2">
          {rows.map((r, i) => (
            <div key={i} className="grid grid-cols-[1fr_6.5rem_4.5rem_auto] items-end gap-2">
              <div>{i === 0 && <span className={labelCls}>Benefício</span>}<select aria-label="Benefício" className={inputCls} value={r.type} onChange={(e) => setRow(i, { type: e.target.value })}><option value="">Selecione…</option>{types.filter((t) => t.active).map((t) => <option key={t.uuid} value={t.uuid}>{t.name}</option>)}</select></div>
              <div>{i === 0 && <span className={labelCls}>Valor (R$)</span>}<input aria-label="Valor" inputMode="decimal" className={inputCls} value={r.amount} onChange={(e) => setRow(i, { amount: e.target.value })} placeholder="0,00" /></div>
              <div>{i === 0 && <span className={labelCls}>Dia</span>}<input aria-label="Dia do depósito" type="number" min={1} max={31} className={inputCls} value={r.day} onChange={(e) => setRow(i, { day: e.target.value })} /></div>
              <button type="button" className="mb-1 rounded p-1.5 text-gray-400 hover:text-red-500" onClick={() => setRows((x) => x.filter((_, k) => k !== i))} disabled={rows.length === 1} aria-label="Remover linha"><Trash2 className="h-4 w-4" /></button>
            </div>
          ))}
        </div>
        <button type="button" className={btnGhost} onClick={() => setRows((r) => [...r, { type: '', amount: '', day: '' }])} disabled={rows.length >= 12}><Plus className="h-4 w-4" /> Adicionar benefício ao pacote</button>
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2"><button type="button" className={btnGhost} onClick={onClose}>Cancelar</button><button type="button" className={btnPrimary} disabled={!ok || save.isPending} onClick={() => save.mutate()}>Salvar pacote</button></div>
      </div>
    </Modal>
  )
}

function PackagesPanel({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const { data: types } = useQuery({ queryKey: ['ws', slug, 'benefit-types'], queryFn: () => workspaceService.benefitTypes(slug) })
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'benefit-packages'], queryFn: () => workspaceService.benefitPackages(slug) })
  const [form, setForm] = useState<{ pkg?: BenefitPackage } | null>(null)
  const remove = useMutation({ mutationFn: (uuid: string) => workspaceService.deleteBenefitPackage(slug, uuid), onSuccess: (d) => qc.setQueryData(['ws', slug, 'benefit-packages'], d) })

  return (
    <Panel>
      <div className="mb-3 flex items-center justify-between gap-3">
        <h2 className="font-semibold text-gray-900">Pacotes de benefícios</h2>
        <button type="button" className={btnPrimary} onClick={() => setForm({})} disabled={!types}><Plus className="h-4 w-4" /> Novo pacote</button>
      </div>
      {isLoading || !data ? <Spinner /> : !data.packages.length ? <p className="text-sm text-gray-400">Nenhum pacote ainda. Crie um para aplicar vários benefícios de uma vez, no convite ou no cadastro da pessoa.</p> : (
        <ul className="space-y-3">
          {data.packages.map((p) => (
            <li key={p.uuid} className="rounded-xl border border-gray-100 p-3">
              <div className="mb-2 flex items-center gap-2"><h3 className="font-medium text-gray-900">{p.name}</h3>
                <span className="ml-auto flex gap-3 text-xs">
                  <button type="button" className="inline-flex items-center gap-1 text-gray-500 hover:text-brand-700" onClick={() => setForm({ pkg: p })}><Pencil size={13} /> Editar</button>
                  <button type="button" className="inline-flex items-center gap-1 text-gray-400 hover:text-red-500" onClick={() => { if (confirm(`Remover o pacote ${p.name}?`)) remove.mutate(p.uuid) }}><Trash2 size={13} /> Remover</button>
                </span></div>
              <div className="flex flex-wrap gap-2">{p.items.map((i) => <span key={i.benefit_type_uuid} className={cn('inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium text-white', BENEFIT_COLORS[i.color]?.tile ?? 'bg-slate-600')}>{i.name}{i.amount_cents != null && ` · ${fmtMoney(i.amount_cents)}`}{i.deposit_day && ` · dia ${i.deposit_day}`}</span>)}</div>
            </li>
          ))}
        </ul>
      )}
      {form && types && <PackageForm slug={slug} pkg={form.pkg} types={types.types} onClose={() => setForm(null)} />}
    </Panel>
  )
}

/** Configuração dos benefícios da empresa (dono/RH): tipos (nome e cor) e pacotes. */
export default function WorkspaceBenefitSettingsPage() {
  const { slug } = useWs()
  return (
    <div className="space-y-5">
      <PageHeader title="Benefícios da empresa" subtitle="Tipos de benefício (com a cor do quadrado) e pacotes prontos para aplicar a quem entra." actions={<Link to={`/w/${slug}/cadastro`} className={btnGhost}><ArrowLeft className="h-4 w-4" /> Cadastro de pessoas</Link>} />
      <p className="flex items-center gap-2 text-sm text-gray-500"><Pill tone="brand">Dica</Pill> Os benefícios de cada pessoa são cadastrados em Cadastro de pessoas → aba Benefícios.</p>
      <TypesPanel slug={slug} />
      <PackagesPanel slug={slug} />
    </div>
  )
}
