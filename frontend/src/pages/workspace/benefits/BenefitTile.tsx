import { CalendarDays } from 'lucide-react'
import type { BenefitItem } from '@/types/benefits.types'
import { cn } from '@/utils/cn'
import { fmtDate, fmtMoney } from '../ui'
import { BENEFIT_COLORS } from './benefitColors'

export const depositText = (b: BenefitItem) =>
  b.frequency === 'one_time' ? `Depósito em ${fmtDate(b.deposit_on)}` : `Todo dia ${b.deposit_day}`

/** O quadrado colorido de um benefício: nome, valor e data de depósito. */
export default function BenefitTile({ b }: { b: BenefitItem }) {
  const c = BENEFIT_COLORS[b.type.color] ?? BENEFIT_COLORS.gray

  return (
    <article className={cn('relative flex min-h-[10.5rem] flex-col justify-between overflow-hidden rounded-2xl p-4 text-white shadow-sm', c.tile, !b.active && 'opacity-60 grayscale')} aria-label={`${b.type.name}${b.active ? '' : ' (encerrado)'}`}>
      <span className="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/10" aria-hidden />
      <div>
        <div className="flex items-start justify-between gap-2">
          <h2 className="text-base font-semibold leading-tight">{b.type.name}</h2>
          {!b.active && <span className="rounded-full bg-black/25 px-2 py-0.5 text-[11px] font-medium">Encerrado</span>}
        </div>
        <p className="mt-2 text-2xl font-bold tabular-nums">{b.amount_cents == null ? 'Incluso' : fmtMoney(b.amount_cents)}</p>
        {b.amount_cents != null && <p className="text-xs text-white/80">{b.frequency === 'monthly' ? 'por mês' : 'pagamento único'}</p>}
        {b.amount_cents == null && b.note && <p className="mt-1 text-sm text-white/90">{b.note}</p>}
      </div>
      <div className="mt-3 border-t border-white/25 pt-2 text-xs">
        <p className="flex items-center gap-1.5 font-medium"><CalendarDays className="h-3.5 w-3.5" /> {depositText(b)}</p>
        {b.active && b.next_deposit_on && <p className="mt-0.5 text-white/85">Próximo: {fmtDate(b.next_deposit_on)}</p>}
        {b.ends_on && <p className="mt-0.5 text-white/85">{b.active ? 'Até' : 'Terminou em'} {fmtDate(b.ends_on)}</p>}
        {b.amount_cents != null && b.note && <p className="mt-0.5 text-white/85">{b.note}</p>}
      </div>
    </article>
  )
}
