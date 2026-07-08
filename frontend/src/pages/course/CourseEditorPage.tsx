import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { Plus, Trash2, GraduationCap, Sparkles, Send } from 'lucide-react'
import { useCan } from '@/hooks/useSubscription'
import {
  useCourse, useCreateCourse, useUpdateCourse, usePublishCourse, useAddLesson, useDeleteLesson,
} from '@/hooks/useCourses'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import type { CourseLevel } from '@/types/course.types'

const LEVELS: { value: CourseLevel; label: string }[] = [
  { value: 'beginner', label: 'Iniciante' },
  { value: 'intermediate', label: 'Intermediário' },
  { value: 'advanced', label: 'Avançado' },
]

/**
 * Editor de curso (criar/editar + gerenciar aulas). Exclusivo de Consultores PRO
 * (gate create_courses); usuários sem o plano veem o convite de upgrade (§13).
 * Sprint: monetization-hotelconnect-pro (Cursos)
 */
export default function CourseEditorPage() {
  const { uuid } = useParams<{ uuid?: string }>()
  const isEdit = !!uuid
  const navigate = useNavigate()
  const canCreate = useCan('create_courses')

  const { data: course } = useCourse(uuid ?? '')
  const createCourse = useCreateCourse()
  const updateCourse = useUpdateCourse(uuid ?? '')
  const publishCourse = usePublishCourse(uuid ?? '')
  const addLesson = useAddLesson(uuid ?? '')
  const deleteLesson = useDeleteLesson(uuid ?? '')

  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [category, setCategory] = useState('')
  const [level, setLevel] = useState<CourseLevel | ''>('')
  const [deletingLesson, setDeletingLesson] = useState<string | null>(null)

  // Aula em edição
  const [lTitle, setLTitle] = useState('')
  const [lContent, setLContent] = useState('')
  const [lVideo, setLVideo] = useState('')
  const [lDuration, setLDuration] = useState('')

  useEffect(() => {
    if (course) {
      setTitle(course.title)
      setDescription(course.description ?? '')
      setCategory(course.category ?? '')
      setLevel(course.level ?? '')
    }
  }, [course])

  if (!canCreate) {
    return (
      <div className="mx-auto max-w-lg px-4 py-12 text-center">
        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
          <Sparkles className="h-3.5 w-3.5" /> HotelConnect PRO — Consultor
        </span>
        <h1 className="mt-3 text-xl font-semibold text-gray-900">Criar cursos é um recurso PRO</h1>
        <p className="mt-1 text-sm text-gray-500">Assine o plano Consultor para publicar cursos e monetizar seu conhecimento.</p>
        <Button className="mt-4" icon={<Sparkles className="h-4 w-4" />} onClick={() => navigate('/plans')}>
          Conhecer HotelConnect PRO
        </Button>
      </div>
    )
  }

  function handleSaveCourse(e: React.FormEvent) {
    e.preventDefault()
    const payload = { title: title.trim(), description: description.trim() || undefined, category: category.trim() || undefined, level: (level || undefined) as CourseLevel | undefined }
    if (isEdit) {
      updateCourse.mutate(payload)
    } else {
      createCourse.mutate(payload, { onSuccess: (c) => navigate(`/courses/${c.uuid}/edit`) })
    }
  }

  function handleAddLesson(e: React.FormEvent) {
    e.preventDefault()
    if (!lTitle.trim()) return
    addLesson.mutate(
      { title: lTitle.trim(), content: lContent.trim() || undefined, video_url: lVideo.trim() || undefined, duration_seconds: lDuration ? Number(lDuration) * 60 : undefined },
      { onSuccess: () => { setLTitle(''); setLContent(''); setLVideo(''); setLDuration('') } },
    )
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-6">
      <div className="mb-5 flex items-center gap-2">
        <GraduationCap className="h-6 w-6 text-primary" />
        <h1 className="text-2xl font-semibold">{isEdit ? 'Editar curso' : 'Novo curso'}</h1>
      </div>

      {/* Dados do curso */}
      <form onSubmit={handleSaveCourse} className="space-y-4 rounded-2xl border border-gray-100 bg-white p-5">
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Título</label>
          <input value={title} onChange={(e) => setTitle(e.target.value)} maxLength={255} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Ex: Gestão de Recepção Hoteleira" />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Descrição</label>
          <textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Sobre o que é o curso?" />
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Categoria</label>
            <input value={category} onChange={(e) => setCategory(e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Ex: Atendimento" />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Nível</label>
            <select value={level} onChange={(e) => setLevel(e.target.value as CourseLevel | '')} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              <option value="">Selecionar…</option>
              {LEVELS.map((l) => <option key={l.value} value={l.value}>{l.label}</option>)}
            </select>
          </div>
        </div>
        <div className="flex justify-end">
          <Button type="submit" loading={createCourse.isPending || updateCourse.isPending} disabled={!title.trim()}>
            {isEdit ? 'Salvar' : 'Criar curso'}
          </Button>
        </div>
      </form>

      {/* Aulas + publicação (só ao editar) */}
      {isEdit && course && (
        <>
          <div className="mt-6 flex items-center justify-between">
            <h2 className="text-base font-semibold text-gray-900">Aulas ({course.lessons_count})</h2>
            {course.status === 'draft' && (
              <Button size="sm" variant="secondary" icon={<Send className="h-3.5 w-3.5" />} loading={publishCourse.isPending} onClick={() => publishCourse.mutate()}>
                Publicar curso
              </Button>
            )}
            {course.status === 'published' && <span className="rounded bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Publicado</span>}
          </div>

          <div className="mt-3 space-y-2">
            {(course.lessons ?? []).map((lesson, i) => (
              <div key={lesson.uuid} className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-3">
                <span className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">{i + 1}</span>
                <span className="min-w-0 flex-1 truncate text-sm text-gray-800">{lesson.title}</span>
                <button onClick={() => setDeletingLesson(lesson.uuid)} aria-label="Remover aula" className="rounded p-1 text-gray-300 hover:bg-red-50 hover:text-red-500">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ))}
          </div>

          {/* Adicionar aula */}
          <form onSubmit={handleAddLesson} className="mt-3 space-y-3 rounded-2xl border border-dashed border-gray-300 p-4">
            <p className="flex items-center gap-1 text-sm font-medium text-gray-700"><Plus className="h-4 w-4" /> Adicionar aula</p>
            <input value={lTitle} onChange={(e) => setLTitle(e.target.value)} placeholder="Título da aula" className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
            <textarea value={lContent} onChange={(e) => setLContent(e.target.value)} rows={2} placeholder="Conteúdo / descrição da aula" className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
            <div className="grid grid-cols-[1fr_auto] gap-3">
              <input value={lVideo} onChange={(e) => setLVideo(e.target.value)} placeholder="Link do vídeo (opcional)" className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
              <input value={lDuration} onChange={(e) => setLDuration(e.target.value)} type="number" min="0" placeholder="min" className="w-20 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
            </div>
            <div className="flex justify-end">
              <Button type="submit" size="sm" loading={addLesson.isPending} disabled={!lTitle.trim()}>Adicionar aula</Button>
            </div>
          </form>
        </>
      )}

      <ConfirmDialog
        open={!!deletingLesson}
        title="Remover aula"
        description="Tem certeza que deseja remover esta aula?"
        confirmLabel="Remover"
        variant="danger"
        loading={deleteLesson.isPending}
        onConfirm={() => { if (deletingLesson) deleteLesson.mutate(deletingLesson, { onSuccess: () => setDeletingLesson(null) }) }}
        onCancel={() => setDeletingLesson(null)}
      />
    </div>
  )
}
