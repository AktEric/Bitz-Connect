import { useEffect, useState } from 'react'
import { Coffee, LogIn, LogOut, Play } from 'lucide-react'
import { useTimeToday, usePunch } from '@/hooks/useWorkspace'
import { cn } from '@/utils/cn'
import type { TimeType } from '@/types/workspace.types'
import { ACTION_LABEL, TYPE_LABEL, fmtClock, fmtMinutes } from './timeUtils'
import { errMsg } from './ui'
import CameraModal from './CameraModal'

type Geo = { lat: number; lng: number; accuracy: number }

/** Pega a posição do aparelho; em modo opcional falha em silêncio (null). */
function getPosition(required: boolean): Promise<Geo | null> {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) return required ? reject(new Error('Este aparelho não oferece localização.')) : resolve(null)
    navigator.geolocation.getCurrentPosition(
      (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy }),
      () => (required ? reject(new Error('Esta empresa exige a localização para marcar o ponto. Permita o acesso à localização no navegador e tente de novo.')) : resolve(null)),
      { enableHighAccuracy: true, timeout: 10_000, maximumAge: 30_000 },
    )
  })
}

const ICON: Record<TimeType, typeof LogIn> = { in: LogIn, break_start: Coffee, break_end: Play, out: LogOut }

const STATE_UI = {
  out: { label: 'Fora do expediente', dot: 'bg-gray-300' },
  working: { label: 'Trabalhando', dot: 'bg-emerald-400 animate-pulse' },
  break: { label: 'Em pausa', dot: 'bg-amber-400' },
} as const

/** Cartão de ponto: relógio ao vivo, estado da jornada e botões de marcação. */
export default function ClockCard({ slug, compact = false }: { slug: string; compact?: boolean }) {
  const { data, isLoading } = useTimeToday(slug)
  const punch = usePunch(slug)
  const [now, setNow] = useState(() => new Date())
  const [error, setError] = useState<string | null>(null)
  const [askPhoto, setAskPhoto] = useState<TimeType | null>(null)
  const [locating, setLocating] = useState(false)

  useEffect(() => {
    const t = setInterval(() => setNow(new Date()), 1000)
    return () => clearInterval(t)
  }, [])

  const time = now.toLocaleTimeString('pt-BR', { timeZone: 'America/Sao_Paulo' })
  const date = now.toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'America/Sao_Paulo' })

  const state = data?.state ?? 'out'
  const ui = STATE_UI[state]
  const next = data?.allowed_next ?? []
  const primary = next[0]
  const secondary = next.slice(1)

  // Se a jornada está aberta, soma o tempo desde a última atualização do servidor.
  const lastSync = data ? new Date(data.server_time).getTime() : now.getTime()
  const live = data ? data.worked_minutes + (state === 'working' ? Math.max(0, (now.getTime() - lastSync) / 60000) : 0) : 0
  const dayOff = !!data && data.daily_minutes === 0
  const pct = data && data.daily_minutes > 0 ? Math.min(100, (live / data.daily_minutes) * 100) : 0

  const req = data?.requirements

  const send = async (type: TimeType, photo: Blob | null) => {
    setError(null)
    let geo: Geo | null = null
    if (req && req.geo !== 'off') {
      setLocating(true)
      try { geo = await getPosition(req.geo === 'required') } catch (e) { setError((e as Error).message); setLocating(false); return }
      setLocating(false)
    }
    punch.mutate({ type, geo, photo }, { onError: (e) => setError(errMsg(e)) })
  }

  const doPunch = (type: TimeType) => {
    setError(null)
    if (req && req.photo !== 'off') setAskPhoto(type)
    else void send(type, null)
  }

  return (
    <section className="overflow-hidden rounded-3xl bg-gradient-to-br from-violet-700 via-violet-600 to-indigo-600 p-5 text-white shadow-lg sm:p-6">
      <div className="flex items-center justify-between">
        <span className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-medium">
          <span className={cn('h-2 w-2 rounded-full', ui.dot)} /> {ui.label}
        </span>
        <span className="text-xs capitalize text-violet-100">{date}</span>
      </div>

      <p className={cn('mt-4 font-semibold tabular-nums tracking-tight', compact ? 'text-4xl' : 'text-5xl sm:text-6xl')}>{time}</p>

      <div className="mt-4">
        <div className="mb-1 flex justify-between text-xs text-violet-100">
          <span>Hoje: {data ? fmtMinutes(live) : '—'}</span>
          <span>{dayOff ? 'Hoje é folga na sua escala' : `Jornada: ${data ? fmtMinutes(data.daily_minutes) : '8h00'}`}</span>
        </div>
        <div className="h-2 overflow-hidden rounded-full bg-white/20">
          <div className="h-full rounded-full bg-white transition-all" style={{ width: `${pct}%` }} />
        </div>
      </div>

      <div className="mt-5 flex flex-wrap gap-2">
        {primary && (() => {
          const Icon = ICON[primary]
          return (
            <button
              type="button"
              disabled={punch.isPending || isLoading}
              onClick={() => doPunch(primary)}
              className="inline-flex min-w-[10rem] flex-1 items-center justify-center gap-2 rounded-2xl bg-white px-5 py-3 text-sm font-semibold text-violet-700 shadow-sm transition hover:bg-violet-50 disabled:opacity-60 sm:flex-none"
            >
              <Icon className="h-4 w-4" /> {ACTION_LABEL[primary]}
            </button>
          )
        })()}
        {secondary.map((t) => {
          const Icon = ICON[t]
          return (
            <button
              key={t}
              type="button"
              disabled={punch.isPending}
              onClick={() => doPunch(t)}
              className="inline-flex items-center justify-center gap-2 rounded-2xl bg-white/15 px-4 py-3 text-sm font-medium text-white transition hover:bg-white/25 disabled:opacity-60"
            >
              <Icon className="h-4 w-4" /> {ACTION_LABEL[t]}
            </button>
          )
        })}
      </div>
      {locating && <p className="mt-3 text-xs text-violet-100">Obtendo sua localização…</p>}
      {error && <p className="mt-3 rounded-xl bg-red-500/20 px-3 py-2 text-xs text-white" role="alert">{error}</p>}
      {askPhoto && (
        <CameraModal
          required={req?.photo === 'required'}
          onCancel={() => setAskPhoto(null)}
          onSkip={() => { const t = askPhoto; setAskPhoto(null); void send(t, null) }}
          onCapture={(blob) => { const t = askPhoto; setAskPhoto(null); void send(t, blob) }}
        />
      )}

      {!compact && !!data?.entries.length && (
        <ol className="mt-5 flex flex-wrap gap-x-5 gap-y-1 border-t border-white/15 pt-3 text-xs text-violet-100">
          {data.entries.map((e) => (
            <li key={e.uuid}>
              <span className="font-semibold text-white">{fmtClock(e.punched_at)}</span> {TYPE_LABEL[e.type]}
            </li>
          ))}
        </ol>
      )}
    </section>
  )
}
