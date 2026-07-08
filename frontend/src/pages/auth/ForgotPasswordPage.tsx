import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import authService from '@/services/auth.service'

const schema = z.object({
  email: z.string().email('E-mail inválido'),
})

type FormData = z.infer<typeof schema>

/**
 * Requirements: 1.6
 */
export default function ForgotPasswordPage() {
  const [successMsg, setSuccessMsg] = useState<string | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({ resolver: zodResolver(schema) })

  const onSubmit = async (data: FormData) => {
    setServerError(null)
    setSuccessMsg(null)
    try {
      const res = await authService.forgotPassword(data)
      setSuccessMsg(res.message)
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? 'Erro ao enviar e-mail. Tente novamente.'
      setServerError(msg)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
      <div className="w-full max-w-sm rounded-xl bg-white p-8 shadow">
        <h1 className="mb-2 text-2xl font-bold text-gray-900">Recuperar senha</h1>
        <p className="mb-6 text-sm text-gray-500">
          Informe seu e-mail e enviaremos um link de redefinição.
        </p>

        {successMsg ? (
          <div className="rounded-lg bg-green-50 px-3 py-4 text-sm text-green-700">
            {successMsg}
          </div>
        ) : (
          <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
            <div>
              <label htmlFor="email" className="mb-1 block text-sm font-medium text-gray-700">
                E-mail
              </label>
              <input
                id="email"
                type="email"
                autoComplete="email"
                {...register('email')}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              {errors.email && (
                <p className="mt-1 text-xs text-red-600">{errors.email.message}</p>
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
              {isSubmitting ? 'Enviando…' : 'Enviar link'}
            </button>
          </form>
        )}

        <p className="mt-4 text-center text-sm text-gray-600">
          Lembrou a senha?{' '}
          <Link to="/login" className="text-blue-600 hover:underline">
            Entrar
          </Link>
        </p>
      </div>
    </div>
  )
}
