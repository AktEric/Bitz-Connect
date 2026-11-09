import type { BenefitColor } from '@/types/benefits.types'

/** Cor sólida de cada benefício (o "quadrado"): vale igual no tema claro e no escuro. */
export const BENEFIT_COLORS: Record<BenefitColor, { tile: string; dot: string; label: string }> = {
  green:  { tile: 'bg-emerald-600', dot: 'bg-emerald-500', label: 'Verde' },
  blue:   { tile: 'bg-blue-600',    dot: 'bg-blue-500',    label: 'Azul' },
  amber:  { tile: 'bg-amber-500',   dot: 'bg-amber-400',   label: 'Âmbar' },
  rose:   { tile: 'bg-rose-600',    dot: 'bg-rose-500',    label: 'Rosa' },
  purple: { tile: 'bg-purple-600',  dot: 'bg-purple-500',  label: 'Roxo' },
  teal:   { tile: 'bg-teal-600',    dot: 'bg-teal-500',    label: 'Turquesa' },
  orange: { tile: 'bg-orange-500',  dot: 'bg-orange-400',  label: 'Laranja' },
  gray:   { tile: 'bg-slate-600',   dot: 'bg-slate-500',   label: 'Cinza' },
}
