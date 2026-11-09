import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Package, Pencil, Plus, Settings2, Trash2 } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { BenefitInput, BenefitItem, BenefitList } from '@/types/benefits.types'
import { Empty, Modal, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, fmtDate, fmtMoney, inputCls, labelCls, useWs } from '../ui'
import { BENEFIT_COLORS } from './benefitColors'
import { depositText } from './BenefitTile'

const parseMoney = (v: string) => (v.trim() === '' ? null : Number(v.replace(/\./g, '').replace(',', '.')))

function BenefitForm({ slug, uuid, benefit, onClose, onSaved }: { slug: string; uuid: string; benefit?: BenefitItem; onClose: () => void; onSaved: (l: BenefitList) => void }) {
  const { data: catalog } = useQuery({ queryKey: ['ws', slug, 'benefit-types'], queryFn: () => workspaceService.benefitTypes(slug) })
  const [f, setF] = useState({
    type: benefit?.type.uuid ?? '', amount: benefit?.amount_cents != null ? (benefit.amount_cents / 100).toFixed(2).replace('.', ',') : '',
    frequency: benefit?.frequency ?? 'monthly' as 'monthly' | 'one_time', day: benefit?.deposit_day?.toString() ?? '', on: benefit?.deposit_on ?? '',
    starts: benefit?.starts_on ?? '', ends: benefit?.ends_on ?? '', note: benefit?.note ?? '',
  })
  const [error, setError] = useState('')
  const set = <K extends keyof typeof f>(k: K, v: (typeof f)[K]) => setF((x) => ({ ...x, [k]: v }))
  const amount = parseMoney(f.amount)
  const bad = (amount !== null && Number.isNaN(amount)) || !f.type || (f.frequency === 'monthly' ? !f.day || Number(f.day) < 1 || Number(f.day) > 31 : !f.on)
  const types = (catalog?.types ?? []).filter((t) => t.active || t.uuid === benefit?.type.uuid)

  const save = useMutation({
    mutationFn: () => {
      const body: BenefitInput = {
        benefit_type_uuid: f.type, amount, frequency: f.frequency, deposit_day: f.frequency === 'monthly' ? Number(f.day) : null,
        deposit_on: f.frequency === 'one_time' ? f.on : null, starts_on: f.starts || null, ends_on: f.ends || null, note: f.note.trim() || null,
      }
      return benefit ? workspaceService.updateBenefit(slug, uuid, benefit.uuid, body) : workspaceService.addBenefit(slug, uuid, body)
    },
    onSuccess: onSaved, onError: (e) => setError(errMsg(e)),
  })

  return (
    <Modal title={benefit ? 'Editar benefício' : 'Adicionar benefício'} onClose={onClose}>
      <div className="space-y-3">
        <div><label className={labelCls} htmlFor="b-type">Benefício</label>
          <select id="b-type" className={inputCls} value={f.type} onChange={(e) => set('type', e.target.value)}><option value="">Selecione…</option>{types.map((t) => <option key={t.uuid} value={t.uuid}>{t.name}</option>)}</select></div>
        <div className="grid grid-cols-2 gap-3">
          <div><label className={labelCls} htmlFor="b-amount">Valor (R$) — vazio = sem valor</label><input id="b-amount" inputMode="decimal" className={inputCls} value={f.amount} onChange={(e) => set('amount', e.target.value)} placeholder="0,00" /></div>
          <div><label className={labelCls} htmlFor="b-freq">Frequência</label>
            <select id="b-freq" className={inputCls} value={f.frequency} onChange={(e) => set('frequency', e.target.value as 'monthly' | 'one_time')}><option value="monthly">Mensal</option><option value="one_time">Pagamento único</option></select></div>
          {f.frequency === 'monthly'
            ? <div><label className={labelCls} htmlFor="b-day">Dia do depósito (1–31)</label><input id="b-day" type="number" min={1} max={31} className={inputCls} value={f.day} onChange={(e) => set('day', e.target.value)} /></div>
            : <div><label className={labelCls} htmlFor="b-on">Data do depósito</label><input id="b-on" type="date" className={inputCls} value={f.on} onChange={(e) => set('on', e.target.value)} /></div>}
          <div />
          <div><label className={labelCls} htmlFor="b-from">Vigente desde (opcional)</label><input id="b-from" type="date" className={inputCls} value={f.starts} onChange={(e) => set('starts', e.target.value)} /></div>
          <div><label className={labelCls} htmlFor="b-to">Até (vazio = sem fim)</label><input id="b-to" type="date" className={inputCls} value={f.ends} onChange={(e) => set('ends', e.target.value)} /></div>
        </div>
        <div><label className={labelCls} htmlFor="b-note">Observação (aparece para a pessoa)</label><input id="b-note" className={inputCls} maxLength={200} value={f.note} onChange={(e) => set('note', e.target.value)} placeholder="Ex.: Unimed Nacional, cobertura familiar" /></div>
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2"><button type="button" className={btnGhost} onClick={onClose}>Cancelar</button><button type="button" className={btnPrimary} disabled={bad || save.isPending} onClick={() => save.mutate()}>Salvar</button></div>
      </div>
    </Modal>
  )
}

