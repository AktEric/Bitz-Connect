import { useQuery } from '@tanstack/react-query'
import { Gift } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Empty, PageHeader, Spinner, fmtMoney, useWs } from './ui'
import BenefitTile from './benefits/BenefitTile'

/** Meus benefícios: cada pessoa vê só os seus (a API usa o usuário logado). */
export default function WorkspaceBenefitsPage() {
  const { slug } = useWs()
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'benefits', 'mine'], queryFn: () => workspaceService.myBenefits(slug) })
  const active = data?.items.filter((b) => b.active) ?? []
  const ended = data?.items.filter((b) => !b.active) ?? []

  return (
    <div>
      <PageHeader title="Meus benefícios" subtitle="Os benefícios que a empresa oferece a você, com valor e data de depósito. Só você vê esta tela." />
      {isLoading ? <Spinner /> : !data?.items.length ? <Empty text="Nenhum benefício cadastrado para você ainda. Em caso de dúvida, fale com o RH." /> : (
        <div className="space-y-6">
          {data.total_monthly_cents > 0 && (
            <div className="ws-card flex items-center gap-3 rounded-2xl bg-white p-4">
              <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><Gift className="h-5 w-5" /></span>
              <div><p className="text-xs text-gray-500">Total mensal em benefícios</p><p className="text-xl font-bold tabular-nums text-gray-900">{fmtMoney(data.total_monthly_cents)}</p></div>
            </div>
          )}
          <section aria-label="Benefícios vigentes" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{active.map((b) => <BenefitTile key={b.uuid} b={b} />)}</section>
          {ended.length > 0 && (
            <section aria-label="Benefícios encerrados">
              <h2 className="mb-3 text-sm font-semibold text-gray-500">Encerrados</h2>
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{ended.map((b) => <BenefitTile key={b.uuid} b={b} />)}</div>
            </section>
          )}
        </div>
      )}
    </div>
  )
}
