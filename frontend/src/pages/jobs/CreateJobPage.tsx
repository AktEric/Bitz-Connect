import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { useCreateJob } from '@/hooks/useJobs'
import { useMyHotels } from '@/hooks/useHotel'

const schema = z.object({
  hotel_uuid:    z.string().min(1, 'Selecione um hotel'),
  title:         z.string().min(2, 'Título deve ter ao menos 2 caracteres').max(255),
  description:   z.string().min(20, 'Descrição deve ter ao menos 20 caracteres').max(10000),
  requirements:  z.string().max(5000).optional(),
  benefits:      z.string().max(5000).optional(),
  contract_type: z.enum(['clt', 'pj', 'freelance', 'internship'], {
    required_error: 'Selecione o tipo de contrato',
  }),
  modality:      z.enum(['on_site', 'remote', 'hybrid'], {
    required_error: 'Selecione a modalidade',
  }),
  city:          z.string().min(1, 'Cidade é obrigatória').max(100),
  state:         z.string().min(1, 'Estado é obrigatório').max(100),
  salary_range:  z.string().max(100).optional(),
})

type FormValues = z.infer<typeof schema>

const CONTRACT_OPTIONS = [
  { value: 'clt',        label: 'CLT' },
  { value: 'pj',         label: 'PJ' },
  { value: 'freelance',  label: 'Freelance' },
  { value: 'internship', label: 'Estágio' },
]

const MODALITY_OPTIONS = [
  { value: 'on_site', label: 'Presencial' },
  { value: 'remote',  label: 'Remoto' },
  { value: 'hybrid',  label: 'Híbrido' },
]

/**
 * Page for hotel admins to publish a new job opening.
 * Requirements: 5.1, 5.2, 5.3
 */
export default function CreateJobPage() {
  const navigate = useNavigate()
  const createJob = useCreateJob()
  const { data: myHotels, isLoading: loadingHotels } = useMyHotels()

  const hotels = myHotels?.data ?? []

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      hotel_uuid: hotels.length === 1 ? hotels[0].uuid : '',
    },
  })

  async function onSubmit(values: FormValues) {
    const res = await createJob.mutateAsync({
      hotel_uuid:    values.hotel_uuid,
      title:         values.title,
      description:   values.description,
      requirements:  values.requirements  || undefined,
      benefits:      values.benefits      || undefined,
      contract_type: values.contract_type,
      modality:      values.modality,
      city:          values.city,
      state:         values.state,
      salary_range:  values.salary_range  || undefined,
    })
    navigate(`/jobs/${res.data.uuid}`)
  }

  if (loadingHotels) {
    return (
      <div className="flex h-64 items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }

  if (hotels.length === 0) {
    return (
      <div className="mx-auto max-w-xl px-4 py-16 text-center">
        <p className="text-gray-600">
          Você precisa ter um hotel cadastrado para publicar vagas.
        </p>
        <button
          onClick={() => navigate('/hotels/new')}
          className="mt-4 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90"
        >
          Criar hotel
        </button>
      </div>
    )
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

      <h1 className="mb-6 text-2xl font-bold text-gray-900">Publicar vaga</h1>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">

        {/* Hotel selector (only shown when admin has multiple hotels) */}
        {hotels.length > 1 && (
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
              Hotel <span className="text-red-500">*</span>
            </label>
            <select
              {...register('hotel_uuid')}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            >
              <option value="">Selecione um hotel...</option>
              {hotels.map((h) => (
                <option key={h.uuid} value={h.uuid}>
                  {h.name}
                </option>
              ))}
            </select>
            {errors.hotel_uuid && (
              <p className="mt-1 text-xs text-red-500">{errors.hotel_uuid.message}</p>
            )}
          </div>
        )}

        {/* Hidden hotel_uuid when only one hotel */}
        {hotels.length === 1 && (
          <input type="hidden" {...register('hotel_uuid')} value={hotels[0].uuid} />
        )}

        {/* Title */}
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">
            Título da vaga <span className="text-red-500">*</span>
          </label>
          <input
            {...register('title')}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="Ex: Recepcionista, Governanta, Chef de Cozinha..."
          />
          {errors.title && (
            <p className="mt-1 text-xs text-red-500">{errors.title.message}</p>
          )}
        </div>

        {/* Contract type & modality */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
              Tipo de contrato <span className="text-red-500">*</span>
            </label>
            <select
              {...register('contract_type')}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            >
              <option value="">Selecione...</option>
              {CONTRACT_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
            {errors.contract_type && (
              <p className="mt-1 text-xs text-red-500">{errors.contract_type.message}</p>
            )}
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
              Modalidade <span className="text-red-500">*</span>
            </label>
            <select
              {...register('modality')}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            >
              <option value="">Selecione...</option>
              {MODALITY_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
            {errors.modality && (
              <p className="mt-1 text-xs text-red-500">{errors.modality.message}</p>
            )}
          </div>
        </div>

        {/* Location */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
              Cidade <span className="text-red-500">*</span>
            </label>
            <input
              {...register('city')}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
              placeholder="Ex: São Paulo"
            />
            {errors.city && (
              <p className="mt-1 text-xs text-red-500">{errors.city.message}</p>
            )}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">
              Estado <span className="text-red-500">*</span>
            </label>
            <input
              {...register('state')}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
              placeholder="Ex: SP"
            />
            {errors.state && (
              <p className="mt-1 text-xs text-red-500">{errors.state.message}</p>
            )}
          </div>
        </div>

        {/* Salary range */}
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">
            Faixa salarial
          </label>
          <input
            {...register('salary_range')}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="Ex: R$ 2.000 – R$ 3.000 / mês  (opcional)"
          />
        </div>

        {/* Description */}
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">
            Descrição <span className="text-red-500">*</span>
          </label>
          <textarea
            {...register('description')}
            rows={5}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="Descreva as responsabilidades e o dia a dia da função..."
          />
          {errors.description && (
            <p className="mt-1 text-xs text-red-500">{errors.description.message}</p>
          )}
        </div>

        {/* Requirements */}
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">
            Requisitos
          </label>
          <textarea
            {...register('requirements')}
            rows={4}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="Experiência mínima, formação, habilidades técnicas...  (opcional)"
          />
        </div>

        {/* Benefits */}
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">
            Benefícios
          </label>
          <textarea
            {...register('benefits')}
            rows={3}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="Vale alimentação, plano de saúde, alojamento...  (opcional)"
          />
        </div>

        {/* API error */}
        {createJob.isError && (
          <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
            Erro ao publicar vaga. Verifique os dados e tente novamente.
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
            disabled={isSubmitting || createJob.isPending}
            className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50"
          >
            {isSubmitting || createJob.isPending ? 'Publicando...' : 'Publicar vaga'}
          </button>
        </div>
      </form>
    </div>
  )
}
