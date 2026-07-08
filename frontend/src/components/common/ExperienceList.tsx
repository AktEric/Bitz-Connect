import { useState } from 'react'
import { Pencil, Trash2, Plus } from 'lucide-react'
import { format } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import type { Experience, CreateExperienceData } from '@/types/user.types'
import ExperienceForm from './ExperienceForm'

interface ExperienceListProps {
  experiences: Experience[]
  editable?: boolean
  onAdd?: (data: CreateExperienceData) => void
  onUpdate?: (id: number, data: Partial<CreateExperienceData>) => void
  onDelete?: (id: number) => void
  mutating?: boolean
}

function formatDate(dateStr: string | null) {
  if (!dateStr) return 'Presente'
  return format(new Date(dateStr), 'MMM yyyy', { locale: ptBR })
}

/**
 * Renders a list of professional experiences with optional edit/delete actions.
 * Requirements: 2.5, 2.6
 */
export default function ExperienceList({
  experiences,
  editable = false,
  onAdd,
  onUpdate,
  onDelete,
  mutating = false,
}: ExperienceListProps) {
  const [addingNew, setAddingNew] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-base font-semibold text-gray-900">Experiência Profissional</h3>
        {editable && (
          <button
            onClick={() => setAddingNew(true)}
            className="flex items-center gap-1 rounded-md px-2 py-1 text-sm text-primary hover:bg-primary/5"
          >
            <Plus className="h-4 w-4" />
            Adicionar
          </button>
        )}
      </div>

      {addingNew && (
        <div className="rounded-lg border border-dashed border-gray-300 p-4">
          <ExperienceForm
            onSubmit={(data) => {
              onAdd?.(data)
              setAddingNew(false)
            }}
            onCancel={() => setAddingNew(false)}
            loading={mutating}
          />
        </div>
      )}

      {experiences.length === 0 && !addingNew && (
        <p className="text-sm text-gray-500">Nenhuma experiência adicionada ainda.</p>
      )}

      {experiences.map((exp) => (
        <div key={exp.id} className="relative rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
          {editingId === exp.id ? (
            <ExperienceForm
              initial={exp}
              onSubmit={(data) => {
                onUpdate?.(exp.id, data)
                setEditingId(null)
              }}
              onCancel={() => setEditingId(null)}
              loading={mutating}
            />
          ) : (
            <>
              <div className="flex items-start justify-between gap-2">
                <div>
                  <p className="font-medium text-gray-900">{exp.title}</p>
                  <p className="text-sm text-gray-600">{exp.company}</p>
                  <p className="text-xs text-gray-400">
                    {formatDate(exp.started_at)} – {formatDate(exp.ended_at)}
                  </p>
                  {exp.description && (
                    <p className="mt-1 text-sm text-gray-600">{exp.description}</p>
                  )}
                </div>

                {editable && (
                  <div className="flex gap-1">
                    <button
                      onClick={() => setEditingId(exp.id)}
                      className="rounded p-1 text-gray-400 hover:text-primary"
                      aria-label="Editar experiência"
                    >
                      <Pencil className="h-4 w-4" />
                    </button>
                    <button
                      onClick={() => onDelete?.(exp.id)}
                      className="rounded p-1 text-gray-400 hover:text-red-500"
                      aria-label="Remover experiência"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                )}
              </div>
            </>
          )}
        </div>
      ))}
    </div>
  )
}
