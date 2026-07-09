import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import type { Job, CreateJobData } from '@/types/job.types'

const schema = z.object({
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

interface JobFormProps {
  initial?: Job
  onSubmit: (data: Omit<CreateJobData, 'hotel_uuid'>) => void
  onCancel: () => void
  loading?: boolean
}

/**
 * Formulário de vaga (criar/editar) usado no painel do hotel. Não inclui
 * seletor de hotel — o contexto já é o hotel administrado atualmente.
 */
export default function JobForm({ initial, onSubmit, onCancel, loading = false }: JobFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: initial
      ? {
          title: initial.title,
          description: initial.description,
          requirements: initial.requirements ?? '',
          benefits: initial.benefits ?? '',
          contract_type: initial.contract_type,
          modality: initial.modality,
          city: initial.city,
          state: initial.state,
          salary_range: initial.salary_range ?? '',
        }
      : undefined,
  })

  function submit(values: FormValues) {
    onSubmit({
      title:         values.title,
      description:   values.description,
      requirements:  values.requirements || undefined,
      benefits:      values.benefits || undefined,
      contract_type: values.contract_type,
      modality:      values.modality,
      city:          values.city,
      state:         values.state,
      salary_range:  values.salary_range || undefined,
    })
  }

  return (
    <form onSubmit={handleSubmit(submit)} className="space-y-4">
      <div>
        <label className="mb-1 block text-sm font-medium text-gray-700">
          Título da vaga <span className="text-red-500">*</span>
        </label>
        <input
          {...register('title')}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          placeholder="Ex: Recepcionista, Governanta, Chef de Cozinha..."
        />
        {errors.title && <p className="mt-1 text-xs text-red-500">{errors.title.message}</p>}
      </div>

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
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
          {errors.contract_type && <p className="mt-1 text-xs text-red-500">{errors.contract_type.message}</p>}
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
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
          {errors.modality && <p className="mt-1 text-xs text-red-500">{errors.modality.message}</p>}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">
            Cidade <span className="text-red-500">*</span>
          </label>
          <input {...register('city')} className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Ex: São Paulo" />
          {errors.city && <p className="mt-1 text-xs text-red-500">{errors.city.message}</p>}
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">
            Estado <span className="text-red-500">*</span>
          </label>
          <input {...register('state')} className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Ex: SP" />
          {errors.state && <p className="mt-1 text-xs text-red-500">{errors.state.message}</p>}
        </div>
      </div>

      <div>
        <label className="mb-1 block text-sm font-medium text-gray-700">Faixa salarial</label>
        <input
          {...register('salary_range')}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          placeholder="Ex: R$ 2.000 – R$ 3.000 / mês (opcional)"
        />
      </div>

      <div>
        <label className="mb-1 block text-sm font-medium text-gray-700">
          Descrição <span className="text-red-500">*</span>
        </label>
        <textarea
          {...register('description')}
          rows={4}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          placeholder="Descreva as responsabilidades e o dia a dia da função..."
        />
        {errors.description && <p className="mt-1 text-xs text-red-500">{errors.description.message}</p>}
      </div>

      <div>
        <label className="mb-1 block text-sm font-medium text-gray-700">Requisitos</label>
        <textarea
          {...register('requirements')}
          rows={3}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          placeholder="Experiência mínima, formação, habilidades técnicas... (opcional)"
        />
      </div>

      <div>
        <label className="mb-1 block text-sm font-medium text-gray-700">Benefícios</label>
        <textarea
          {...register('benefits')}
          rows={3}
          className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          placeholder="Vale alimentação, plano de saúde, alojamento... (opcional)"
        />
      </div>

      <div className="flex justify-end gap-2 pt-2">
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
          {loading ? 'Salvando...' : initial ? 'Salvar alterações' : 'Publicar vaga'}
        </button>
      </div>
    </form>
  )
}
