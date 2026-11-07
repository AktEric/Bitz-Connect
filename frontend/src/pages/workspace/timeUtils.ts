import type { TimeType } from '@/types/workspace.types'

export const fmtMinutes = (m: number) => {
  const sign = m < 0 ? '-' : ''
  const a = Math.abs(Math.round(m))
  return `${sign}${Math.floor(a / 60)}h${String(a % 60).padStart(2, '0')}`
}

export const TYPE_LABEL: Record<TimeType, string> = {
  in: 'Entrada',
  break_start: 'Saída para pausa',
  break_end: 'Retorno da pausa',
  out: 'Saída',
}

export const ACTION_LABEL: Record<TimeType, string> = {
  in: 'Registrar entrada',
  break_start: 'Iniciar pausa',
  break_end: 'Voltar da pausa',
  out: 'Encerrar o dia',
}

export const fmtClock = (iso: string) =>
  new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: 'America/Sao_Paulo' })
