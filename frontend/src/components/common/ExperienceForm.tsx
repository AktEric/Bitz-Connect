import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useState } from 'react'
import type { Experience, CreateExperienceData } from '@/types/user.types'
import { useSearch } from '@/hooks/useSearch'

const schema = z
  .object({
    title: z.string().min(1, 'Cargo obrigatório').max(255),
    company: z.string().min(1, 'Empresa obrigatória').max(255),
    started_at: z.string().min(1, 'Data de início obrigatória'),
    ended_at: z.string().optional(),
    description: z.string().max(2000).optional(),
  })
  .refine(
    (d) => !d.ended_at || d.ended_at >= d.started_at,
    { message: 'Data de término deve ser igual ou posterior ao início', path: ['ended_at'] },
  )

type FormValues = z.infer<typeof schema>

interface ExperienceFormProps {
  initial?: Experience
  onSubmit: (data: CreateExperienceData) => void
  onCancel: () => void
  loading?: boolean
}

/**
 * Form to create or edit a professional experience.
 * Requirements: 2.5
 */
export default function ExperienceForm({
  initial,
  onSubmit,
  onCancel,
  loading = false,
}: ExperienceFormProps) {
  const [hotelSearch, setHotelSearch] = useState('')
  const [selectedHotelName, setSelectedHotelName] = useState(initial?.hotel?.name ?? '')
  // Guardado como estado React explícito (não via react-hook-form) porque não
  // há um <input> registrado para este campo — setValue em um campo nunca
  // registrado não é incluído de forma confiável no payload do handleSubmit.
  const [selectedHotelUuid, setSelectedHotelUuid] = useState<string | null>(initial?.hotel?.uuid ?? null)
  const [showDropdown, setShowDropdown] = useState(false)

  const { debouncedQuery, data: searchData } = useSearch(hotelSearch, 'hotels', 1)
  const hotelResults = (searchData?.data ?? []) as any[]

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: initial
      ? {
          title: initial.title,
          company: initial.company,
          started_at: initial.started_at,
          ended_at: initial.ended_at ?? '',
          description: initial.description ?? '',
        }
      : undefined,
  })

  function handleSelectHotel(uuid: string, name: string) {
    setSelectedHotelName(name)
    setSelectedHotelUuid(uuid)
    setHotelSearch('')
    setShowDropdown(false)
  }

  function handleClearHotel() {
    setSelectedHotelName('')
    setSelectedHotelUuid(null)
  }

  function submit(formData: FormValues) {
    onSubmit({ ...formData, hotel_uuid: selectedHotelUuid })
  }

  return (
    <form onSubmit={handleSubmit(submit)} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Cargo</label>
          <input
            {...register('title')}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="Ex: Gerente de Recepção"
          />
          {errors.title && <p className="mt-1 text-xs text-red-500">{errors.title.message}</p>}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Empresa / Hotel</label>
          <input
            {...register('company')}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="Ex: Grand Hotel SP"
          />
          {errors.company && <p className="mt-1 text-xs text-red-500">{errors.company.message}</p>}
        </div>

        {/* Hotel selector */}
        <div className="sm:col-span-2">
          <label className="mb-1 block text-sm font-medium text-gray-700">
            Vincular a hotel cadastrado <span className="text-xs text-gray-400">(opcional)</span>
          </label>
          {selectedHotelName ? (
            <div className="flex items-center gap-2 rounded-md border border-brand-300 bg-brand-50 px-3 py-2">
              <span className="flex-1 text-sm text-gray-800">{selectedHotelName}</span>
              <button
                type="button"
                onClick={handleClearHotel}
                className="text-xs text-gray-400 hover:text-red-500 transition-colors"
              >
                Remover
              </button>
            </div>
          ) : (
            <div className="relative">
              <input
                type="text"
                value={hotelSearch}
                onChange={(e) => { setHotelSearch(e.target.value); setShowDropdown(true) }}
                onFocus={() => setShowDropdown(true)}
                onBlur={() => setTimeout(() => setShowDropdown(false), 200)}
                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                placeholder="Buscar hotel pelo nome..."
              />
              {showDropdown && debouncedQuery.length > 0 && hotelResults.length > 0 && (
                <div className="absolute z-10 mt-1 w-full rounded-lg border border-gray-100 bg-white shadow-lg">
                  {hotelResults.slice(0, 5).map((h: any) => (
                    <button
                      key={h.uuid}
                      type="button"
                      onMouseDown={() => handleSelectHotel(h.uuid, h.name)}
                      className="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                      {h.name}
                      {h.city && <span className="ml-2 text-xs text-gray-400">{h.city}</span>}
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Início</label>
          <input
            {...register('started_at')}
            type="date"
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          />
          {errors.started_at && <p className="mt-1 text-xs text-red-500">{errors.started_at.message}</p>}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Término (opcional)</label>
          <input
            {...register('ended_at')}
            type="date"
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          />
          {errors.ended_at && <p className="mt-1 text-xs text-red-500">{errors.ended_at.message}</p>}
        </div>
      </div>

      <div>
        <label className="mb-1 block text-sm font-medium text-gray-700">Descrição</label>
        <textarea
          {...register('description')}
          rows={3}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          placeholder="Descreva suas responsabilidades e conquistas..."
        />
        {errors.description && <p className="mt-1 text-xs text-red-500">{errors.description.message}</p>}
      </div>

      <div className="flex justify-end gap-2">
        <button
          type="button"
          onClick={onCancel}
          className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          Cancelar
        </button>
        <button
          type="submit"
          disabled={loading}
          className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50"
        >
          {loading ? 'Salvando...' : initial ? 'Salvar alterações' : 'Adicionar'}
        </button>
      </div>
    </form>
  )
}
