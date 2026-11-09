import { useState } from 'react'
import { Link } from 'react-router-dom'
import { MapPin, ExternalLink, TrendingUp } from 'lucide-react'
import { useHotelDirectory } from '@/hooks/useHotel'
import { useJobs } from '@/hooks/useJobs'
import { Card, CardTitle } from '@/components/ui/Card'
import { Avatar } from '@/components/ui/Avatar'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import type { Hotel } from '@/types/hotel.types'
import type { Job } from '@/types/job.types'

// ── News ─────────────────────────────────────────────────

const MOCK_NEWS = [
  { id: 1, title: 'Setor hoteleiro cresce 12% no 1º trimestre', ago: '2h' },
  { id: 2, title: 'Tecnologia transforma a recepção de hóspedes', ago: '4h' },
  { id: 3, title: 'Brasil lidera turismo de negócios na América Latina', ago: '6h' },
  { id: 4, title: 'Sustentabilidade é prioridade nas empresas em 2025', ago: '9h' },
]

export function NewsWidget() {
  const [all, setAll] = useState(false)
  const visible = all ? MOCK_NEWS : MOCK_NEWS.slice(0, 3)
  return (
    <Card className="mb-3">
      <div className="flex items-center gap-2 mb-3">
        <TrendingUp className="h-4 w-4 text-brand-500" />
        <CardTitle>Novidades do mercado</CardTitle>
      </div>
      <ul className="space-y-3">
        {visible.map((n) => (
          <li key={n.id} className="group cursor-pointer">
            <p className="text-sm text-gray-700 group-hover:text-brand-600 transition-colors leading-snug">{n.title}</p>
            <p className="text-xs text-gray-400 mt-0.5">há {n.ago}</p>
          </li>
        ))}
      </ul>
      {!all && (
        <button onClick={() => setAll(true)} className="mt-3 text-xs font-medium text-brand-600 hover:underline">
          Ver mais
        </button>
      )}
    </Card>
  )
}

// ── Hotel Highlights ──────────────────────────────────────

const FALLBACK_HOTELS: Pick<Hotel, 'uuid' | 'slug' | 'name' | 'city' | 'state' | 'logo_url'>[] = [
  { uuid: 'mock-1', slug: 'grand-palace', name: 'Grand Palace Hotel', city: 'São Paulo', state: 'SP', logo_url: null },
  { uuid: 'mock-2', slug: 'ocean-breeze', name: 'Ocean Breeze Resort', city: 'Florianópolis', state: 'SC', logo_url: null },
]

export function HotelHighlights() {
  const { data } = useHotelDirectory()
  const hotels = (data?.data?.length ? data.data : FALLBACK_HOTELS) as Hotel[]
  return (
    <Card className="mb-3">
      <CardTitle className="mb-3">Empresas em Destaque</CardTitle>
      <ul className="space-y-2.5">
        {hotels.slice(0, 4).map((h) => (
          <li key={h.uuid}>
            <Link to={`/hotels/${h.slug}`} className="flex items-center gap-2.5 group">
              <Avatar src={h.logo_url} name={h.name} size="sm" />
              <div className="min-w-0">
                <p className="flex items-center gap-1 truncate text-sm font-medium text-gray-800 group-hover:text-brand-600 transition-colors">
                  <span className="truncate">{h.name}</span>
                  <PremiumBadge plan={h.plan} compact />
                </p>
                {(h.city || h.state) && (
                  <p className="flex items-center gap-0.5 text-xs text-gray-400">
                    <MapPin className="h-3 w-3" />{[h.city, h.state].filter(Boolean).join(', ')}
                  </p>
                )}
              </div>
            </Link>
          </li>
        ))}
      </ul>
    </Card>
  )
}

// ── Recommended Jobs ──────────────────────────────────────

export function RecommendedJobs() {
  const { data } = useJobs({ page: 1 })
  const jobs: Job[] = data?.data?.slice(0, 3) ?? []
  return (
    <Card className="mb-3">
      <CardTitle className="mb-3">Vagas Recomendadas</CardTitle>
      {jobs.length === 0 ? (
        <div className="space-y-2">
          {[0, 1, 2].map((i) => (
            <div key={i} className="flex items-center gap-2.5 animate-pulse">
              <div className="h-8 w-8 rounded-lg bg-gray-100 flex-shrink-0" />
              <div className="flex-1 space-y-1.5">
                <div className="h-2.5 w-28 rounded bg-gray-100" />
                <div className="h-2 w-20 rounded bg-gray-100" />
              </div>
            </div>
          ))}
        </div>
      ) : (
        <ul className="space-y-2.5">
          {jobs.map((job) => (
            <li key={job.uuid}>
              <Link to={`/jobs/${job.uuid}`} className="flex items-center gap-2.5 group">
                <Avatar src={job.hotel.logo_url} name={job.hotel.name} size="sm" className="rounded-lg" />
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-gray-800 group-hover:text-brand-600 transition-colors">{job.title}</p>
                  {job.city && (
                    <p className="flex items-center gap-0.5 text-xs text-gray-400">
                      <MapPin className="h-3 w-3" />{job.city}
                    </p>
                  )}
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
      <Link to="/jobs" className="mt-3 flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline">
        <ExternalLink className="h-3 w-3" />Ver todas as vagas
      </Link>
    </Card>
  )
}

// ── Suggested Connections ─────────────────────────────────

export function SuggestedConnections() {
  return (
    <Card className="mb-3">
      <CardTitle className="mb-3">Pessoas que talvez você conheça</CardTitle>
      <ul className="space-y-3 mb-3">
        {[0, 1].map((i) => (
          <li key={i} className="flex items-center gap-2.5 animate-pulse">
            <div className="h-9 w-9 rounded-full bg-gray-100 flex-shrink-0" />
            <div className="flex-1 space-y-1.5">
              <div className="h-2.5 w-28 rounded bg-gray-100" />
              <div className="h-2 w-20 rounded bg-gray-100" />
            </div>
            <div className="h-6 w-16 rounded-full bg-gray-100" />
          </li>
        ))}
      </ul>
      <p className="text-xs text-gray-400 italic">Em breve: sugestões personalizadas</p>
    </Card>
  )
}

// ── Composed sidebar ──────────────────────────────────────

export default function SidebarRight() {
  return (
    <div className="w-full">
      <NewsWidget />
      <HotelHighlights />
      <RecommendedJobs />
      <SuggestedConnections />
    </div>
  )
}
