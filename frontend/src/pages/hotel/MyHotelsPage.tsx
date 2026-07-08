import { Link } from 'react-router-dom'
import { Building2, Plus, MapPin } from 'lucide-react'
import { useMyHotels } from '@/hooks/useHotel'
import { CompanyAvatar } from '@/components/common/CompanyAvatar'
import { CompanyLink } from '@/components/common/CompanyLink'

/**
 * Lists hotels where the authenticated user is admin or approved employee.
 * Requirements: 2.3, 2.4
 */
export default function MyHotelsPage() {
  const { data, isLoading } = useMyHotels()
  const hotels = data?.data ?? []

  if (isLoading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-8">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Empresas / Hotéis</h1>
        <Link
          to="/hotels/new"
          className="flex items-center gap-1.5 rounded-full bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition-colors"
        >
          <Plus className="h-4 w-4" />
          Criar Hotel
        </Link>
      </div>

      {hotels.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-2xl border border-gray-100 bg-white py-16 text-center shadow-sm">
          <Building2 className="mb-3 h-10 w-10 text-gray-200" />
          <p className="text-gray-500">Você ainda não gerencia nenhum hotel.</p>
          <Link
            to="/hotels/new"
            className="mt-4 inline-flex items-center gap-1.5 rounded-full bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 transition-colors"
          >
            <Plus className="h-4 w-4" />
            Criar primeiro hotel
          </Link>
        </div>
      ) : (
        <div className="space-y-3">
          {hotels.map((hotel) => (
            <div
              key={hotel.uuid}
              className="flex items-center gap-4 rounded-xl border border-gray-100 bg-white p-4 hover:shadow-sm transition-shadow"
            >
              <CompanyAvatar slug={hotel.slug} name={hotel.name} logoUrl={hotel.logo_url} size="lg" />
              <div className="flex-1 min-w-0">
                <CompanyLink
                  slug={hotel.slug}
                  name={hotel.name}
                  className="text-base font-semibold text-gray-900 hover:text-violet-600"
                />
                {hotel.segment && (
                  <p className="text-sm text-gray-500 mt-0.5">{hotel.segment}</p>
                )}
                {(hotel.city || hotel.state) && (
                  <p className="flex items-center gap-1 text-xs text-gray-400 mt-1">
                    <MapPin className="h-3 w-3" />
                    {[hotel.city, hotel.state].filter(Boolean).join(', ')}
                  </p>
                )}
              </div>
              <Link
                to={`/hotels/${hotel.slug}`}
                className="flex-shrink-0 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors"
              >
                Ver perfil
              </Link>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
