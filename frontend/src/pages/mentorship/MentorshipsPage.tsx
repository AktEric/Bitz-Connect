import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Handshake, Plus, Search, X } from 'lucide-react'
import { useMentorships } from '@/hooks/useMentorships'
import { useCan } from '@/hooks/useSubscription'
import { MentorshipCard } from '@/components/mentorship/MentorshipCard'
import { UpgradeModal } from '@/components/subscription/UpgradeModal'
import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { SkeletonCard } from '@/components/ui/Skeleton'

/**
 * Catálogo de mentorias (§11), pesquisável. Consultores PRO podem ofertar.
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
export default function MentorshipsPage() {
  const canCreate = useCan('sell_mentorships')
  const navigate = useNavigate()
  const [upgradeOpen, setUpgradeOpen] = useState(false)

  const [search, setSearch] = useState('')
  const [debounced, setDebounced] = useState('')
  useEffect(() => {
    const id = setTimeout(() => setDebounced(search.trim()), 300)
    return () => clearTimeout(id)
  }, [search])

  const { data, isLoading, isFetching } = useMentorships(debounced ? { q: debounced } : {})
  const mentorships = data?.data ?? []

  return (
    <div className="mx-auto max-w-5xl px-4 py-6">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
            <Handshake className="h-6 w-6 text-primary" /> Mentorias
          </h1>
          <p className="mt-0.5 text-sm text-gray-500">Sessões individuais com consultores do setor.</p>
        </div>
        <Button icon={<Plus className="h-4 w-4" />} onClick={() => (canCreate ? navigate('/mentorships/new') : setUpgradeOpen(true))}>
          Oferecer mentoria
        </Button>
      </div>

      <div className="relative mb-5">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Buscar mentorias…"
          className="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-10 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
        />
        {search && (
          <button onClick={() => setSearch('')} aria-label="Limpar" className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <X className="h-4 w-4" />
          </button>
        )}
      </div>

      {isLoading || (isFetching && debounced) ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"><SkeletonCard /><SkeletonCard /><SkeletonCard /></div>
      ) : mentorships.length === 0 ? (
        debounced ? (
          <EmptyState title="Nenhuma mentoria encontrada" description={`Nada para "${debounced}".`} icon={<Search className="h-8 w-8" />} />
        ) : (
          <EmptyState
            title="Nenhuma mentoria publicada ainda"
            description="As mentorias dos Consultores PRO aparecerão aqui."
            icon={<Handshake className="h-8 w-8" />}
            action={{ label: canCreate ? 'Oferecer a primeira' : 'Conhecer o PRO', onClick: () => (canCreate ? navigate('/mentorships/new') : setUpgradeOpen(true)) }}
          />
        )
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {mentorships.map((m) => <MentorshipCard key={m.uuid} mentorship={m} />)}
        </div>
      )}

      <UpgradeModal
        open={upgradeOpen}
        onClose={() => setUpgradeOpen(false)}
        title="Ofereça mentorias"
        description="A oferta de mentorias é exclusiva do HotelConnect PRO - Consultor."
      />
    </div>
  )
}
