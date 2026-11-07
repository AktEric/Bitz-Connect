import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import api from '@/services/api'
import { useAuth } from '@/hooks/useAuth'
import { useHotelAuth } from '@/hooks/useHotelAuth'
import type { User } from '@/types/auth.types'

// ── Schemas ──────────────────────────────────────────────────────────────────

const step1Schema = z
  .object({
    name: z.string().min(2, 'Nome deve ter ao menos 2 caracteres'),
    email: z.string().email('E-mail inválido'),
    password: z.string().min(8, 'Senha deve ter ao menos 8 caracteres'),
    password_confirmation: z.string().min(1, 'Confirmação obrigatória'),
  })
  .refine((d) => d.password === d.password_confirmation, {
    message: 'As senhas não coincidem',
    path: ['password_confirmation'],
  })

const step2Schema = z.object({
  hotel_name: z.string().min(2, 'Nome da empresa deve ter ao menos 2 caracteres'),
  cnpj: z
    .string()
    .min(14, 'CNPJ inválido')
    .max(18, 'CNPJ inválido'),
  hotel_email: z.string().email('E-mail da empresa inválido'),
  hotel_password: z.string().min(8, 'Senha da empresa deve ter ao menos 8 caracteres'),
  city: z.string().optional(),
  website: z.string().url('URL inválida').optional().or(z.literal('')),
})

// ── Types ─────────────────────────────────────────────────────────────────────

type Step = 1 | 2
type Step1Data = z.infer<typeof step1Schema>
type Step2Data = z.infer<typeof step2Schema>

interface RegisterHotelResponse {
  data: {
    token: string
    user: User
    hotel: {
      slug: string
      name: string
      uuid: string
    }
  }
}

// ── Field config helpers ──────────────────────────────────────────────────────

const step1Fields: {
  id: keyof Step1Data
  label: string
  type: string
  autocomplete: string
}[] = [
  { id: 'name', label: 'Nome completo', type: 'text', autocomplete: 'name' },
  { id: 'email', label: 'E-mail', type: 'email', autocomplete: 'email' },
  { id: 'password', label: 'Senha', type: 'password', autocomplete: 'new-password' },
  {
    id: 'password_confirmation',
    label: 'Confirmar senha',
    type: 'password',
    autocomplete: 'new-password',
  },
]

const step2Fields: {
  id: keyof Step2Data
  label: string
  type: string
  autocomplete: string
  placeholder?: string
  optional?: boolean
}[] = [
  { id: 'hotel_name', label: 'Nome da empresa', type: 'text', autocomplete: 'organization' },
  {
    id: 'cnpj',
    label: 'CNPJ',
    type: 'text',
    autocomplete: 'off',
    placeholder: '00.000.000/0000-00',
  },
  {
    id: 'hotel_email',
    label: 'E-mail da empresa',
    type: 'email',
    autocomplete: 'email',
  },
  {
    id: 'hotel_password',
    label: 'Senha de acesso da empresa',
    type: 'password',
    autocomplete: 'new-password',
  },
  {
    id: 'city',
    label: 'Cidade',
    type: 'text',
    autocomplete: 'address-level2',
    optional: true,
  },
  {
    id: 'website',
    label: 'Site',
    type: 'url',
    autocomplete: 'url',
    placeholder: 'https://seuhotel.com.br',
    optional: true,
  },
]

// ── Step indicator ────────────────────────────────────────────────────────────

function StepIndicator({ step }: { step: Step }) {
  return (
    <div className="mb-6 flex items-center gap-2" aria-label={`Passo ${step} de 2`}>
      {([1, 2] as const).map((n) => (
        <div key={n} className="flex items-center gap-2">
          <div
            className={[
              'flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold',
              n === step
                ? 'bg-amber-500 text-white'
                : n < step
                  ? 'bg-amber-200 text-amber-800'
                  : 'bg-gray-200 text-gray-500',
            ].join(' ')}
          >
            {n}
          </div>
          {n === 1 && (
            <div
              className={[
                'h-1 w-8 rounded-full',
                step === 2 ? 'bg-amber-300' : 'bg-gray-200',
              ].join(' ')}
            />
          )}
        </div>
      ))}
      <span className="ml-2 text-sm font-medium text-amber-700">Passo {step} de 2</span>
    </div>
  )
}

// ── Step 1 form ───────────────────────────────────────────────────────────────

interface Step1FormProps {
  onNext: (data: Step1Data) => void
}

