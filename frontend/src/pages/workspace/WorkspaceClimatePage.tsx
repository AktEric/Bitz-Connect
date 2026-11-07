import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import api from '@/services/api'
import type { ApiResponse } from '@/types/api.types'
import { Empty, Modal, PageHeader, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, fmtDate, inputCls, labelCls, useWs } from './ui'

type Question = { key: string; label: string; min: number; max: number }
type Survey = { uuid: string; title: string; status: 'open' | 'closed'; closes_on: string | null; answered: boolean; responses: number | null }
type Index = { items: Survey[]; can_manage: boolean; can_see_results: boolean; questions: Question[]; min_responses: number }
type Summary = { responses: number; enps: number | null; averages: Record<string, number> }
type Results = { uuid: string; title: string; hidden: boolean; min_responses: number; participation: { answered: number; eligible: number }; summary: Summary | null; departments: ({ name: string } & Summary)[]; comments: string[] }

const w = (slug: string) => `/workspaces/${slug}/climate`
const call = {
  index: (slug: string) => api.get<ApiResponse<Index>>(w(slug)).then((r) => r.data.data),
  open: (slug: string, body: { title: string; closes_on: string | null }) => api.post(w(slug), body),
  close: (slug: string, uuid: string) => api.post(`${w(slug)}/${uuid}/close`),
  answer: (slug: string, uuid: string, body: { scores: Record<string, number>; comment: string }) => api.post(`${w(slug)}/${uuid}/answer`, body),
  results: (slug: string, uuid: string) => api.get<ApiResponse<Results>>(`${w(slug)}/${uuid}/results`).then((r) => r.data.data),
}

function AnswerModal({ slug, survey, questions, onClose }: { slug: string; survey: Survey; questions: Question[]; onClose: () => void }) {
  const qc = useQueryClient()
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
            {q.max === 5 && <p className="mt-1 text-[11px] text-gray-400">1 = discordo totalmente · 5 = concordo totalmente</p>}
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

function ResultsModal({ slug, uuid, questions, onClose }: { slug: string; uuid: string; questions: Question[]; onClose: () => void }) {
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'climate', uuid, 'results'], queryFn: () => call.results(slug, uuid) })
  const label = (k: string) => questions.find((q) => q.key === k)?.label ?? k

  const Block = ({ s }: { s: Summary }) => (
    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
      <div className="rounded-xl bg-gray-50 p-3"><p className="text-xl font-bold text-gray-900">{s.enps ?? '—'}</p><p className="text-xs text-gray-500">eNPS (−100 a 100)</p></div>
      {Object.entries(s.averages).map(([k, v]) => <div key={k} className="rounded-xl bg-gray-50 p-3"><p className="text-xl font-bold text-gray-900">{v.toFixed(1)}</p><p className="text-xs leading-tight text-gray-500">{label(k)}</p></div>)}
    </div>
  )

  return (
    <Modal title={data?.title ?? 'Resultados'} onClose={onClose}>
      {isLoading || !data ? <Spinner /> : (
        <div className="space-y-4 text-sm">
          <p className="text-gray-600">Participação: <b>{data.participation.answered}</b> de {data.participation.eligible} pessoas.</p>
          {data.hidden || !data.summary ? (
            <p className="rounded-xl bg-amber-50 px-3 py-3 text-amber-900">Os resultados aparecem quando houver pelo menos {data.min_responses} respostas, para proteger o anonimato de quem respondeu.</p>
          ) : (
            <>
              <section><h3 className="mb-2 font-semibold text-gray-900">Empresa toda</h3><Block s={data.summary} /></section>
              {data.departments.map((d) => <section key={d.name}><h3 className="mb-2 font-semibold text-gray-900">{d.name} <span className="text-xs font-normal text-gray-400">({d.responses} respostas)</span></h3><Block s={d} /></section>)}
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
  const open = useMutation({
    mutationFn: () => call.open(slug, { title: title.trim(), closes_on: closes || null }),
    onSuccess: () => { setCreating(false); setTitle(''); setCloses(''); setError(''); qc.invalidateQueries({ queryKey: ['ws', slug, 'climate'] }) },
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
      {answering && data && <AnswerModal slug={slug} survey={answering} questions={data.questions} onClose={() => setAnswering(null)} />}
      {results && data && <ResultsModal slug={slug} uuid={results} questions={data.questions} onClose={() => setResults(null)} />}
      {creating && (
        <Modal title="Nova pesquisa de clima" onClose={() => setCreating(false)}>
          <div className="space-y-3">
            <p className="text-sm text-gray-600">Vai com as perguntas padrão: indicação (eNPS), reconhecimento, clareza do que se espera e apoio do gestor. Todos serão avisados.</p>
            <div><label className={labelCls} htmlFor="cl-t">Título</label><input id="cl-t" className={inputCls} maxLength={120} value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Ex.: Clima do 4º trimestre" /></div>
            <div><label className={labelCls} htmlFor="cl-e">Encerra em (opcional)</label><input id="cl-e" type="date" className={inputCls} value={closes} onChange={(e) => setCloses(e.target.value)} /></div>
            {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
            <div className="flex justify-end gap-2">
              <button type="button" className={btnGhost} onClick={() => setCreating(false)}>Cancelar</button>
              <button type="button" className={btnPrimary} disabled={title.trim().length < 4 || open.isPending} onClick={() => open.mutate()}>Abrir pesquisa</button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  )
}
