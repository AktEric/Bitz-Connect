import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import api from '@/services/api'
import type { ApiResponse } from '@/types/api.types'
import { Empty, Modal, PageHeader, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, fmtDate, inputCls, labelCls, useWs } from './ui'

type QType = 'nps' | 'scale'
type Question = { key: string; label: string; type: QType; min: number; max: number }
type Draft = { label: string; type: QType }
type Survey = { uuid: string; title: string; status: 'open' | 'closed'; closes_on: string | null; answered: boolean; responses: number | null; questions: Question[] }
type Index = { items: Survey[]; can_manage: boolean; can_see_results: boolean; questions: Question[]; min_responses: number }
type Summary = { responses: number; enps: number | null; averages: Record<string, number> }
type Comparison = { title: string; enps: { current: number | null; previous: number | null; delta: number | null }; items: { key: string; label: string; current: number; previous: number; delta: number }[] }
type History = { uuid: string; title: string; enps: number | null; responses: number; current: boolean }
type Results = {
  uuid: string; title: string; questions: Question[]; hidden: boolean; min_responses: number
  participation: { answered: number; eligible: number }; summary: Summary | null; departments: ({ name: string } & Summary)[]
  comments: string[]; comparison: Comparison | null; history: History[]
}

const w = (slug: string) => `/workspaces/${slug}/climate`
const call = {
  index: (slug: string) => api.get<ApiResponse<Index>>(w(slug)).then((r) => r.data.data),
  open: (slug: string, body: { title: string; closes_on: string | null; questions: Draft[] }) => api.post(w(slug), body),
  close: (slug: string, uuid: string) => api.post(`${w(slug)}/${uuid}/close`),
  answer: (slug: string, uuid: string, body: { scores: Record<string, number>; comment: string }) => api.post(`${w(slug)}/${uuid}/answer`, body),
  results: (slug: string, uuid: string) => api.get<ApiResponse<Results>>(`${w(slug)}/${uuid}/results`).then((r) => r.data.data),
}

function AnswerModal({ slug, survey, onClose }: { slug: string; survey: Survey; onClose: () => void }) {
  const qc = useQueryClient()
  const questions = survey.questions
  const [scores, setScores] = useState<Record<string, number>>({})
  const [comment, setComment] = useState('')
  const [error, setError] = useState('')
  const send = useMutation({
    mutationFn: () => call.answer(slug, survey.uuid, { scores, comment: comment.trim() }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'climate'] }); onClose() },
    onError: (e) => setError(errMsg(e)),
  })
  const done = questions.every((q) => scores[q.key] !== undefined)

  return (
    <Modal title={survey.title} onClose={onClose}>
      <p className="mb-3 rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-800">Sua resposta é anônima: não guardamos quem respondeu o quê.</p>
      <div className="space-y-4">
        {questions.map((q) => (
          <fieldset key={q.key}>
            <legend className="mb-1.5 text-sm font-medium text-gray-900">{q.label}</legend>
            <div className="flex flex-wrap gap-1.5">
              {Array.from({ length: q.max - q.min + 1 }, (_, i) => q.min + i).map((v) => (
                <button key={v} type="button" aria-pressed={scores[q.key] === v} onClick={() => setScores((s) => ({ ...s, [q.key]: v }))}
                  className={`h-9 min-w-9 rounded-lg border px-2 text-sm ${scores[q.key] === v ? 'border-violet-600 bg-violet-600 text-white' : 'border-gray-200 text-gray-700 hover:bg-gray-50'}`}>{v}</button>
              ))}
            </div>
            {q.type === 'scale' && <p className="mt-1 text-[11px] text-gray-400">1 = discordo totalmente · 5 = concordo totalmente</p>}
          </fieldset>
        ))}
        <div>
          <label className={labelCls} htmlFor="cl-c">Quer deixar um comentário? (opcional)</label>
          <textarea id="cl-c" rows={3} maxLength={500} className={inputCls} value={comment} onChange={(e) => setComment(e.target.value)} placeholder="Não cite nomes nem dados que identifiquem você." />
        </div>
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>Agora não</button>
          <button type="button" className={btnPrimary} disabled={!done || send.isPending} onClick={() => send.mutate()}>Enviar resposta</button>
        </div>
      </div>
    </Modal>
  )
}

