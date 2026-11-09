import { CheckCircle2, Circle, Clock, MinusCircle, XCircle } from 'lucide-react'
import type { RequestStep } from '@/types/workspace.types'
import { cn } from '@/utils/cn'
import PersonLink from './PersonLink'
import { fmtDate } from './ui'

const STATUS: Record<RequestStep['status'], { label: string; Icon: typeof Circle; color: string }> = {
  approved: { label: 'Aprovou', Icon: CheckCircle2, color: 'text-emerald-600' },
  rejected: { label: 'Recusou', Icon: XCircle, color: 'text-red-600' },
  pending: { label: 'Em análise', Icon: Clock, color: 'text-amber-500' },
  waiting: { label: 'Aguardando a vez', Icon: Circle, color: 'text-gray-300' },
  skipped: { label: 'Não necessária', Icon: MinusCircle, color: 'text-gray-300' },
}

/** Etapas da aprovação: quem já aprovou, quem está analisando agora e quem ainda falta. */
export default function RequestFlowTimeline({ steps }: { steps: RequestStep[] }) {
  if (!steps.length) return null

  return (
    <ol className="space-y-0" aria-label="Etapas da aprovação">
      {steps.map((s, i) => {
        const { label, Icon, color } = STATUS[s.status]
        return (
          <li key={s.position} className="relative flex gap-3 pb-4 last:pb-0" aria-current={s.is_current ? 'step' : undefined}>
            {i < steps.length - 1 && <span className="absolute left-[11px] top-6 h-[calc(100%-1.5rem)] w-px bg-gray-200" aria-hidden />}
            <Icon className={cn('relative z-[1] mt-0.5 h-6 w-6 flex-shrink-0 rounded-full bg-white', color, s.is_current && 'animate-pulse')} aria-hidden />
            <div className="min-w-0 flex-1">
              <p className="flex flex-wrap items-baseline gap-x-2">
                <span className={cn('font-semibold', s.status === 'waiting' || s.status === 'skipped' ? 'text-gray-400' : 'text-gray-900')}>{s.label}</span>
                <span className={cn('text-xs font-medium', color)}>{label}</span>
              </p>
              {s.decided_by && (
                <p className="text-xs text-gray-500">
                  <PersonLink person={s.decided_by} className="font-medium text-gray-700" />{s.decided_at ? ` · ${fmtDate(s.decided_at)}` : ''}
                </p>
              )}
              {s.note && <p className="mt-0.5 text-xs italic text-gray-500">“{s.note}”</p>}
              {!!s.approvers.length && (
                <p className="text-xs text-gray-500">
                  {s.status === 'pending' ? 'Analisando agora: ' : 'Vai analisar: '}
                  {s.approvers.map((p, k) => <span key={p.uuid}>{k > 0 && ', '}<PersonLink person={p} className="font-medium text-gray-700" /></span>)}
                </p>
              )}
            </div>
          </li>
        )
      })}
    </ol>
  )
}
