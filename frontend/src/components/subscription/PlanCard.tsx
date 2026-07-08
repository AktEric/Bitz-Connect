import { Check, Sparkles } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { cn } from '@/utils/cn'
import { formatCurrency } from '@/utils/format'
import type { Plan, PlanTier } from '@/types/subscription.types'

interface PlanCardProps {
  plan: Plan
  currency: string
  /** The viewer's current plan for this audience, to render "plano atual". */
  currentPlan?: PlanTier
  onSelect?: (plan: Plan) => void
  loading?: boolean
}

/**
 * Single plan column for the pricing table (LinkedIn Premium style).
 * Requirements: §2, §12 (reusable PlanCard).
 */
export function PlanCard({ plan, currency, currentPlan, onSelect, loading }: PlanCardProps) {
  const isCurrent = currentPlan === plan.id
  const isFree = plan.id === 'free'

  return (
    <div
      className={cn(
        'flex flex-col rounded-2xl border bg-white p-6',
        plan.featured ? 'border-amber-300 shadow-lg ring-1 ring-amber-200' : 'border-gray-200',
      )}
    >
      {plan.featured && (
        <span className="mb-3 inline-flex w-fit items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">
          <Sparkles className="h-3 w-3" /> Recomendado
        </span>
      )}

      <h3 className="text-lg font-semibold text-gray-900">{plan.name}</h3>
      {plan.tagline && <p className="mt-1 text-sm text-gray-500">{plan.tagline}</p>}

      <div className="mt-4 flex items-baseline gap-1">
        <span className="text-3xl font-bold text-gray-900">
          {isFree ? 'Grátis' : formatCurrency(plan.price_cents, currency)}
        </span>
        {!isFree && plan.interval === 'month' && (
          <span className="text-sm text-gray-400">/mês</span>
        )}
      </div>

      <ul className="mt-5 flex-1 space-y-2.5">
        {plan.benefits.map((benefit) => (
          <li key={benefit.label} className="flex items-start gap-2 text-sm text-gray-700">
            <Check className="mt-0.5 h-4 w-4 flex-shrink-0 text-emerald-500" />
            <span>
              {benefit.label}
              {benefit.prepared && (
                <span className="ml-1 rounded bg-gray-100 px-1 py-0.5 text-[10px] font-medium text-gray-400">
                  em breve
                </span>
              )}
            </span>
          </li>
        ))}
      </ul>

      <div className="mt-6">
        {isFree ? (
          <Button variant="outline" fullWidth disabled>
            {isCurrent ? 'Seu plano' : 'Plano padrão'}
          </Button>
        ) : isCurrent ? (
          <Button variant="secondary" fullWidth disabled>
            Plano atual
          </Button>
        ) : (
          <Button
            variant={plan.featured ? 'primary' : 'outline'}
            fullWidth
            loading={loading}
            onClick={() => onSelect?.(plan)}
          >
            {currentPlan && currentPlan !== 'free' ? 'Fazer Upgrade' : 'Assinar'}
          </Button>
        )}
      </div>
    </div>
  )
}
