import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Download, Printer } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Empty, PageHeader, Panel, Spinner, btnGhost, errMsg, inputCls, useWs } from './ui'

function csvOf(columns: string[], rows: (string | number)[][]): string {
  const esc = (v: string | number) => `"${String(v).replace(/"/g, '""')}"`
  return '﻿' + [columns, ...rows].map((r) => r.map(esc).join(';')).join('\r\n')
}

function Result({ slug, reportKey, month }: { slug: string; reportKey: string; month: string }) {
  const { data, isLoading, error } = useQuery({
    queryKey: ['ws', slug, 'report', reportKey, month],
    queryFn: () => workspaceService.report(slug, reportKey, month),
  })
  if (isLoading) return <Spinner />
  if (error) return <p className="text-sm text-red-600" role="alert">{errMsg(error)}</p>
  if (!data) return null

  const download = () => {
    const url = URL.createObjectURL(new Blob([csvOf(data.columns, data.rows)], { type: 'text/csv;charset=utf-8' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `${data.key}${data.month ? '-' + data.month : ''}.csv`
    a.click()
    URL.revokeObjectURL(url)
  }

  return (
    <Panel className="mt-4">
      <div className="no-print mb-3 flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm text-gray-500">{data.rows.length} linha(s)</p>
        <div className="flex gap-2">
          <button type="button" className={btnGhost} onClick={download}><Download size={15} /> Baixar CSV</button>
          <button type="button" className={btnGhost} onClick={() => window.print()}><Printer size={15} /> Imprimir / PDF</button>
        </div>
      </div>
      <div className="print-area overflow-x-auto">
        <h2 className="mb-1 font-semibold text-gray-900">{data.title}{data.month ? ` — ${data.month.split('-').reverse().join('/')}` : ''}</h2>
        <p className="mb-3 text-xs text-gray-400">Gerado em {new Date(data.generated_at).toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' })}</p>
        {data.rows.length === 0 ? <Empty text="Nada a mostrar neste período." /> : (
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                {data.columns.map((c) => <th key={c} className="whitespace-nowrap px-2 py-2 font-medium">{c}</th>)}
              </tr>
            </thead>
            <tbody>
              {data.rows.map((r, i) => (
                <tr key={i} className="border-b border-gray-100 last:border-0">
                  {r.map((v, j) => <td key={j} className="px-2 py-2 text-gray-800">{v}</td>)}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </Panel>
  )
}

export default function WorkspaceReportsPage() {
  const { slug } = useWs()
  const { data: list, isLoading } = useQuery({ queryKey: ['ws', slug, 'reports'], queryFn: () => workspaceService.reports(slug) })
  const [key, setKey] = useState<string | null>(null)
  const [month, setMonth] = useState(() => new Date().toISOString().slice(0, 7))
  const current = list?.find((r) => r.key === key)

  return (
    <div>
      <PageHeader title="Relatórios" subtitle="Cada relatório mostra só o que o seu papel permite ver." />
      {isLoading ? <Spinner /> : !list?.length ? <Empty text="Seu papel não tem relatórios disponíveis." /> : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {list.map((r) => (
              <button key={r.key} type="button" onClick={() => setKey(r.key)} aria-pressed={key === r.key}
                className={`rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:border-violet-300 ${key === r.key ? 'border-violet-500 ring-2 ring-violet-100' : 'border-gray-100'}`}>
                <span className="block font-semibold text-gray-900">{r.title}</span>
                <span className="mt-1 block text-xs text-gray-500">{r.description}</span>
              </button>
            ))}
          </div>
          {current && (
            <>
              {current.monthly && (
                <div className="no-print mt-4 max-w-[12rem]">
                  <label className="mb-1 block text-xs font-medium text-gray-500" htmlFor="rel-mes">Mês</label>
                  <input id="rel-mes" type="month" className={inputCls} value={month} max={new Date().toISOString().slice(0, 7)} onChange={(e) => e.target.value && setMonth(e.target.value)} />
                </div>
              )}
              <Result slug={slug} reportKey={current.key} month={current.monthly ? month : ''} />
            </>
          )}
        </>
      )}
    </div>
  )
}