function Delta({ v }: { v: number | null }) {
  if (v === null) return null
  const tone = v > 0 ? 'text-emerald-600' : v < 0 ? 'text-red-600' : 'text-gray-400'
  return <span className={`text-xs font-medium ${tone}`}>{v > 0 ? '▲' : v < 0 ? '▼' : '='} {v > 0 ? '+' : ''}{v}</span>
}

function Block({ s, label }: { s: Summary; label: (k: string) => string }) {
  return (
    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
      {s.enps !== null && <div className="rounded-xl bg-gray-50 p-3"><p className="text-xl font-bold text-gray-900">{s.enps}</p><p className="text-xs text-gray-500">eNPS (−100 a 100)</p></div>}
      {Object.entries(s.averages).map(([k, v]) => <div key={k} className="rounded-xl bg-gray-50 p-3"><p className="text-xl font-bold text-gray-900">{v.toFixed(1)}</p><p className="text-xs leading-tight text-gray-500">{label(k)}</p></div>)}
    </div>
  )
}

function ResultsModal({ slug, uuid, onClose }: { slug: string; uuid: string; onClose: () => void }) {
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'climate', uuid, 'results'], queryFn: () => call.results(slug, uuid) })
  const label = (k: string) => data?.questions.find((q) => q.key === k)?.label ?? k

  return (
    <Modal title={data?.title ?? 'Resultados'} onClose={onClose}>
      {isLoading || !data ? <Spinner /> : (
        <div className="space-y-4 text-sm">
          <p className="text-gray-600">Participação: <b>{data.participation.answered}</b> de {data.participation.eligible} pessoas.</p>
          {data.hidden || !data.summary ? (
            <p className="rounded-xl bg-amber-50 px-3 py-3 text-amber-900">Os resultados aparecem quando houver pelo menos {data.min_responses} respostas, para proteger o anonimato de quem respondeu.</p>
          ) : (
            <>
              <section><h3 className="mb-2 font-semibold text-gray-900">Empresa toda</h3><Block s={data.summary} label={label} /></section>
              {data.comparison && (
                <section>
                  <h3 className="mb-2 font-semibold text-gray-900">Comparado com “{data.comparison.title}”</h3>
                  <ul className="divide-y divide-gray-100 rounded-xl border border-gray-100">
                    {data.comparison.enps.current !== null && data.comparison.enps.previous !== null && (
                      <li className="flex items-center justify-between gap-3 px-3 py-2">
                        <span className="text-gray-700">eNPS</span>
                        <span className="text-gray-500">{data.comparison.enps.previous} → <b className="text-gray-900">{data.comparison.enps.current}</b> <Delta v={data.comparison.enps.delta} /></span>
                      </li>
                    )}
                    {data.comparison.items.map((i) => (
                      <li key={i.key} className="flex items-center justify-between gap-3 px-3 py-2">
                        <span className="min-w-0 flex-1 text-gray-700">{i.label}</span>
                        <span className="shrink-0 text-gray-500">{i.previous.toFixed(1)} → <b className="text-gray-900">{i.current.toFixed(1)}</b> <Delta v={i.delta} /></span>
                      </li>
                    ))}
                  </ul>
                  <p className="mt-1 text-[11px] text-gray-400">Só entram afirmações com o mesmo texto nas duas pesquisas.</p>
                </section>
              )}
              {data.history.length > 1 && (
                <section>
                  <h3 className="mb-2 font-semibold text-gray-900">Evolução do eNPS</h3>
                  <ul className="space-y-1.5">
                    {data.history.map((h) => (
                      <li key={h.uuid} className="flex items-center gap-2">
                        <span className={`w-40 shrink-0 truncate text-xs ${h.current ? 'font-semibold text-gray-900' : 'text-gray-500'}`}>{h.title}</span>
                        <div className="relative h-2 flex-1 rounded-full bg-gray-100" aria-hidden="true">
                          <div className={`absolute top-0 h-2 rounded-full ${h.current ? 'bg-violet-600' : 'bg-violet-300'}`}
                            style={{ left: '50%', width: `${Math.abs(h.enps ?? 0) / 2}%`, transform: (h.enps ?? 0) < 0 ? 'translateX(-100%)' : undefined }} />
                        </div>
                        <span className="w-10 shrink-0 text-right text-xs tabular-nums text-gray-700">{h.enps ?? '—'}</span>
                      </li>
                    ))}
                  </ul>
                </section>
              )}
              {data.departments.map((d) => <section key={d.name}><h3 className="mb-2 font-semibold text-gray-900">{d.name} <span className="text-xs font-normal text-gray-400">({d.responses} respostas)</span></h3><Block s={d} label={label} /></section>)}
              <p className="text-[11px] text-gray-400">Setores com menos de {data.min_responses} respostas não são mostrados separadamente.</p>
              {data.comments.length > 0 && (
                <section><h3 className="mb-2 font-semibold text-gray-900">Comentários (anônimos, em ordem aleatória)</h3>
                  <ul className="space-y-1.5">{data.comments.map((c, i) => <li key={i} className="rounded-xl bg-gray-50 px-3 py-2 text-gray-700">{c}</li>)}</ul></section>
              )}
            </>
          )}
        </div>
      )}
    </Modal>
  )
}