/** Aba Benefícios do Cadastro de pessoas (só dono/RH): tabela por pessoa, com pacotes. */
export default function PersonBenefits({ uuid }: { uuid: string }) {
  const { slug } = useWs()
  const qc = useQueryClient()
  const key = ['ws', slug, 'benefits', 'person', uuid]
  const { data, isLoading } = useQuery({ queryKey: key, queryFn: () => workspaceService.personBenefits(slug, uuid) })
  const { data: pk } = useQuery({ queryKey: ['ws', slug, 'benefit-packages'], queryFn: () => workspaceService.benefitPackages(slug) })
  const [form, setForm] = useState<{ b?: BenefitItem } | null>(null)
  const [pkg, setPkg] = useState('')
  const [msg, setMsg] = useState('')
  const done = (l: BenefitList) => { qc.setQueryData(key, l); setForm(null); setMsg('') }
  const remove = useMutation({ mutationFn: (id: string) => workspaceService.deleteBenefit(slug, uuid, id), onSuccess: done })
  const apply = useMutation({ mutationFn: () => workspaceService.applyBenefitPackage(slug, uuid, pkg), onSuccess: (l) => { qc.setQueryData(key, l); setMsg('Pacote aplicado.'); setPkg('') }, onError: (e) => setMsg(errMsg(e)) })

  if (isLoading || !data) return <Spinner />

  return (
    <Panel>
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <h3 className="mr-auto text-sm font-semibold text-gray-900">Benefícios da pessoa {data.total_monthly_cents > 0 && <span className="ml-2 font-normal text-gray-500">Total mensal: <b className="text-gray-900">{fmtMoney(data.total_monthly_cents)}</b></span>}</h3>
        {!!pk?.packages.length && (
          <span className="inline-flex items-center gap-1.5">
            <select aria-label="Pacote de benefícios" className={`${inputCls} !w-auto !py-1.5`} value={pkg} onChange={(e) => setPkg(e.target.value)}><option value="">Aplicar pacote…</option>{pk.packages.map((p) => <option key={p.uuid} value={p.uuid}>{p.name}</option>)}</select>
            <button type="button" className={btnGhost} disabled={!pkg || apply.isPending} onClick={() => apply.mutate()}><Package className="h-4 w-4" /> Aplicar</button>
          </span>
        )}
        <button type="button" className={btnPrimary} onClick={() => setForm({})}><Plus className="h-4 w-4" /> Adicionar</button>
        <Link to={`/w/${slug}/configuracoes/beneficios`} className={btnGhost}><Settings2 className="h-4 w-4" /> Tipos e pacotes</Link>
      </div>
      {msg && <p className="mb-2 text-sm text-gray-500" role="status">{msg}</p>}

      {!data.items.length ? <Empty text="Nenhum benefício cadastrado para esta pessoa." /> : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[40rem] text-sm">
            <thead><tr className="border-b border-gray-100 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
              <th scope="col" className="py-2 pr-3">Benefício</th><th scope="col" className="px-3 py-2">Valor</th><th scope="col" className="px-3 py-2">Depósito</th><th scope="col" className="px-3 py-2">Vigência</th><th scope="col" className="py-2 pl-3 text-right">Ações</th>
            </tr></thead>
            <tbody className="divide-y divide-gray-50">
              {data.items.map((b) => (
                <tr key={b.uuid} className={b.active ? '' : 'opacity-60'}>
                  <td className="py-2.5 pr-3 font-medium text-gray-900"><span className="flex items-center gap-2"><span className={`h-3 w-3 rounded-sm ${BENEFIT_COLORS[b.type.color]?.dot ?? 'bg-gray-400'}`} aria-hidden />{b.type.name}{!b.active && <Pill tone="gray">Encerrado</Pill>}</span>{b.note && <span className="block pl-5 text-xs font-normal text-gray-400">{b.note}</span>}</td>
                  <td className="whitespace-nowrap px-3 py-2.5 tabular-nums text-gray-700">{b.amount_cents == null ? '—' : fmtMoney(b.amount_cents)}</td>
                  <td className="whitespace-nowrap px-3 py-2.5 text-gray-600">{depositText(b)}{b.active && b.next_deposit_on && <span className="block text-xs text-gray-400">próximo {fmtDate(b.next_deposit_on)}</span>}</td>
                  <td className="whitespace-nowrap px-3 py-2.5 text-gray-600">{b.starts_on ? fmtDate(b.starts_on) : '—'} → {b.ends_on ? fmtDate(b.ends_on) : 'sem fim'}</td>
                  <td className="whitespace-nowrap py-2.5 pl-3 text-right">
                    <button type="button" className="mr-3 inline-flex items-center gap-1 text-gray-500 hover:text-brand-700" onClick={() => setForm({ b })}><Pencil size={13} /> Editar</button>
                    <button type="button" className="inline-flex items-center gap-1 text-gray-400 hover:text-red-500" onClick={() => { if (confirm(`Remover ${b.type.name}?`)) remove.mutate(b.uuid) }}><Trash2 size={13} /> Remover</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <p className="mt-3 text-xs text-gray-400">A pessoa vê estes benefícios em “Meus benefícios”, e só os dela. Cada abertura desta aba fica na auditoria.</p>
      {form && <BenefitForm slug={slug} uuid={uuid} benefit={form.b} onClose={() => setForm(null)} onSaved={done} />}
    </Panel>
  )
}
