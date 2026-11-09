import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight, Eye, EyeOff, Lock, Mail } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import authService from '@/services/auth.service'
import workspaceService from '@/services/workspace.service'
import GoogleSignInButton from '@/components/auth/GoogleSignInButton'
import Logo from '@/components/common/Logo'

/** Só aceita caminhos internos, para não virar redirecionamento aberto. */
function safeRedirect(value: string | null): string | null {
  return value && value.startsWith('/') && !value.startsWith('//') ? value : null
}

function errorText(err: unknown): string {
  type ApiErr = { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  const data = (err as ApiErr)?.response?.data
  return (data?.errors ? Object.values(data.errors)[0]?.[0] : undefined) ?? data?.message ?? 'Não foi possível entrar. Tente novamente.'
}

/**
 * Login do funcionário: e-mail e senha (ou Google), sem CNPJ.
 * Depois de entrar, leva direto ao Workspace da empresa (ou ao link de convite que ele abriu).
 */
export default function LoginFuncionarioPage() {
  const { login, setSession, isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const redirect = safeRedirect(params.get('redirect'))
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [show, setShow] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const { data: providers } = useQuery({ queryKey: ['auth-providers'], queryFn: authService.providers, staleTime: 5 * 60_000, retry: 0 })

  const afterLogin = async () => {
    if (redirect) return navigate(redirect, { replace: true })
    try {
      const mine = await workspaceService.mine()
      navigate(mine.length === 1 ? `/w/${mine[0].hotel.slug}` : '/w', { replace: true })
    } catch {
      navigate('/w', { replace: true })
    }
  }

  // Quem já está logado e abre /entrar (ou um convite) segue direto para o destino.
  useEffect(() => {
    if (isAuthenticated) void afterLogin()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setBusy(true)
    try {
      await login({ email: email.trim(), password })
      await afterLogin()
    } catch (err) {
      setError(errorText(err))
    } finally {
      setBusy(false)
    }
  }

  const google = async (credential: string) => {
    setError(null)
    setBusy(true)
    try {
      const res = await authService.google(credential)
      setSession(res.data.token, res.data.user)
      await afterLogin()
    } catch (err) {
      setError(errorText(err))
    } finally {
      setBusy(false)
    }
  }

  const input = 'w-full rounded-lg border border-gray-300 py-2.5 pl-10 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20'

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-50 via-white to-brand-100 px-4 py-10">
      <div className="w-full max-w-sm rounded-2xl border border-gray-100 bg-white p-7 shadow-sm">
        <Link to="/" className="mb-6 flex justify-center"><Logo iconClassName="h-9 w-9" textClassName="text-lg" /></Link>
        <h1 className="text-center text-2xl font-bold text-gray-900">Entrar na sua empresa</h1>
        <p className="mt-1.5 text-center text-sm text-gray-500">Acesse o espaço interno com seu e-mail — não precisa de CNPJ.</p>

        {providers?.google_client_id && (
          <>
            <div className="mt-6">
              <GoogleSignInButton clientId={providers.google_client_id} onCredential={google} onError={setError} />
            </div>
            <div className="my-5 flex items-center gap-3"><div className="h-px flex-1 bg-gray-100" /><span className="text-xs text-gray-400">ou com e-mail</span><div className="h-px flex-1 bg-gray-100" /></div>
          </>
        )}

        <form onSubmit={submit} className={`space-y-4 ${providers?.google_client_id ? '' : 'mt-6'}`}>
          <div className="relative">
            <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <input type="email" autoComplete="email" required placeholder="seu.email@empresa.com" aria-label="E-mail" className={input} value={email} onChange={(e) => setEmail(e.target.value)} />
          </div>
          <div>
            <div className="relative">
              <Lock className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <input type={show ? 'text' : 'password'} autoComplete="current-password" required placeholder="Senha" aria-label="Senha" className={`${input} !pr-10`} value={password} onChange={(e) => setPassword(e.target.value)} />
              <button type="button" tabIndex={-1} onClick={() => setShow((v) => !v)} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" aria-label="Mostrar senha">
                {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            </div>
            <div className="mt-1.5 text-right"><Link to="/forgot-password" className="text-xs font-medium text-primary hover:underline">Esqueceu a senha?</Link></div>
          </div>

          {error && <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">{error}</p>}

          <button type="submit" disabled={busy} className="group flex w-full items-center justify-center gap-2 rounded-lg bg-primary py-2.5 text-sm font-semibold text-white hover:bg-primary/90 disabled:opacity-50">
            {busy ? 'Entrando…' : <>Entrar <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" /></>}
          </button>
        </form>

        <p className="mt-6 text-center text-sm text-gray-500">
          Primeiro acesso? Use o link de convite que o RH enviou.
        </p>
        <p className="mt-3 text-center text-xs text-gray-400">
          <Link to="/login/pessoa" className="hover:underline">Login pessoal</Link> · <Link to="/login/hotel" className="hover:underline">Sou a empresa (CNPJ)</Link>
        </p>
      </div>
    </div>
  )
}
