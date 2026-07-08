import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { useCreateHotel } from '@/hooks/useHotel'

const schema = z.object({
  name:          z.string().min(2, 'Nome deve ter ao menos 2 caracteres').max(255),
  description:   z.string().min(10, 'Descrição deve ter ao menos 10 caracteres').max(5000),
  city:          z.string().max(100).optional(),
  state:         z.string().max(100).optional(),
  country:       z.string().max(100).optional(),
  website:       z.string().url('URL inválida').or(z.literal('')).optional(),
  phone:         z.string().max(30).optional(),
  contact_email: z.string().email('E-mail inválido').or(z.literal('')).optional(),
})

type FormValues = z.infer<typeof schema>

/**
 * Page to create a new hotel profile.
 * Requirements: 3.1, 3.2
 */
export default function CreateHotelPage() {
  const navigate = useNavigate()
  const createHotel = useCreateHotel()

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  async function onSubmit(values: FormValues) {
    const res = await createHotel.mutateAsync({
      name:          values.name,
      description:   values.description,
      city:          values.city  || undefined,
      state:         values.state || undefined,
      country:       values.country || undefined,
      website:       values.website || undefined,
      phone:         values.phone || undefined,
      contact_email: values.contact_email || undefined,
    })
    navigate(`/hotels/${res.data.slug}`)
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <button
        onClick={() => navigate(-1)}
        className="mb-6 flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
      >
        <ArrowLeft className="h-4 w-4" />
        Voltar
      </button>

      <h1 className="mb-6 text-2xl font-bold text-gray-900">Criar hotel</h1>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
        {/* Name */}
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">
            Nome do hotel <span className="text-red-500">*</span>
          </label>
          <input
            {...register('name')}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="Ex: Grand Hotel Lisboa"
          />
          {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name.message}</p>}
        </div>

        {/* Description */}
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">
            Descrição <span className="text-red-500">*</span>
          </label>
          <textarea
            {...register('description')}
            rows={4}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="Descreva o hotel, sua história e diferenciais..."
          />
          {errors.description && <p className="mt-1 text-xs text-red-500">{errors.description.message}</p>}
        </div>

        {/* Location */}
        <div className="grid grid-cols-3 gap-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Cidade</label>
            <input
              {...register('city')}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Estado</label>
            <input
              {...register('state')}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">País</label>
            <input
              {...register('country')}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
              placeholder="Brasil"
            />
          </div>
        </div>

        {/* Website */}
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Website</label>
          <input
            {...register('website')}
            type="url"
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="https://..."
          />
          {errors.website && <p className="mt-1 text-xs text-red-500">{errors.website.message}</p>}
        </div>

        {/* Phone & email */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Telefone</label>
            <input
              {...register('phone')}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
              placeholder="+55 11 9 9999-9999"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">E-mail de contato</label>
            <input
              {...register('contact_email')}
              type="email"
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
              placeholder="contato@hotel.com"
            />
            {errors.contact_email && (
              <p className="mt-1 text-xs text-red-500">{errors.contact_email.message}</p>
            )}
          </div>
        </div>

        {createHotel.isError && (
          <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
            Erro ao criar hotel. Verifique os dados e tente novamente.
          </p>
        )}

        <div className="flex justify-end gap-2 pt-2">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Cancelar
          </button>
          <button
            type="submit"
            disabled={isSubmitting || createHotel.isPending}
            className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50"
          >
            {isSubmitting || createHotel.isPending ? 'Criando...' : 'Criar hotel'}
          </button>
        </div>
      </form>
    </div>
  )
}