export default function WorkspaceClimatePage() {
  const { slug } = useWs()
  const qc = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'climate'], queryFn: () => call.index(slug) })
  const [answering, setAnswering] = useState<Survey | null>(null)
  const [results, setResults] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)
  const [title, setTitle] = useState('')
  const [closes, setCloses] = useState('')
  const [error, setError] = useState('')
  const [edited, setEdited] = useState<Draft[] | null>(null)
  // enquanto ninguém editou, usa as perguntas padrão devolvidas pelo servidor
  const draft: Draft[] = edited ?? (data?.questions ?? []).map((q) => ({ label: q.label, type: q.type }))
  const editQs = (fn: (cur: Draft[]) => Draft[]) => setEdited(fn(draft))
  const valid = draft.length > 0 && draft.every((q) => q.label.trim().length >= 8) && draft.filter((q) => q.type === 'nps').length <= 1

  const open = useMutation({
    mutationFn: () => call.open(slug, { title: title.trim(), closes_on: closes || null, questions: draft.map((q) => ({ label: q.label.trim(), type: q.type })) }),
    onSuccess: () => { setCreating(false); setTitle(''); setCloses(''); setEdited(null); setError(''); qc.invalidateQueries({ queryKey: ['ws', slug, 'climate'] }) },
    onError: (e) => setError(errMsg(e)),
  })
  const close = useMutation({ mutationFn: (uuid: string) => call.close(slug, uuid), onSuccess: () => qc.invalidateQueries({ queryKey: ['ws', slug, 'climate'] }) })

  return (
    <div>
      <PageHeader
        title="Pesquisa de clima"
        subtitle="Anônima e rápida: ajuda a empresa a saber como o time está."
        actions={data?.can_manage ? <button type="button" className={btnPrimary} onClick={() => setCreating(true)}><Plus size={16} /> Nova pesquisa</button> : undefined}
      />
      {isLoading ? <Spinner /> : !data?.items.length ? <Empty text="Nenhuma pesquisa no momento." /> : (
        <div className="space-y-3">
          {data.items.map((s) => (
            <Panel key={s.uuid} className="flex flex-wrap items-center gap-3">
              <div className="min-w-0 flex-1">
                <p className="font-semibold text-gray-900">{s.title}</p>
                <p className="text-xs text-gray-500">{s.closes_on ? `Até ${fmtDate(s.closes_on)}` : 'Sem data para encerrar'}{s.responses !== null ? ` · ${s.responses} resposta(s)` : ''}</p>
              </div>
              <Pill tone={s.status === 'open' ? 'green' : 'gray'}>{s.status === 'open' ? 'Aberta' : 'Encerrada'}</Pill>
              {s.status === 'open' && (s.answered ? <Pill tone="violet">Você respondeu</Pill> : <button type="button" className={btnPrimary} onClick={() => setAnswering(s)}>Responder</button>)}
              {data.can_see_results && <button type="button" className={btnGhost} onClick={() => setResults(s.uuid)}>Resultados</button>}
              {data.can_manage && s.status === 'open' && <button type="button" className={btnGhost} onClick={() => { if (confirm('Encerrar esta pesquisa? Ninguém mais poderá responder.')) close.mutate(s.uuid) }}>Encerrar</button>}
            </Panel>
          ))}
        </div>
      )}
      {answering && <AnswerModal slug={slug} survey={answering} onClose={() => setAnswering(null)} />}
      {results && <ResultsModal slug={slug} uuid={results} onClose={() => setResults(null)} />}
      {creating && (
        <Modal title="Nova pesquisa de clima" onClose={() => setCreating(false)}>
          <div className="space-y-3">
            <p className="text-sm text-gray-600">Todos serão avisados. Ajuste as perguntas se quiser — para comparar com pesquisas anteriores, mantenha o mesmo texto.</p>
            <div><label className={labelCls} htmlFor="cl-t">Título</label><input id="cl-t" className={inputCls} maxLength={120} value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Ex.: Clima do 4º trimestre" /></div>
            <div><label className={labelCls} htmlFor="cl-e">Encerra em (opcional)</label><input id="cl-e" type="date" className={inputCls} value={closes} onChange={(e) => setCloses(e.target.value)} /></div>
            <fieldset className="space-y-2">
              <legend className={labelCls}>Perguntas</legend>
              {draft.map((q, i) => (
                <div key={i} className="flex items-start gap-2">
                  <textarea rows={2} maxLength={160} aria-label={`Pergunta ${i + 1}`} className={`${inputCls} flex-1`} value={q.label}
                    onChange={(e) => editQs((c) => c.map((x, j) => (j === i ? { ...x, label: e.target.value } : x)))} />
                  <select aria-label={`Tipo da pergunta ${i + 1}`} className="rounded-xl border border-gray-200 bg-white px-2 py-2 text-xs text-gray-900" value={q.type}
                    onChange={(e) => editQs((c) => c.map((x, j) => (j === i ? { ...x, type: e.target.value as QType } : x)))}>
                    <option value="scale">Concordo 1–5</option>
                    <option value="nps">Indicação 0–10</option>
                  </select>
                  <button type="button" aria-label={`Remover pergunta ${i + 1}`} className="rounded-lg px-2 py-1.5 text-gray-400 hover:bg-gray-100 hover:text-red-600"
                    onClick={() => editQs((c) => c.filter((_, j) => j !== i))}>✕</button>
                </div>
              ))}
              {draft.length < 8 && <button type="button" className={btnGhost} onClick={() => editQs((c) => [...c, { label: '', type: 'scale' }])}><Plus size={14} /> Adicionar pergunta</button>}
              {!valid && <p className="text-xs text-amber-700">Cada pergunta precisa de pelo menos 8 letras, e só pode haver uma de indicação (0–10).</p>}
            </fieldset>
            {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
            <div className="flex justify-end gap-2">
              <button type="button" className={btnGhost} onClick={() => setCreating(false)}>Cancelar</button>
              <button type="button" className={btnPrimary} disabled={title.trim().length < 4 || !valid || open.isPending} onClick={() => open.mutate()}>Abrir pesquisa</button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  )
}
