import {
  createContext,
  useContext,
  useState,
  useCallback,
  useEffect,
  type ReactNode,
} from 'react'
import type { HotelSession } from '@/types/hotel-auth.types'

interface HotelAuthContextValue {
  session: HotelSession | null
  isHotelAuthenticated: boolean
  enterHotel: (session: HotelSession) => void
  exitHotel: () => void
}

const HotelAuthContext = createContext<HotelAuthContextValue | null>(null)

const STORAGE_KEY = 'hotel_session'

function getStoredSession(): HotelSession | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? (JSON.parse(raw) as HotelSession) : null
  } catch {
    return null
  }
}

/**
 * Provides hotel session state and actions to the app.
 * Does NOT manage its own Sanctum token — uses the token from AuthContext.
 * Requirements: 1.9, 3.7
 */
export function HotelAuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<HotelSession | null>(getStoredSession)

  const enterHotel = useCallback((newSession: HotelSession) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(newSession))
    setSession(newSession)
  }, [])

  const exitHotel = useCallback(() => {
    localStorage.removeItem(STORAGE_KEY)
    setSession(null)
  }, [])

  // Logout geral (manual ou 401 do axios) também deve encerrar a sessão de
  // hotel — sem isso, uma sessão obsoleta causa loop de redirecionamento
  // entre HotelPrivateRoute e GuestRoute (isAuthenticated=false mas
  // isHotelAuthenticated=true).
  useEffect(() => {
    window.addEventListener('auth:unauthenticated', exitHotel)
    return () => window.removeEventListener('auth:unauthenticated', exitHotel)
  }, [exitHotel])

  return (
    <HotelAuthContext.Provider
      value={{
        session,
        isHotelAuthenticated: !!session,
        enterHotel,
        exitHotel,
      }}
    >
      {children}
    </HotelAuthContext.Provider>
  )
}

export function useHotelAuthContext(): HotelAuthContextValue {
  const ctx = useContext(HotelAuthContext)
  if (!ctx)
    throw new Error('useHotelAuthContext must be used inside <HotelAuthProvider>')
  return ctx
}
