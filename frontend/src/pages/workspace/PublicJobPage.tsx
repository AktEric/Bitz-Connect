import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Briefcase, MapPin } from 'lucide-react'
import workspaceService from '@/services/workspace.service'

/** Página pública de divulgação da vaga (aberta por link, sem login). */
export default function PublicJobPage() {
  const { token = '' } = useParams()
  const { data, isLoading, isError } = useQuery({ queryKey: ['public-job', token], queryFn: () => workspaceService.publicJob(token), retry: false })

  return (
    <div className="min-h-screen bg-slate-50 px-4 py-10">
      <div className="mx-auto max-w-xl rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
        {isLoading ? <p className="text-sm text-gray-400">Carregando…</p> : isError || !data ? (
          <div className="text-center"><Briefcase className="mx-auto h-8 w-8 text-gray-300" /><p className="mt-2 font-semibold text-gray-900">Esta vaga não está mais disponível</p></div>
        ) : (
          <>
            <p className="text-xs font-semibold uppercase tracking-wide text-brand-600">{data.company}</p>
            <h1 className="mt-1 text-2xl font-bold text-gray-900">{data.title}</h1>
            <p className="mt-1 flex flex-wrap gap-x-3 text-sm text-gray-500">
              {data.department && <span>{data.department}</span>}
              {data.location && <span className="inline-flex items-center gap-1"><MapPin className="h-3.5 w-3.5" /> {data.location}</span>}
              {data.contract_type && <span>{data.contract_type}</span>}
            </p>
            <p className="mt-4 whitespace-pre-wrap text-sm text-gray-700">{data.description}</p>
            {data.requirements && <p className="mt-3 whitespace-pre-wrap text-sm text-gray-600"><b>Requisitos: </b>{data.requirements}</p>}
            {data.contact && <p className="mt-5 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900">Para se candidatar, fale com: <b>{data.contact}</b></p>}
            {data.closes_on && <p className="mt-2 text-xs text-gray-400">Inscrições até {data.closes_on.split('-').reverse().join('/')}</p>}
          </>
        )}
      </div>
    </div>
  )
}
