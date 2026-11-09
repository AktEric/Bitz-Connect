import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Mail, Lock, Eye, EyeOff, ArrowRight, User as UserIcon, Sparkles, IdCard } from 'lucide-react'
import { withTwoFactor } from '@/services/twoFactorLogin'
import api from '@/services/api'
import { useAuth } from '@/hooks/useAuth'
import { useHotelAuth } from '@/hooks/useHotelAuth'
import type { User } from '@/types/auth.types'
import Logo from '@/components/common/Logo'

const schema = z.object({
  email: z.string().email('E-mail inválido'),
  password: z.string().min(1, 'Senha obrigatória'),
  cnpj: z.string().min(14, 'CNPJ inválido').max(18),
})

type FormData = z.infer<typeof schema>

interface HotelLoginResponse {
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

/**
 * Página de login para contas de Hotel.
 * Exige e-mail, senha e CNPJ da empresa administrado.
 * Requirements: 1.2, 1.4, 1.9
 */
export default function LoginHotelPage() {
  const { setSession } = useAuth()
  const { enterHotel } = useHotelAuth()
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
      const response = await withTwoFactor((extra) => api.post<HotelLoginResponse>('/auth/hotel-login', {
        email: data.email,
        password: data.password,
        cnpj: data.cnpj,
        ...extra,
      }))

      const { token, user, hotel } = response.data.data

      setSession(token, user)

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
          ?.message ?? 'Erro ao fazer login. Tente novamente.'
      setServerError(msg)
    }
  }

  return (
    <div className="flex min-h-screen bg-white">
      {/* ── Painel de marca (desktop) ── */}
      <div className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-amber-600 via-amber-500 to-orange-500 px-12 py-12 text-white lg:flex">
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
          <div className="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-white/10 blur-3xl" />
          <div className="absolute -bottom-32 -right-16 h-96 w-96 rounded-full bg-orange-300/20 blur-3xl" />
          <div className="absolute inset-0 opacity-[0.07] [background-image:radial-gradient(circle_at_1px_1px,white_1px,transparent_0)] [background-size:28px_28px]" />
        </div>

        <Link to="/" className="relative z-10 flex items-center gap-2.5">
          <Logo iconClassName="h-10 w-10" textClassName="text-xl" variant="white" />
        </Link>

        <div className="relative z-10 max-w-md">
          <div className="mb-5 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium backdrop-blur-sm ring-1 ring-white/20">
            <Sparkles className="h-3.5 w-3.5 text-yellow-200" />
            Painel para empresas e redes
          </div>
          <h1 className="text-4xl font-bold leading-tight tracking-tight">
            RH e rede social interna para a sua empresa.
          </h1>
          <p className="mt-4 text-base leading-relaxed text-amber-50">
            Encontre talentos, gerencie vagas e equipe e construa a reputação
            da sua marca em um painel feito sob medida para o seu negócio.
          </p>

          <div className="mt-10 flex items-center gap-8">
            <div>
              <p className="text-2xl font-bold">+800</p>
              <p className="text-sm text-amber-100">empresas parceiras</p>
            </div>
            <div className="h-8 w-px bg-white/20" />
            <div>
              <p className="text-2xl font-bold">+12 mil</p>
              <p className="text-sm text-amber-100">candidatos ativos</p>
            </div>
            <div className="h-8 w-px bg-white/20" />
            <div>
              <p className="text-2xl font-bold">4.8/5</p>
              <p className="text-sm text-amber-100">satisfação</p>
            </div>
          </div>
        </div>

        <p className="relative z-10 text-xs text-amber-100">
          © {new Date().getFullYear()} Bitz Connect. Todos os direitos reservados.
        </p>
      </div>

      {/* ── Formulário ── */}
      <div className="flex w-full flex-col justify-center px-6 py-12 sm:px-12 lg:w-1/2 lg:px-20 xl:px-24">
        <div className="mx-auto w-full max-w-sm">
          <Link to="/" className="mb-10 flex items-center gap-2.5 lg:hidden">
            <Logo iconClassName="h-9 w-9" textClassName="text-lg" />
          </Link>

          <h2 className="text-2xl font-bold text-gray-900">Painel do Hotel</h2>
          <p className="mt-1.5 text-sm text-gray-500">
            Acesse a gestão da sua empresa e conecte-se aos melhores talentos.
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
                  placeholder="contato@seuhotel.com"
                  {...register('email')}
                  className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-3 text-sm transition-colors focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                />
              </div>
              {errors.email && (
                <p className="mt-1 text-xs text-red-600">{errors.email.message}</p>
              )}
            </div>

            <div>
              <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-gray-700">
                Senha
              </label>
              <div className="relative">
                <Lock className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  placeholder="••••••••"
                  {...register('password')}
                  className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-10 text-sm transition-colors focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
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

            <div>
              <label htmlFor="cnpj" className="mb-1.5 block text-sm font-medium text-gray-700">
                CNPJ
              </label>
              <div className="relative">
                <IdCard className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                  id="cnpj"
                  type="text"
                  autoComplete="off"
                  placeholder="00.000.000/0000-00"
                  {...register('cnpj')}
                  className="w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-3 text-sm transition-colors focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                />
              </div>
              {errors.cnpj && (
                <p className="mt-1 text-xs text-red-600">{errors.cnpj.message}</p>
              )}
            </div>

            {serverError && (
              <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{serverError}</p>
            )}

            <button
              type="submit"
              disabled={isSubmitting}
              className="group flex w-full items-center justify-center gap-2 rounded-lg bg-amber-500 py-2.5 text-sm font-semibold text-white transition-all hover:bg-amber-600 hover:shadow-lg hover:shadow-amber-500/25 disabled:cursor-not-allowed disabled:opacity-50"
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
            Não tem conta de hotel?{' '}
            <Link to="/register/hotel" className="font-medium text-amber-600 hover:underline">
              Cadastrar hotel
            </Link>
          </p>

          <div className="my-6 flex items-center gap-3">
            <div className="h-px flex-1 bg-gray-100" />
            <span className="text-xs text-gray-400">ou</span>
            <div className="h-px flex-1 bg-gray-100" />
          </div>

          <Link
            to="/login/pessoa"
            className="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
          >
            <UserIcon className="h-4 w-4 text-primary" />
            Entrar como Pessoa
          </Link>
        </div>
      </div>
    </div>
  )
}
