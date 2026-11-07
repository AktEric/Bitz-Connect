import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowRight, Network, Plus, Trash2 } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Panel, Pill, Spinner, btnPrimary, errMsg, fmtDate, inputCls, labelCls } from './ui'

/** Áreas com chefe (enxerga e aprova a área toda) e substituições temporárias de gestores. */
export default function AreasSection({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const [error, setError] = useState('')
  const today = new Date().toISOString().slice(0, 10)
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [start, setStart] = useState(today)
  const [end, setEnd] = useState(today)

  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'areas'], queryFn: () => workspaceService.areas(slug) })
  const refresh = () => {
    qc.invalidateQueries({ queryKey: ['ws', slug, 'areas'] })
    qc.invalidateQueries({ queryKey: ['workspace', slug] })
  }

  const setHead = useMutation({
    mutationFn: ({ area, head }: { area: string; head: string | null }) => workspaceService.setAreaHead(slug, area, head),
    onSuccess: () => { setError(''); refresh() },
    onError: (e) => setError(errMsg(e)),
  })
  const delegate = useMutation({
    mutationFn: () => workspaceService.delegate(slug, { from_user_uuid: from, to_user_uuid: to, starts_on: start, ends_on: end }),
    onSuccess: () => { setError(''); setFrom(''); setTo(''); refresh() },
    onError: (e) => setError(errMsg(e)),
  })
  const undelegate = useMutation({
    mutationFn: (uuid: string) => workspaceService.undelegate(slug, uuid),
    onSuccess: () => { setError(''); refresh() },
    onError: (e) => setError(errMsg(e)),
  })

  if (isLoading || !data) return <Spinner />

  return (
    <section className="mb-8">
      <div className="mb-1 flex items-center gap-2">
        <Network className="h-5 w-5 text-violet-600" />
        <h2 className="text-lg font-semibold text-gray-900">Áreas e chefias</h2>
      </div>
      <p className="mb-3 text-sm text-gray-500">
        O chefe da área enxerga e aprova solicitações, ponto, metas e desenvolvimento de todas as pessoas do setor.
        Quem é gestor de gestores também enxerga toda a equipe abaixo.
      </p>
      {error && <p className="mb-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{error}</p>}

      {data.areas.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-200 bg-white p-4 text-sm text-gray-400">
          Nenhuma área criada. Aplique um modelo de segmento mais abaixo ou crie setores em “Pessoas”.
        </div>
      ) : (
        <Panel className="divide-y divide-gray-100 p-0">
          {data.areas.map((a) => (
            <div key={a.uuid} className="flex flex-wrap items-center gap-3 px-4 py-3">
              <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-semibold text-gray-900">{a.name}</div>
                <div className="text-xs text-gray-500">{a.members_count} {a.members_count === 1 ? 'pessoa' : 'pessoas'}</div>
              </div>
              <select
                className={`${inputCls} !w-auto max-w-[16rem]`}
                value={a.head?.uuid ?? ''}
                disabled={setHead.isPending}
                aria-label={`Chefe da área ${a.name}`}
                onChange={(e) => setHead.mutate({ area: a.uuid, head: e.target.value || null })}
              >
                <option value="">Sem chefe</option>
                {data.people.map((p) => <option key={p.uuid} value={p.uuid}>{p.name}</option>)}
              </select>
            </div>
          ))}
        </Panel>
      )}

      <h3 className="mb-2 mt-6 text-base font-semibold text-gray-900">Substituições</h3>
      <p className="mb-3 text-sm text-gray-500">Durante o período, o substituto aprova e acompanha a equipe de quem está ausente (férias, licença).</p>

      {data.delegations.length > 0 && (
        <ul className="mb-4 space-y-2">
          {data.delegations.map((d) => (
            <li key={d.uuid} className="flex flex-wrap items-center gap-2 rounded-xl border border-gray-100 bg-white px-3 py-2 text-sm">
              <b className="text-gray-900">{d.from?.name}</b>
              <ArrowRight className="h-4 w-4 text-gray-300" />
              <b className="text-gray-900">{d.to?.name}</b>
              <span className="text-gray-500">{fmtDate(d.starts_on)} a {fmtDate(d.ends_on)}</span>
              {d.active ? <Pill tone="green">Em vigor</Pill> : <Pill tone="gray">Agendada</Pill>}
              <button
                type="button" className="ml-auto rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600"
                aria-label="Remover substituição" disabled={undelegate.isPending} onClick={() => undelegate.mutate(d.uuid)}
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </li>
          ))}
        </ul>
      )}

      <Panel>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <label className={labelCls}>Quem se ausenta</label>
            <select className={inputCls} value={from} onChange={(e) => setFrom(e.target.value)}>
              <option value="">Selecione…</option>
              {data.people.map((p) => <option key={p.uuid} value={p.uuid}>{p.name}</option>)}
            </select>
          </div>
          <div>
            <label className={labelCls}>Substituto</label>
            <select className={inputCls} value={to} onChange={(e) => setTo(e.target.value)}>
              <option value="">Selecione…</option>
              {data.people.filter((p) => p.uuid !== from).map((p) => <option key={p.uuid} value={p.uuid}>{p.name}</option>)}
            </select>
          </div>
          <div>
            <label className={labelCls}>De</label>
            <input type="date" className={inputCls} min={today} value={start} onChange={(e) => { setStart(e.target.value); if (e.target.value > end) setEnd(e.target.value) }} />
          </div>
          <div>
            <label className={labelCls}>Até</label>
            <input type="date" className={inputCls} min={start} value={end} onChange={(e) => setEnd(e.target.value)} />
          </div>
        </div>
        <div className="mt-3">
          <button type="button" className={btnPrimary} disabled={!from || !to || delegate.isPending} onClick={() => delegate.mutate()}>
            <Plus className="h-4 w-4" /> {delegate.isPending ? 'Registrando…' : 'Registrar substituição'}
          </button>
        </div>
      </Panel>
    </section>
  )
}
