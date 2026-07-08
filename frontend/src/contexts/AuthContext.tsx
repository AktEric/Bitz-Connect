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
    setToken(newToken)
    setUserState(newUser)
  }, [])

  const clearSession = useCallback(() => {
    localStorage.removeItem('token')
    localStorage.removeItem('user')
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