function Step1Form({ onNext }: Step1FormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<Step1Data>({ resolver: zodResolver(step1Schema) })

  return (
    <form onSubmit={handleSubmit(onNext)} noValidate className="space-y-4">
      {step1Fields.map(({ id, label, type, autocomplete }) => (
        <div key={id}>
          <label htmlFor={id} className="mb-1 block text-sm font-medium text-gray-700">
            {label}
          </label>
          <input
            id={id}
            type={type}
            autoComplete={autocomplete}
            {...register(id)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
          {errors[id] && (
            <p className="mt-1 text-xs text-red-600">{errors[id]?.message}</p>
          )}
        </div>
      ))}

      <button
        type="submit"
        disabled={isSubmitting}
        className="w-full rounded-lg bg-amber-500 py-2 text-sm font-semibold text-white hover:bg-amber-600 disabled:opacity-50"
      >
        Próximo
      </button>
    </form>
  )
}

// ── Step 2 form ───────────────────────────────────────────────────────────────

interface Step2FormProps {
  onBack: () => void
  onSubmit: (data: Step2Data) => Promise<void>
  serverError: string | null
}

function Step2Form({ onBack, onSubmit, serverError }: Step2FormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<Step2Data>({ resolver: zodResolver(step2Schema) })

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
      {step2Fields.map(({ id, label, type, autocomplete, placeholder, optional }) => (
        <div key={id}>
          <label htmlFor={id} className="mb-1 block text-sm font-medium text-gray-700">
            {label}
            {optional && (
              <span className="ml-1 text-xs font-normal text-gray-400">(opcional)</span>
            )}
          </label>
          <input
            id={id}
            type={type}
            autoComplete={autocomplete}
            placeholder={placeholder}
            {...register(id)}
            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
          {errors[id] && (
            <p className="mt-1 text-xs text-red-600">{errors[id]?.message}</p>
          )}
        </div>
      ))}

      {serverError && (
        <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{serverError}</p>
      )}

      <div className="flex gap-3">
        <button
          type="button"
          onClick={onBack}
          className="flex-1 rounded-lg border border-amber-300 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50"
        >
          Voltar
        </button>
        <button
          type="submit"
          disabled={isSubmitting}
          className="flex-1 rounded-lg bg-amber-500 py-2 text-sm font-semibold text-white hover:bg-amber-600 disabled:opacity-50"
        >
          {isSubmitting ? 'Criando conta…' : 'Criar conta'}
        </button>
      </div>
    </form>
  )
}

// ── Main page ─────────────────────────────────────────────────────────────────

/**
 * Página de cadastro para contas de Hotel, com stepper de 2 passos.
 * Passo 1: dados do usuário Pessoa (name, email, password, password_confirmation).
 * Passo 2: dados do hotel (hotel_name, cnpj, hotel_email, hotel_password, city?, website?).
 * Visual: fundo amber/dourado consistente com LoginHotelPage.
 * Requirements: 2.3, 2.4, 2.8
 */
export default function RegisterHotelPage() {
  const { setSession } = useAuth()
  const { enterHotel } = useHotelAuth()
  const navigate = useNavigate()

  const [step, setStep] = useState<Step>(1)
  const [step1Data, setStep1Data] = useState<Step1Data | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)

  // Advance from step 1 → 2 (called only when step 1 validates successfully)
  const handleStep1Next = (data: Step1Data) => {
    setStep1Data(data)
    setStep(2)
  }

  // Go back from step 2 → 1
  const handleBack = () => {
    setServerError(null)
    setStep(1)
  }

  // Submit merged payload to POST /auth/register-hotel
  const handleStep2Submit = async (step2: Step2Data) => {
    if (!step1Data) return
    setServerError(null)

    try {
      const payload = {
        ...step1Data,
        ...step2,
        // Omit empty optional strings so backend doesn't get empty values
        city: step2.city || undefined,
        website: step2.website || undefined,
      }

      const response = await api.post<RegisterHotelResponse>('/auth/register-hotel', payload)
      const { token, user, hotel } = response.data.data

      // Persist auth session
      setSession(token, user)

      // Enter hotel session
      enterHotel({
        hotelSlug: hotel.slug,
        hotelName: hotel.name,
        hotelUuid: hotel.uuid,
        isAdmin: true,
      })

      navigate(`/hotel/${hotel.slug}/dashboard`, { replace: true })
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? 'Erro ao criar conta. Tente novamente.'
      setServerError(msg)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-amber-50 p-4">
      <div className="w-full max-w-sm rounded-xl bg-white p-8 shadow">
        <h1 className="mb-2 text-2xl font-bold text-amber-900">Cadastrar Hotel</h1>
        <p className="mb-4 text-sm text-amber-600">
          {step === 1 ? 'Dados da sua conta de administrador' : 'Dados da sua empresa'}
        </p>

        <StepIndicator step={step} />

        {step === 1 ? (
          <Step1Form onNext={handleStep1Next} />
        ) : (
          <Step2Form
            onBack={handleBack}
            onSubmit={handleStep2Submit}
            serverError={serverError}
          />
        )}

        <p className="mt-4 text-center text-sm text-gray-600">
          Já tem conta de hotel?{' '}
          <Link to="/login/hotel" className="text-amber-600 hover:underline">
            Entrar
          </Link>
        </p>
      </div>
    </div>
  )
}
