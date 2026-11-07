import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Mail, Lock, Eye, EyeOff, ArrowRight, Building2, Sparkles } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import Logo from '@/components/common/Logo'

const schema = z.object({
  email: z.string().email('E-mail inválido'),
  password: z.string().min(1, 'Senha obrigatória'),
})

type FormData = z.infer<typeof schema>

/**
 * Página de login para contas de Pessoa (usuários individuais).
 * Requirements: 1.1, 1.3, 1.5, 1.8
 */
export default function LoginPessoaPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [serverError, setServerError] = useState<string | null>(null)
  const [showPassword, setShowPassword] = useState(false)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({ resolver: zodResolver(schema) })

  const onSubmit = async (data: FormData) => {
    setServerError(null)
    try {
      await login(data)
      navigate('/feed', { replace: true })
    } catch (err: unknown) {
      type ApiErr = { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
      const data = (err as ApiErr)?.response?.data
      const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined
      const msg = firstError ?? data?.message ?? 'Erro ao fazer login. Tente novamente.'
      setServerError(msg)
    }
  }

  return (
    <div className="flex min-h-screen bg-white">
      {/* ── Painel de marca (desktop) ── */}
      <div className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-violet-700 via-violet-600 to-fuchsia-600 px-12 py-12 text-white lg:flex">
        {/* Decoração de fundo */}
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <div className="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-white/10 blur-3xl" />
          <div className="absolute -bottom-32 -right-16 h-96 w-96 rounded-full bg-fuchsia-400/20 blur-3xl" />
          <div className="absolute inset-0 opacity-[0.07] [background-image:radial-gradient(circle_at_1px_1px,white_1px,transparent_0)] [background-size:28px_28px]" />
        </div>

        <Link to="/" className="relative z-10 flex items-center gap-2.5">
          <Logo iconClassName="h-10 w-10" textClassName="text-xl" variant="white" />
        </Link>

        <div className="relative z-10 max-w-md">
          <div className="mb-5 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium backdrop-blur-sm ring-1 ring-white/20">
            <Sparkles className="h-3.5 w-3.5 text-yellow-300" />
            A rede de pessoas e empresas
          </div>
          <h1 className="text-4xl font-bold leading-tight tracking-tight">
            RH e rede social para o seu trabalho.
          </h1>
          <p className="mt-4 text-base leading-relaxed text-violet-100">
            Profissionais, empresas e especialistas em um só lugar — sua próxima
            grande oportunidade começa aqui.
          </p>

          <div className="mt-10 flex items-center gap-8">
            <div>
              <p className="text-2xl font-bold">+12 mil</p>
              <p className="text-sm text-violet-200">profissionais</p>
            </div>
            <div className="h-8 w-px bg-white/20" />
            <div>
              <p className="text-2xl font-bold">+800</p>
              <p className="text-sm text-violet-200">empresas parceiras</p>
            </div>
            <div className="h-8 w-px bg-white/20" />
            <div>
              <p className="text-2xl font-bold">+3 mil</p>
              <p className="text-sm text-violet-200">vagas abertas</p>
            </div>
          </div>
        </div>

        <p className="relative z-10 text-xs text-violet-200">
          © {new Date().getFullYear()} ConnectHotel. Todos os direitos reservados.
        </p>
      </div>

      {/* ── Formulário ── */}
      <div className="flex w-full flex-col justify-center px-6 py-12 sm:px-12 lg:w-1/2 lg:px-20 xl:px-24">
        <div className="mx-auto w-full max-w-sm">
          <Link to="/" className="mb-10 flex items-center gap-2.5 lg:hidden">
            <Logo iconClassName="h-9 w-9" textClassName="text-lg" />
          </Link>

          <h2 className="text-2xl font-bold text-gray-900">Bem-vindo de volta</h2>
          <p className="mt-1.5 text-sm text-gray-500">
            Entre com sua conta para continuar na sua jornada profissional.
          </p>

          <form onSubmit={handleSubmit(onSubmit)} noValidate className="mt-8 space-y-4">
            <div>
              <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-gray-700">
                E-mail
              </label>
              <div className="relative">
                <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                  id="email"
                  type="email"
                  autoComplete="email"
                  placeholder="voce@exemplo.com"
                  {...register('email')}
                  className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-3 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                />
              </div>
              {errors.email && (
                <p className="mt-1 text-xs text-red-600">{errors.email.message}</p>
              )}
            </div>

            <div>
              <div className="mb-1.5 flex items-center justify-between">
                <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                  Senha
                </label>
                <Link to="/forgot-password" className="text-xs font-medium text-primary hover:underline">
                  Esqueceu a senha?
                </Link>
              </div>
              <div className="relative">
                <Lock className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  placeholder="••••••••"
                  {...register('password')}
                  className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-10 text-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((v) => !v)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                  tabIndex={-1}
                >
                  {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
              {errors.password && (
                <p className="mt-1 text-xs text-red-600">{errors.password.message}</p>
              )}
            </div>

            {serverError && (
              <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{serverError}</p>
            )}

            <button
              type="submit"
              disabled={isSubmitting}
              className="group flex w-full items-center justify-center gap-2 rounded-lg bg-primary py-2.5 text-sm font-semibold text-white transition-all hover:bg-primary/90 hover:shadow-lg hover:shadow-primary/25 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {isSubmitting ? (
                'Entrando…'
              ) : (
                <>
                  Entrar
                  <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                </>
              )}
            </button>
          </form>

          <p className="mt-6 text-center text-sm text-gray-500">
            Não tem conta?{' '}
            <Link to="/register" className="font-medium text-primary hover:underline">
              Cadastre-se gratuitamente
            </Link>
          </p>

          <div className="my-6 flex items-center gap-3">
            <div className="h-px flex-1 bg-gray-100" />
            <span className="text-xs text-gray-400">ou</span>
            <div className="h-px flex-1 bg-gray-100" />
          </div>

          <Link
            to="/entrar"
            className="mb-3 flex w-full items-center justify-center gap-2 rounded-lg border border-violet-200 bg-violet-50 py-2.5 text-sm font-medium text-violet-700 transition-colors hover:bg-violet-100"
          >
            <Building2 className="h-4 w-4" />
            Sou funcionário de uma empresa
          </Link>

          <Link
            to="/login/hotel"
            className="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
          >
            <Building2 className="h-4 w-4 text-amber-500" />
            Entrar como Hotel / Empresa
          </Link>
        </div>
      </div>
    </div>
  )
}
