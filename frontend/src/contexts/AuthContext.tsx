import {
  createContext,
  useContext,
  useState,
  useCallback,
  useEffect,
  type ReactNode,
} from 'react'
import type { User, LoginCredentials, RegisterData } from '@/types/auth.types'
import authService from '@/services/auth.service'
import { watchIdle, markActivity, clearActivity, isIdleExpired } from '@/utils/sessionIdle'

interface AuthContextValue {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  login: (data: LoginCredentials) => Promise<void>
  register: (data: RegisterData) => Promise<void>
  logout: () => Promise<void>
  setUser: (user: User) => void
  /**
   * Persiste uma sessão já obtida por fora do fluxo padrão de login (ex:
   * hotel-login, register-hotel), sincronizando localStorage E o estado React.
   * Escrever direto no localStorage sem isso deixa `isAuthenticated` desatualizado
   * e causa loop de redirecionamento entre PrivateRoute/GuestRoute.
   */
  setSession: (token: string, user: User) => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

function getStoredToken(): string | null {
  // Ficou ocioso por mais de 2h (inclui PC desligado): descarta a sessão guardada.
  if (localStorage.getItem('token') && isIdleExpired()) {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    localStorage.removeItem('hotel_session')
    clearActivity()
    return null
  }
  return localStorage.getItem('token')
}

function getStoredUser(): User | null {
  try {
    const raw = localStorage.getItem('user')
    return raw ? (JSON.parse(raw) as User) : null
  } catch {
    return null
  }
}

/**
 * Provides authentication state and actions to the app.
 * Requirements: 1.1, 1.3, 1.5
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(getStoredToken)
  const [user, setUserState] = useState<User | null>(getStoredUser)

  const persistSession = useCallback((newToken: string, newUser: User) => {
    localStorage.setItem('token', newToken)
    localStorage.setItem('user', JSON.stringify(newUser))
    markActivity()
    setToken(newToken)
    setUserState(newUser)
  }, [])

  const clearSession = useCallback(() => {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
    clearActivity()
    setToken(null)
    setUserState(null)
  }, [])

  const login = useCallback(
    async (data: LoginCredentials) => {
      const res = await authService.login(data)
      persistSession(res.data.token, res.data.user)
    },
    [persistSession],
  )

  const register = useCallback(
    async (data: RegisterData) => {
      const res = await authService.register(data)
      persistSession(res.data.token, res.data.user)
    },
    [persistSession],
  )

  const logout = useCallback(async () => {
    try {
      await authService.logout()
    } finally {
      clearSession()
      // Avisa outros contextos (ex: HotelAuthContext) para limpar sua sessão
      // também — sem isso, uma sessão de hotel obsoleta pode sobreviver ao
      // logout e causar loop de redirecionamento entre as guardas de rota.
      window.dispatchEvent(new CustomEvent('auth:unauthenticated'))
    }
  }, [clearSession])

  // O interceptor do axios (api.ts) também dispara este evento em respostas 401.
  useEffect(() => {
    window.addEventListener('auth:unauthenticated', clearSession)
    return () => window.removeEventListener('auth:unauthenticated', clearSession)
  }, [clearSession])

  // Inatividade de 2h com a aba aberta: encerra a sessão (no servidor também) e volta ao login.
  useEffect(() => {
    if (!token) return
    return watchIdle(() => {
      authService.logout().catch(() => undefined)
      clearSession()
      window.dispatchEvent(new CustomEvent('auth:unauthenticated'))
    })
  }, [token, clearSession])

  const setUser = useCallback((newUser: User) => {
    localStorage.setItem('user', JSON.stringify(newUser))
    setUserState(newUser)
  }, [])

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        isAuthenticated: !!token,
        login,
        register,
        logout,
        setUser,
        setSession: persistSession,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuthContext(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuthContext must be used inside <AuthProvider>')
  return ctx
}
