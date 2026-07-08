import { useState } from 'react'
import { Pencil, Trash2, Plus, Building2, CheckCircle } from 'lucide-react'
import { format, formatDistance } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import type { Experience, CreateExperienceData } from '@/types/user.types'
import ExperienceForm from '@/components/common/ExperienceForm'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'

interface Props {
  experiences: Experience[]
  editable?: boolean
  onAdd?: (data: CreateExperienceData) => void
  onUpdate?: (id: number, data: Partial<CreateExperienceData>) => void
  onDelete?: (id: number) => void
  mutating?: boolean
}

function fmtDate(d: string | null) {
  if (!d) return 'Presente'
  return format(new Date(d), 'MMM yyyy', { locale: ptBR })
}

function duration(start: string, end: string | null): string {
  const s = new Date(start)
  const e = end ? new Date(end) : new Date()
  return formatDistance(s, e, { locale: ptBR })
}

export default function ExperienceTimeline({
  experiences,
  editable = false,
  onAdd,
  onUpdate,
  onDelete,
  mutating = false,
}: Props) {
  const [addingNew, setAddingNew] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [deletingExp, setDeletingExp] = useState<Experience | null>(null)

  return (
    <div>
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-base font-semibold text-gray-900">Experiência</h3>
        {editable && (
          <button onClick={() => setAddingNew(true)}
            className="flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 hover:text-primary transition-colors">
            <Plus className="h-3.5 w-3.5" /> Adicionar
          </button>
        )}
      </div>

      {addingNew && (
        <div className="mb-4 rounded-xl border border-dashed border-gray-300 p-4">
          <ExperienceForm
            onSubmit={(data) => { onAdd?.(data); setAddingNew(false) }}
            onCancel={() => setAddingNew(false)}
            loading={mutating}
          />
        </div>
      )}

      {experiences.length === 0 && !addingNew && (
        <p className="text-sm text-gray-400 py-4">Nenhuma experiência cadastrada.</p>
      )}

      <div className="relative">
        {/* Timeline vertical line */}
        {experiences.length > 1 && (
          <div className="absolute left-5 top-6 bottom-6 w-px bg-gray-200" />
        )}

        <div className="space-y-0">
          {experiences.map((exp) => (
            <div key={exp.id} className="relative flex gap-4 pb-5 last:pb-0">
              {/* Circle on timeline */}
              <div className="relative z-10 flex-shrink-0">
                <div className="flex h-10 w-10 items-center justify-center rounded-full border-2 border-gray-200 bg-white">
                  <Building2 className="h-4 w-4 text-gray-400" />
                </div>
              </div>

              {/* Content */}
              <div className="flex-1 min-w-0 pt-1">
                {editingId === exp.id ? (
                  <div className="rounded-xl border border-dashed border-gray-300 p-4">
                    <ExperienceForm
                      initial={exp}
                      onSubmit={(data) => { onUpdate?.(exp.id, data); setEditingId(null) }}
                      onCancel={() => setEditingId(null)}
                      loading={mutating}
                    />
                  </div>
                ) : (
                  <div className="group rounded-xl border border-gray-100 bg-white p-4 hover:shadow-sm transition-shadow">
                    <div className="flex items-start justify-between gap-2">
                      <div className="min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                          <p className="font-semibold text-gray-900 leading-tight">{exp.title}</p>
                          {exp.verified && (
                            <span className="flex items-center gap-1 rounded-full bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-600">
                              <CheckCircle className="h-3 w-3" />
                              Verificado
                            </span>
                          )}
                        </div>
                        <p className="text-sm text-gray-600 mt-0.5">{exp.company}</p>
                        <p className="text-xs text-gray-400 mt-1">
                          {fmtDate(exp.started_at)} – {fmtDate(exp.ended_at)}
                          <span className="ml-1 text-gray-300">·</span>
                          <span className="ml-1">{duration(exp.started_at, exp.ended_at)}</span>
                        </p>
                        {exp.description && (
                          <p className="mt-2 text-sm text-gray-600 leading-relaxed">{exp.description}</p>
                        )}
                      </div>
                      {editable && (
                        <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                          <button onClick={() => setEditingId(exp.id)}
                            className="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-primary transition-colors">
                            <Pencil className="h-3.5 w-3.5" />
                          </button>
                          <button onClick={() => setDeletingExp(exp)}
                            className="rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors">
                            <Trash2 className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>

      <ConfirmDialog
        open={!!deletingExp}
        title="Remover experiência"
        description={`Tem certeza que deseja remover "${deletingExp?.title ?? ''}"? Esta ação não pode ser desfeita.`}
        confirmLabel="Remover"
        variant="danger"
        loading={mutating}
        onConfirm={() => { if (deletingExp) { onDelete?.(deletingExp.id); setDeletingExp(null) } }}
        onCancel={() => setDeletingExp(null)}
      />
    </div>
  )
}
