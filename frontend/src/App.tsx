import { useEffect } from 'react'
import { BrowserRouter } from 'react-router-dom'
import AppRoutes from '@/routes'
import TwoFactorPrompt from '@/components/auth/TwoFactorPrompt'
import { HotelAuthProvider } from '@/contexts/HotelAuthContext'
import { useToast } from '@/contexts/ToastContext'

export default function App() {
  const { error } = useToast()

  useEffect(() => {
    function handleForbidden(e: Event) {
      const message = (e as CustomEvent<{ message?: string }>).detail?.message ?? 'Acesso negado.'
      error(message)
    }

    window.addEventListener('auth:forbidden', handleForbidden)
    return () => {
      window.removeEventListener('auth:forbidden', handleForbidden)
    }
  }, [error])

  return (
    <BrowserRouter>
      <HotelAuthProvider>
        <AppRoutes />
        <TwoFactorPrompt />
      </HotelAuthProvider>
    </BrowserRouter>
  )
}
