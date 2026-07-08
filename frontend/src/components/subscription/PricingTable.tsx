import { PlanCard } from './PlanCard'
import type { Plan, PlanTier } from '@/types/subscription.types'

interface PricingTableProps {
  plans: Plan[]
  currency: string
  currentUserPlan?: PlanTier
  currentHotelPlan?: PlanTier
  onSelect?: (plan: Plan) => void
  selectingPlan?: PlanTier | null
}

/**
 * Grid of PlanCards comparing all plans (§2).
 * Picks the relevant "current plan" per card based on the plan's audience.
 */
export function PricingTable({
  plans,
  currency,
  currentUserPlan,
  currentHotelPlan,
  onSelect,
  selectingPlan,
}: PricingTableProps) {
  return (
    <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
      {plans.map((plan) => (
        <PlanCard
          key={plan.id}
          plan={plan}
          currency={currency}
          currentPlan={plan.audience === 'hotel' ? currentHotelPlan : currentUserPlan}
          onSelect={onSelect}
          loading={selectingPlan === plan.id}
        />
      ))}
    </div>
  )
}
