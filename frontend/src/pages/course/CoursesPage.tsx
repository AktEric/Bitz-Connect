import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { GraduationCap, Plus, Search, X } from 'lucide-react'
import { useCourses } from '@/hooks/useCourses'
import { useCan } from '@/hooks/useSubscription'
import { CourseCard } from '@/components/course/CourseCard'
import { UpgradeModal } from '@/components/subscription/UpgradeModal'
import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { SkeletonCard } from '@/components/ui/Skeleton'

/**
 * Catálogo de cursos (§11). Consultores PRO podem criar; demais veem o convite.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
export default function CoursesPage() {
  const canCreate = useCan('create_courses')
  const navigate = useNavigate()
  const [upgradeOpen, setUpgradeOpen] = useState(false)

  // Busca com debounce
  const [search, setSearch] = useState('')
  const [debounced, setDebounced] = useState('')
  useEffect(() => {
    const id = setTimeout(() => setDebounced(search.trim()), 300)
    return () => clearTimeout(id)
  }, [search])

  const { data, isLoading, isFetching } = useCourses(debounced ? { q: debounced } : {})
  const courses = data?.data ?? []

  return (
    <div className="mx-auto max-w-5xl px-4 py-6">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="flex items-center gap-2 text-2xl font-bold text-gray-900">
            <GraduationCap className="h-6 w-6 text-primary" /> Cursos
          </h1>
          <p className="mt-0.5 text-sm text-gray-500">Aprenda com os consultores do setor hoteleiro.</p>
        </div>
        <Button
          icon={<Plus className="h-4 w-4" />}
          onClick={() => (canCreate ? navigate('/courses/new') : setUpgradeOpen(true))}
        >
          Criar curso
        </Button>
      </div>

      {/* Busca */}
      <div className="relative mb-5">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Buscar cursos por título ou descrição…"
          className="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-10 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
        />
        {search && (
          <button onClick={() => setSearch('')} aria-label="Limpar" className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <X className="h-4 w-4" />
          </button>
        )}
      </div>

      {isLoading || (isFetching && debounced) ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <SkeletonCard /><SkeletonCard /><SkeletonCard />
        </div>
      ) : courses.length === 0 ? (
        debounced ? (
          <EmptyState
            title="Nenhum curso encontrado"
            description={`Não encontramos cursos para "${debounced}". Tente outros termos.`}
            icon={<Search className="h-8 w-8" />}
          />
        ) : (
          <EmptyState
            title="Nenhum curso publicado ainda"
            description="Os cursos dos Consultores PRO aparecerão aqui."
            icon={<GraduationCap className="h-8 w-8" />}
            action={{ label: canCreate ? 'Criar o primeiro' : 'Conhecer o PRO', onClick: () => (canCreate ? navigate('/courses/new') : setUpgradeOpen(true)) }}
          />
        )
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {courses.map((c) => <CourseCard key={c.uuid} course={c} />)}
        </div>
      )}

      <UpgradeModal
        open={upgradeOpen}
        onClose={() => setUpgradeOpen(false)}
        title="Crie e venda cursos"
        description="A criação de cursos é exclusiva do HotelConnect PRO - Consultor."
      />
    </div>
  )
}
