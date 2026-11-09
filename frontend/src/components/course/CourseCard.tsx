import { Link } from 'react-router-dom'
import { GraduationCap, PlayCircle, Users, Star } from 'lucide-react'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import type { Course } from '@/types/course.types'

interface CourseCardProps {
  course: Course
}

const LEVEL_LABELS: Record<string, string> = {
  beginner: 'Iniciante',
  intermediate: 'Intermediário',
  advanced: 'Avançado',
}

/**
 * Card de curso reutilizável (§12).
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
export function CourseCard({ course }: CourseCardProps) {
  return (
    <Link
      to={`/courses/${course.uuid}`}
      className="group flex flex-col overflow-hidden rounded-xl border border-gray-100 bg-white transition-shadow hover:shadow-md"
    >
      <div className="relative aspect-video w-full overflow-hidden bg-gradient-to-br from-brand-500 to-ink-900">
        {course.thumbnail_url ? (
          <img src={course.thumbnail_url} alt={course.title} className="h-full w-full object-cover" />
        ) : (
          <div className="flex h-full w-full items-center justify-center">
            <GraduationCap className="h-10 w-10 text-white/70" />
          </div>
        )}
        {course.category && (
          <span className="absolute left-2 top-2 rounded bg-black/50 px-1.5 py-0.5 text-[10px] font-medium text-white">
            {course.category}
          </span>
        )}
      </div>

      <div className="flex flex-1 flex-col p-3">
        <h3 className="line-clamp-2 text-sm font-semibold text-gray-900">{course.title}</h3>
        {course.creator && (
          <div className="mt-1 flex items-center gap-1 text-xs text-gray-500">
            <span className="truncate">{course.creator.name}</span>
            <PremiumBadge plan={course.creator.plan} compact />
          </div>
        )}
        <div className="mt-2 flex items-center gap-3 text-[11px] text-gray-400">
          <span className="flex items-center gap-0.5"><PlayCircle className="h-3 w-3" />{course.lessons_count} aulas</span>
          <span className="flex items-center gap-0.5"><Users className="h-3 w-3" />{course.enrollments_count}</span>
          {course.reviews_count > 0 && (
            <span className="flex items-center gap-0.5"><Star className="h-3 w-3 fill-amber-400 text-amber-400" />{course.rating_avg}</span>
          )}
          {course.level && <span>· {LEVEL_LABELS[course.level] ?? course.level}</span>}
        </div>
        <div className="mt-2 text-sm font-semibold text-gray-900">
          {course.price_cents > 0
            ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(course.price_cents / 100)
            : 'Gratuito'}
        </div>
      </div>
    </Link>
  )
}
