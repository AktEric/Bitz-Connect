import { useNavigate } from 'react-router-dom'
import { MapPin, Briefcase, AlertCircle } from 'lucide-react'
import { Avatar } from '@/components/ui/Avatar'
import { Button } from '@/components/ui/Button'
import type { JobMetadata } from '@/types/api.types'

interface JobShareCardProps {
  metadata: JobMetadata | null
  isMine?: boolean
}

export function JobShareCard({ metadata, isMine = false }: JobShareCardProps) {
  const navigate = useNavigate()

  // ── State: metadata missing / malformed ────────────────────────────────────
  if (!metadata) {
    return (
      <div className="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500">
        <AlertCircle className="h-4 w-4 flex-shrink-0 text-gray-400" />
        <span>Vaga indisponível</span>
      </div>
    )
  }

  const { job_uuid, title, hotel_name, hotel_logo, location, contract_type, is_active } = metadata

  const cardBase = isMine
    ? 'border-white/20 bg-white/10 text-white'
    : 'border-gray-200 bg-white text-gray-900'

  const mutedText = isMine ? 'text-white/70' : 'text-gray-500'
  const divider   = isMine ? 'border-white/20' : 'border-gray-100'

  return (
    <div className={`relative w-full overflow-hidden rounded-xl border ${cardBase}`}>
      {/* ── Inactive overlay ─────────────────────────────────────────────── */}
      {!is_active && (
        <div className="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-black/30">
          <span className="rounded-full bg-black/60 px-3 py-1 text-xs font-semibold text-white">
            Vaga encerrada
          </span>
        </div>
      )}

      {/* ── Card body ────────────────────────────────────────────────────── */}
      <div className={!is_active ? 'opacity-60' : undefined}>
        {/* Header row: logo + title */}
        <div className="flex items-start gap-3 px-4 pt-4 pb-3">
          <Avatar
            src={hotel_logo}
            name={hotel_name}
            size="md"
            className={isMine ? 'ring-2 ring-white/30' : undefined}
          />
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold leading-snug line-clamp-2">{title}</p>
            <p className={`text-xs mt-0.5 font-medium ${mutedText}`}>{hotel_name}</p>
          </div>
        </div>

        {/* Meta row: location + contract type */}
        {(location || contract_type) && (
          <div className={`flex flex-wrap items-center gap-x-3 gap-y-1 px-4 pb-3 text-xs ${mutedText}`}>
            {location && (
              <span className="flex items-center gap-1">
                <MapPin className="h-3 w-3 flex-shrink-0" />
                {location}
              </span>
            )}
            {contract_type && (
              <span className="flex items-center gap-1">
                <Briefcase className="h-3 w-3 flex-shrink-0" />
                {contract_type}
              </span>
            )}
          </div>
        )}

        {/* Action button */}
        {is_active && (
          <div className={`px-4 pb-4 pt-1 border-t ${divider}`}>
            <Button
              variant={isMine ? 'secondary' : 'primary'}
              size="sm"
              fullWidth
              onClick={() => navigate(`/jobs/${job_uuid}`)}
            >
              Ver vaga
            </Button>
          </div>
        )}
      </div>
    </div>
  )
}
