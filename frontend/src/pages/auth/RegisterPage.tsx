import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useAuth } from '@/hooks/useAuth'

const schema = z
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

type FormData = z.infer<typeof schema>

/**
 * Requirements: 1.1, 1.2
 */
export default function RegisterPage() {
  const { register: registerUser } = useAuth()
  const navigate = useNavigate()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({ resolver: zodResolver(schema) })

  const onSubmit = async (data: FormData) => {
    setServerError(null)
    try {
      await registerUser(data)
      navigate('/', { replace: true })
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? 'Erro ao criar conta. Tente novamente.'
      setServerError(msg)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
      <div className="w-full max-w-sm rounded-xl bg-white p-8 shadow">
        <h1 className="mb-6 text-2xl font-bold text-gray-900">Criar conta</h1>

        <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
          {[
            { id: 'name', label: 'Nome completo', type: 'text', autocomplete: 'name' },
            { id: 'email', label: 'E-mail', type: 'email', autocomplete: 'email' },
            { id: 'password', label: 'Senha', type: 'password', autocomplete: 'new-password' },
            {
              id: 'password_confirmation',
              label: 'Confirmar senha',
              type: 'password',
              autocomplete: 'new-password',
            },
          ].map(({ id, label, type, autocomplete }) => (
            <div key={id}>
              <label htmlFor={id} className="mb-1 block text-sm font-medium text-gray-700">
                {label}
              </label>
              <input
                id={id}
                type={type}
                autoComplete={autocomplete}
                {...register(id as keyof FormData)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              {errors[id as keyof FormData] && (
                <p className="mt-1 text-xs text-red-600">
                  {errors[id as keyof FormData]?.message}
                </p>
              )}
            </div>
          ))}

          {serverError && (
            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{serverError}</p>
          )}

          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full rounded-lg bg-blue-600 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {isSubmitting ? 'Criando conta…' : 'Criar conta'}
          </button>
        </form>

        <p className="mt-4 text-center text-sm text-gray-600">
          Já tem conta?{' '}
          <Link to="/login" className="text-blue-600 hover:underline">
            Entrar
          </Link>
        </p>
      </div>
    </div>
  )
}
