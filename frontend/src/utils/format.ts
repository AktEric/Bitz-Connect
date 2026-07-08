import { format, formatDistanceToNow } from 'date-fns'
import { ptBR } from 'date-fns/locale'

export function formatDate(date: string | Date): string {
  return format(new Date(date), "dd 'de' MMMM 'de' yyyy", { locale: ptBR })
}

export function formatRelative(date: string | Date): string {
  return formatDistanceToNow(new Date(date), { addSuffix: true, locale: ptBR })
}

export function formatDateTime(date: string | Date): string {
  return format(new Date(date), "dd/MM/yyyy 'às' HH:mm", { locale: ptBR })
}

/** Format an amount given in cents as a currency string (default BRL). */
export function formatCurrency(cents: number, currency = 'BRL'): string {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency,
  }).format(cents / 100)
}
