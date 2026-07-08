import { MapPin, Globe, Phone, Mail } from 'lucide-react'
import type { Hotel } from '@/types/hotel.types'

interface HotelHeaderProps {
  hotel: Hotel
  /** Show edit/upload controls */
  editable?: boolean
  onLogoSelect?: (file: File) => void
  onCoverSelect?: (file: File) => void
  logoLoading?: boolean
  coverLoading?: boolean
}

/**
 * Hotel profile header with logo, cover, name and metadata.
 * Requirements: 3.2, 3.4
 */
export default function HotelHeader({
  hotel,
  editable = false,
  onLogoSelect,
  onCoverSelect,
  logoLoading = false,
  coverLoading = false,
}: HotelHeaderProps) {
  function pickFile(onSelect?: (file: File) => void, accept = 'image/jpeg,image/png,image/webp') {
    if (!onSelect) return
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = accept
    input.onchange = (e) => {
      const file = (e.target as HTMLInputElement).files?.[0]
      if (file) onSelect(file)
    }
    input.click()
  }

  return (
    <div className="overflow-hidden rounded-xl border border-gray-100 bg-white">
      {/* Cover */}
      <div
        className={`relative h-40 bg-gradient-to-r from-primary/20 to-primary/40 ${editable ? 'cursor-pointer hover:opacity-90' : ''}`}
        style={hotel.cover_url ? { backgroundImage: `url(${hotel.cover_url})`, backgroundSize: 'cover', backgroundPosition: 'center' } : undefined}
        onClick={() => editable && pickFile(onCoverSelect)}
      >
        {editable && (
          <div className="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition-opacity hover:opacity-100">
            <span className="text-sm font-medium text-white">
              {coverLoading ? 'Enviando...' : 'Alterar capa'}
            </span>
          </div>
        )}
      </div>

      {/* Logo + info */}
      <div className="px-6 pb-6">
        <div className="-mt-10 flex items-end justify-between">
          {/* Logo */}
          <div
            className={`relative h-20 w-20 overflow-hidden rounded-xl border-4 border-white bg-white shadow-sm ${editable ? 'cursor-pointer' : ''}`}
            onClick={() => editable && pickFile(onLogoSelect)}
          >
            {hotel.logo_url ? (
              <img src={hotel.logo_url} alt={hotel.name} className="h-full w-full object-cover" />
            ) : (
              <div className="flex h-full w-full items-center justify-center bg-primary/10 text-2xl font-bold text-primary">
                {hotel.name.charAt(0).toUpperCase()}
              </div>
            )}
            {editable && (
              <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity hover:opacity-100">
                {logoLoading ? (
                  <div className="h-5 w-5 animate-spin rounded-full border-2 border-white border-t-transparent" />
                ) : (
                  <span className="text-xs font-medium text-white">Logo</span>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Name & description */}
        <div className="mt-3">
          <h1 className="text-2xl font-bold text-gray-900">{hotel.name}</h1>
          {hotel.description && (
            <p className="mt-1 text-sm text-gray-600">{hotel.description}</p>
          )}

          {/* Metadata */}
          <div className="mt-3 flex flex-wrap gap-4 text-sm text-gray-500">
            {(hotel.city || hotel.state) && (
              <span className="flex items-center gap-1">
                <MapPin className="h-4 w-4" />
                {[hotel.city, hotel.state, hotel.country].filter(Boolean).join(', ')}
              </span>
            )}
            {hotel.website && (
              <a
                href={hotel.website}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-1 text-primary hover:underline"
              >
                <Globe className="h-4 w-4" />
                {hotel.website.replace(/^https?:\/\//, '')}
              </a>
            )}
            {hotel.phone && (
              <span className="flex items-center gap-1">
                <Phone className="h-4 w-4" />
                {hotel.phone}
              </span>
            )}
            {hotel.contact_email && (
              <a href={`mailto:${hotel.contact_email}`} className="flex items-center gap-1 hover:underline">
                <Mail className="h-4 w-4" />
                {hotel.contact_email}
              </a>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
