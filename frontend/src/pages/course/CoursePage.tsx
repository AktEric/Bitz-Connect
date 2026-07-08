import { useParams, Link, useNavigate } from 'react-router-dom'
import { GraduationCap, Lock, PlayCircle, Users, Pencil, CheckCircle } from 'lucide-react'
import { useCourse, useEnrollCourse } from '@/hooks/useCourses'
import { Button } from '@/components/ui/Button'
import { Avatar } from '@/components/ui/Avatar'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import { ReviewList } from '@/components/review/ReviewList'

function fmtDuration(seconds: number | null): string | null {
  if (!seconds || seconds <= 0) return null
  const m = Math.round(seconds / 60)
  return `${m} min`
}

/**
 * Detalhe do curso (§11): cabeçalho, criador, aulas (bloqueadas até matricular)
 * e ação de matrícula/gestão.
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
export default function CoursePage() {
  const { uuid = '' } = useParams<{ uuid: string }>()
  const navigate = useNavigate()
  const { data: course, isLoading } = useCourse(uuid)
  const enroll = useEnrollCourse(uuid)

  if (isLoading) {
    return <div className="flex h-64 items-center justify-center"><div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" /></div>
  }
  if (!course) return null

  return (
    <div className="mx-auto max-w-3xl px-4 py-6">
      {/* Header */}
      <div className="overflow-hidden rounded-2xl border border-gray-100 bg-white">
        <div className="relative aspect-[3/1] w-full bg-gradient-to-br from-violet-500 to-indigo-600">
          {course.thumbnail_url
            ? <img src={course.thumbnail_url} alt={course.title} className="h-full w-full object-cover" />
            : <div className="flex h-full w-full items-center justify-center"><GraduationCap className="h-12 w-12 text-white/70" /></div>}
        </div>

        <div className="p-5">
          {course.status === 'draft' && (
            <span className="mb-2 inline-block rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Rascunho</span>
          )}
          <h1 className="text-2xl font-bold text-gray-900">{course.title}</h1>

          {course.creator && (
            <Link to={`/creators/${course.creator.uuid}`} className="mt-2 flex items-center gap-2">
              <Avatar src={course.creator.avatar_url} name={course.creator.name} size="sm" />
              <span className="text-sm font-medium text-gray-700">{course.creator.name}</span>
              <PremiumBadge plan={course.creator.plan} compact />
            </Link>
          )}

          {course.description && <p className="mt-3 text-sm text-gray-600 leading-relaxed">{course.description}</p>}

          <div className="mt-3 flex items-center gap-4 text-sm text-gray-500">
            <span className="flex items-center gap-1"><PlayCircle className="h-4 w-4" />{course.lessons_count} aulas</span>
            <span className="flex items-center gap-1"><Users className="h-4 w-4" />{course.enrollments_count} matriculados</span>
            <span className="font-semibold text-gray-900">
              {course.price_cents > 0
                ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(course.price_cents / 100)
                : 'Gratuito'}
            </span>
          </div>

          {/* Ação */}
          <div className="mt-4">
            {course.is_owner ? (
              <Button variant="outline" icon={<Pencil className="h-4 w-4" />} onClick={() => navigate(`/courses/${course.uuid}/edit`)}>
                Gerenciar curso
              </Button>
            ) : course.is_enrolled ? (
              <span className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                <CheckCircle className="h-4 w-4" /> Você está matriculado
              </span>
            ) : (
              <Button loading={enroll.isPending} onClick={() => enroll.mutate()}>
                {course.price_cents > 0 ? 'Matricular' : 'Matricular gratuitamente'}
              </Button>
            )}
          </div>
        </div>
      </div>

      {/* Conteúdo / aulas */}
      <h2 className="mt-6 mb-3 text-base font-semibold text-gray-900">Conteúdo do curso</h2>
      <div className="space-y-2">
        {(course.lessons ?? []).map((lesson, i) => (
          <div key={lesson.uuid} className="rounded-xl border border-gray-100 bg-white p-4">
            <div className="flex items-start gap-3">
              <span className="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">
                {i + 1}
              </span>
              <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between gap-2">
                  <h3 className="text-sm font-semibold text-gray-900">{lesson.title}</h3>
                  {lesson.locked
                    ? <Lock className="h-4 w-4 flex-shrink-0 text-gray-300" />
                    : fmtDuration(lesson.duration_seconds) && <span className="flex-shrink-0 text-xs text-gray-400">{fmtDuration(lesson.duration_seconds)}</span>}
                </div>
                {lesson.description && <p className="mt-0.5 text-xs text-gray-500">{lesson.description}</p>}

                {lesson.locked ? (
                  <p className="mt-1 text-xs text-gray-400">Matricule-se para acessar esta aula.</p>
                ) : (
                  <div className="mt-2 space-y-2">
                    {lesson.content && <p className="whitespace-pre-line text-sm text-gray-600">{lesson.content}</p>}
                    {lesson.video_url && (
                      <a href={lesson.video_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                        <PlayCircle className="h-4 w-4" /> Assistir aula
                      </a>
                    )}
                  </div>
                )}
              </div>
            </div>
          </div>
        ))}
        {(course.lessons ?? []).length === 0 && (
          <p className="rounded-xl border border-gray-100 bg-white p-6 text-center text-sm text-gray-400">
            Este curso ainda não tem aulas.
          </p>
        )}
      </div>

      <h2 className="mt-8 mb-3 text-base font-semibold text-gray-900">Avaliações</h2>
      <ReviewList type="course" uuid={course.uuid} />
    </div>
  )
}
