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
 * Página de cadastro para contas de Pessoa (usuários individuais).
 * Visual: fundo azul/indigo — consistente com LoginPessoaPage.
 * Requirements: 2.2, 2.7
 */
export default function RegisterPessoaPage() {
  const { register: registerUser } = useAuth()
  const navigate = useNavigate()
  const redirectTo = (() => {
    const r = new URLSearchParams(window.location.search).get('redirect')
    return r && r.startsWith('/') && !r.startsWith('//') ? r : '/feed'
  })()
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
      navigate(redirectTo, { replace: true })
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? 'Erro ao criar conta. Tente novamente.'
      setServerError(msg)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-indigo-50 p-4">
      <div className="w-full max-w-sm rounded-xl bg-white p-8 shadow">
        <h1 className="mb-2 text-2xl font-bold text-indigo-900">Criar conta</h1>
        <p className="mb-6 text-sm text-indigo-500">Cadastre-se como Pessoa</p>

        <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
          {(
            [
              { id: 'name', label: 'Nome completo', type: 'text', autocomplete: 'name' },
              { id: 'email', label: 'E-mail', type: 'email', autocomplete: 'email' },
              { id: 'password', label: 'Senha', type: 'password', autocomplete: 'new-password' },
              {
                id: 'password_confirmation',
                label: 'Confirmar senha',
                type: 'password',
                autocomplete: 'new-password',
              },
            ] as const
          ).map(({ id, label, type, autocomplete }) => (
            <div key={id}>
              <label htmlFor={id} className="mb-1 block text-sm font-medium text-gray-700">
                {label}
              </label>
              <input
                id={id}
                type={type}
                autoComplete={autocomplete}
                {...register(id)}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />
              {errors[id] && (
                <p className="mt-1 text-xs text-red-600">{errors[id]?.message}</p>
              )}
            </div>
          ))}

          {serverError && (
            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{serverError}</p>
          )}

          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {isSubmitting ? 'Criando conta…' : 'Criar conta'}
          </button>
        </form>

        <p className="mt-4 text-center text-sm text-gray-600">
          Já tem conta?{' '}
          <Link to="/login/pessoa" className="text-indigo-600 hover:underline">
            Entrar
          </Link>
        </p>
      </div>
    </div>
  )
}
