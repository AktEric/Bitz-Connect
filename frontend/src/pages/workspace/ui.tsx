import type { ReactNode } from 'react'
import { useOutletContext } from 'react-router-dom'
import { cn } from '@/utils/cn'
import type { WorkspaceInfo } from '@/types/workspace.types'

export interface WorkspaceContext {
  slug: string
  info: WorkspaceInfo
}

export function useWs() {
  return useOutletContext<WorkspaceContext>()
}

export function Spinner() {
  return (
    <div className="flex h-40 items-center justify-center">
      <div className="h-7 w-7 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>
  )
}

export function PageHeader({ title, subtitle, actions }: { title: string; subtitle?: string; actions?: ReactNode }) {
  return (
    <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-gray-500">{subtitle}</p>}
      </div>
      {actions}
    </div>
  )
}

export function Panel({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={cn('rounded-2xl border border-gray-100 bg-white p-4 shadow-sm', className)}>{children}</div>
}

export function Empty({ text }: { text: string }) {
  return <div className="rounded-2xl border border-dashed border-gray-200 bg-white py-12 text-center text-sm text-gray-400">{text}</div>
}

export function Tabs<T extends string>({ value, onChange, options }: { value: T; onChange: (v: T) => void; options: { value: T; label: string }[] }) {
  return (
    <div className="mb-4 inline-flex rounded-xl bg-gray-100 p-1">
      {options.map((o) => (
        <button
          key={o.value}
          type="button"
          onClick={() => onChange(o.value)}
          className={cn(
            'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
            value === o.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700',
          )}
        >
          {o.label}
        </button>
      ))}
    </div>
  )
}

const TONES = {
  gray: 'bg-gray-100 text-gray-600',
  green: 'bg-emerald-50 text-emerald-700',
  red: 'bg-red-50 text-red-700',
  amber: 'bg-amber-50 text-amber-700',
  violet: 'bg-violet-50 text-violet-700',
  blue: 'bg-blue-50 text-blue-700',
} as const

export function Pill({ tone = 'gray', children }: { tone?: keyof typeof TONES; children: ReactNode }) {
  return <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', TONES[tone])}>{children}</span>
}

export const btnPrimary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50'
export const btnGhost = 'inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50'
export const inputCls = 'w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100'
export const labelCls = 'mb-1 block text-xs font-medium text-gray-600'

export function Modal({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
  return (
    <div className="fixed inset-0 z-[70] flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" onClick={onClose}>
      <div
        className="max-h-[90vh] w-full overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:max-w-lg sm:rounded-2xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900">{title}</h2>
          <button type="button" onClick={onClose} className="rounded-lg px-2 py-1 text-gray-400 hover:bg-gray-100" aria-label="Fechar">✕</button>
        </div>
        {children}
      </div>
    </div>
  )
}

export function errMsg(e: unknown): string {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const first = err.response?.data?.errors ? Object.values(err.response.data.errors)[0]?.[0] : undefined
  return first ?? err.response?.data?.message ?? 'Algo deu errado. Tente novamente.'
}

export const fmtDate = (iso: string | null | undefined) =>
  iso ? new Date(iso.length === 10 ? iso + 'T12:00:00' : iso).toLocaleDateString('pt-BR') : '—'

export const fmtMoney = (cents: number | null | undefined) =>
  cents == null ? '—' : (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

export const fmtMonth = (ym: string | null | undefined) => {
  if (!ym) return '—'
  const [y, m] = ym.split('-')
  const names = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']
  return `${names[Number(m) - 1]}/${y}`
}

export const timeAgo = (iso: string) => {
  const s = Math.max(1, Math.floor((Date.now() - new Date(iso).getTime()) / 1000))
  if (s < 60) return 'agora'
  if (s < 3600) return `${Math.floor(s / 60)} min`
  if (s < 86400) return `${Math.floor(s / 3600)} h`
  return `${Math.floor(s / 86400)} d`
}
