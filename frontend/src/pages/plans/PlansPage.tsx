import { useState } from 'react'
import { Sparkles } from 'lucide-react'
import { usePlans, useMySubscription, useSubscribe } from '@/hooks/useSubscription'
import { useMyHotels } from '@/hooks/useHotel'
import { PricingTable } from '@/components/subscription/PricingTable'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { useToast } from '@/contexts/ToastContext'
import { SkeletonCard } from '@/components/ui/Skeleton'
import type { Plan, PlanTier } from '@/types/subscription.types'
import type { Hotel } from '@/types/hotel.types'

/**
 * Plans / pricing page (§2). Compares Free, PRO Consultor and PRO Empresa.
 * Activation is a fake checkout this sprint (no real payment).
 */
export default function PlansPage() {
  const { data: catalog, isLoading } = usePlans()
  const { data: mySub } = useMySubscription()
  const { data: myHotelsRes } = useMyHotels()
  const subscribe = useSubscribe()
  const { info } = useToast()

  const [pendingPlan, setPendingPlan] = useState<Plan | null>(null)
  const [hotelChoice, setHotelChoice] = useState<string>('')

  const myHotels: Hotel[] = myHotelsRes?.data ?? []
  const currentUserPlan: PlanTier = mySub?.plan ?? 'free'

  function handleSelect(plan: Plan) {
    if (plan.audience === 'hotel') {
      if (myHotels.length === 0) {
        info('Você precisa administrar um hotel para assinar o plano Empresa.')
        return
      }
      setHotelChoice(myHotels[0].slug)
    }
    setPendingPlan(plan)
  }

  function confirmSubscribe() {
    if (!pendingPlan) return
    subscribe.mutate(
      {
        plan: pendingPlan.id,
        hotel_slug: pendingPlan.audience === 'hotel' ? hotelChoice : undefined,
      },
      { onSuccess: () => setPendingPlan(null) },
    )
  }

  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <div className="mb-8 text-center">
        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
          <Sparkles className="h-3.5 w-3.5" /> ConnectHotel PRO
        </span>
        <h1 className="mt-3 text-3xl font-bold text-gray-900">Escolha seu plano</h1>
        <p className="mt-2 text-gray-500">
          Ferramentas profissionais para pessoas e empresas do setor hoteleiro.
        </p>
      </div>

      {isLoading || !catalog ? (
        <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
          <SkeletonCard />
          <SkeletonCard />
          <SkeletonCard />
        </div>
      ) : (
        <PricingTable
          plans={catalog.plans}
          currency={catalog.currency}
          currentUserPlan={currentUserPlan}
          onSelect={handleSelect}
          selectingPlan={subscribe.isPending ? pendingPlan?.id : null}
        />
      )}

      <ConfirmDialog
        open={!!pendingPlan}
        title={`Assinar ${pendingPlan?.name ?? ''}`}
        description={
          pendingPlan?.audience === 'hotel' && myHotels.length > 1
            ? 'Selecione o hotel e confirme a ativação do plano (checkout de demonstração — sem cobrança).'
            : 'Confirme a ativação do plano. Este é um checkout de demonstração — nenhuma cobrança será feita.'
        }
        confirmLabel="Ativar plano"
        loading={subscribe.isPending}
        onConfirm={confirmSubscribe}
        onCancel={() => setPendingPlan(null)}
      />

      {/* Hotel picker for company plan when the user administers several hotels */}
      {pendingPlan?.audience === 'hotel' && myHotels.length > 1 && (
        <div className="fixed inset-0 z-[60] flex items-end justify-center p-4 pb-28 pointer-events-none">
          <div className="pointer-events-auto w-full max-w-md rounded-xl border border-gray-200 bg-white p-3 shadow-lg">
            <label className="mb-1 block text-xs font-medium text-gray-500">Hotel</label>
            <select
              value={hotelChoice}
              onChange={(e) => setHotelChoice(e.target.value)}
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            >
              {myHotels.map((h) => (
                <option key={h.slug} value={h.slug}>{h.name}</option>
              ))}
            </select>
          </div>
        </div>
      )}
    </div>
  )
}
