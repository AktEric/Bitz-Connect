import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import authService from '@/services/auth.service'

const schema = z
  .object({
    password: z.string().min(8, 'Senha deve ter ao menos 8 caracteres'),
    password_confirmation: z.string().min(1, 'Confirmação obrigatória'),
  })
  .refine((d) => d.password === d.password_confirmation, {
    message: 'As senhas não coincidem',
    path: ['password_confirmation'],
  })

type FormData = z.infer<typeof schema>

/**
 * Requirements: 1.7
 */
export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const [serverError, setServerError] = useState<string | null>(null)

  const token = searchParams.get('token') ?? ''
  const email = searchParams.get('email') ?? ''

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({ resolver: zodResolver(schema) })

  const onSubmit = async (data: FormData) => {
    setServerError(null)
    try {
      await authService.resetPassword({ token, email, ...data })
      navigate('/login', { replace: true })
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? 'Erro ao redefinir senha. O link pode ter expirado.'
      setServerError(msg)
    }
  }

  if (!token || !email) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
        <div className="w-full max-w-sm rounded-xl bg-white p-8 shadow text-center">
          <p className="text-sm text-red-600">Link de redefinição inválido ou expirado.</p>
          <Link to="/forgot-password" className="mt-4 inline-block text-sm text-blue-600 hover:underline">
            Solicitar novo link
          </Link>
        </div>
      </div>
    )
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
      <div className="w-full max-w-sm rounded-xl bg-white p-8 shadow">
        <h1 className="mb-6 text-2xl font-bold text-gray-900">Nova senha</h1>

        <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
          <div>
            <label htmlFor="password" className="mb-1 block text-sm font-medium text-gray-700">
              Nova senha
            </label>
            <input
              id="password"
              type="password"
              autoComplete="new-password"
              {...register('password')}
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {errors.password && (
              <p className="mt-1 text-xs text-red-600">{errors.password.message}</p>
            )}
          </div>

          <div>
            <label
              htmlFor="password_confirmation"
              className="mb-1 block text-sm font-medium text-gray-700"
            >
              Confirmar nova senha
            </label>
            <input
              id="password_confirmation"
              type="password"
              autoComplete="new-password"
              {...register('password_confirmation')}
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            {errors.password_confirmation && (
              <p className="mt-1 text-xs text-red-600">{errors.password_confirmation.message}</p>
            )}
          </div>

          {serverError && (
            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{serverError}</p>
          )}

          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full rounded-lg bg-blue-600 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {isSubmitting ? 'Salvando…' : 'Redefinir senha'}
          </button>
        </form>
      </div>
    </div>
  )
}
