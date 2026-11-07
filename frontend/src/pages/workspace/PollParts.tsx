import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, X } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { FeedPost } from '@/types/workspace.types'
import { Modal, btnGhost, btnPrimary, errMsg, fmtDate, inputCls, labelCls } from './ui'

/** Opções da enquete com barras de resultado; clicar vota (ou troca o voto) enquanto estiver aberta. */
export function PollBlock({ slug, post }: { slug: string; post: FeedPost }) {
  const qc = useQueryClient()
  const poll = post.poll
  const vote = useMutation({
    mutationFn: (option: string) => workspaceService.votePoll(slug, post.uuid, option),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] }),
  })
  if (!poll) return null

  return (
    <div className="mt-3 space-y-2">
      {poll.options.map((o) => {
        const pct = poll.total ? Math.round((o.votes / poll.total) * 100) : 0
        const mine = poll.my_vote === o.uuid
        return (
          <button key={o.uuid} type="button" disabled={poll.closed || vote.isPending} onClick={() => vote.mutate(o.uuid)} aria-pressed={mine}
            className={`relative block w-full overflow-hidden rounded-xl border px-3 py-2 text-left text-sm ${mine ? 'border-violet-500' : 'border-gray-200'} ${poll.closed ? 'cursor-default' : 'hover:border-violet-300'}`}>
            <span className="absolute inset-y-0 left-0 bg-violet-100/70" style={{ width: `${pct}%` }} aria-hidden />
            <span className="relative flex items-center justify-between gap-3">
              <span className={mine ? 'font-semibold text-violet-800' : 'text-gray-800'}>{mine ? '✓ ' : ''}{o.label}</span>
              <span className="flex-shrink-0 text-xs text-gray-500">{pct}% · {o.votes}</span>
            </span>
          </button>
        )
      })}
      <p className="text-xs text-gray-400">
        {poll.total} {poll.total === 1 ? 'voto' : 'votos'} · votação anônima
        {poll.closes_on && (poll.closed ? ' · encerrada' : ` · até ${fmtDate(poll.closes_on)}`)}
      </p>
      {vote.error && <p className="text-xs text-red-600" role="alert">{errMsg(vote.error)}</p>}
    </div>
  )
}

export function PollModal({ slug, onClose }: { slug: string; onClose: () => void }) {
  const qc = useQueryClient()
  const { data: departments } = useQuery({ queryKey: ['ws', slug, 'departments'], queryFn: () => workspaceService.departments(slug) })
  const [question, setQuestion] = useState('')
  const [options, setOptions] = useState(['', ''])
  const [closes, setCloses] = useState('')
  const [dept, setDept] = useState('')
  const [error, setError] = useState('')
  const create = useMutation({
    mutationFn: () => workspaceService.createPoll(slug, { question: question.trim(), options: options.map((o) => o.trim()).filter(Boolean), closes_on: closes || null, department_uuid: dept || null }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] }); onClose() },
    onError: (e) => setError(errMsg(e)),
  })
  const filled = options.filter((o) => o.trim()).length

  return (
    <Modal title="Nova enquete" onClose={onClose}>
      <div className="space-y-3">
        <div><label className={labelCls} htmlFor="p-q">Pergunta</label><input id="p-q" className={inputCls} maxLength={300} value={question} onChange={(e) => setQuestion(e.target.value)} placeholder="Ex.: Onde será a festa de fim de ano?" /></div>
        <div>
          <p className={labelCls}>Opções (2 a 6)</p>
          <div className="space-y-2">
            {options.map((o, i) => (
              <div key={i} className="flex gap-2">
                <input className={inputCls} maxLength={120} aria-label={`Opção ${i + 1}`} value={o} onChange={(e) => setOptions((a) => a.map((x, j) => (j === i ? e.target.value : x)))} placeholder={`Opção ${i + 1}`} />
                {options.length > 2 && <button type="button" aria-label={`Remover opção ${i + 1}`} className="text-gray-400 hover:text-red-500" onClick={() => setOptions((a) => a.filter((_, j) => j !== i))}><X className="h-4 w-4" /></button>}
              </div>
            ))}
          </div>
          {options.length < 6 && <button type="button" className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-violet-600" onClick={() => setOptions((a) => [...a, ''])}><Plus className="h-4 w-4" /> Adicionar opção</button>}
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div><label className={labelCls} htmlFor="p-c">Encerra em (opcional)</label><input id="p-c" type="date" className={inputCls} value={closes} onChange={(e) => setCloses(e.target.value)} /></div>
          <div><label className={labelCls} htmlFor="p-d">Público</label>
            <select id="p-d" className={inputCls} value={dept} onChange={(e) => setDept(e.target.value)}>
              <option value="">Toda a empresa</option>{departments?.map((d) => <option key={d.uuid} value={d.uuid}>{d.name}</option>)}
            </select></div>
        </div>
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>Cancelar</button>
          <button type="button" className={btnPrimary} disabled={question.trim().length < 5 || filled < 2 || create.isPending} onClick={() => create.mutate()}>Publicar enquete</button>
        </div>
      </div>
    </Modal>
  )
}
