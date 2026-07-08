// @vitest-environment jsdom
import { describe, it, expect, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { AuthProvider } from '@/contexts/AuthContext'
import { ToastProvider } from '@/contexts/ToastContext'
import PlansPage from './PlansPage'

// Mock do serviço de assinatura com o shape real da API /plans.
vi.mock('@/services/subscription.service', () => ({
  default: {
    getPlans: () => Promise.resolve({
      success: true, message: 'ok',
      data: {
        currency: 'BRL',
        plans: [
          { id: 'free', name: 'Gratuito', tagline: 't', price_cents: 0, interval: null, audience: 'user', featured: false, features: [], benefits: [{ label: 'Perfil' }] },
          { id: 'pro_consultor', name: 'PRO Consultor', tagline: 't', price_cents: 4990, interval: 'month', audience: 'user', featured: true, features: ['pro_badge'], benefits: [{ label: 'Vídeos' }, { label: 'Cursos', prepared: true }] },
          { id: 'pro_empresa', name: 'PRO Empresa', tagline: 't', price_cents: 19990, interval: 'month', audience: 'hotel', featured: true, features: ['company_dashboard'], benefits: [{ label: 'Dashboard' }] },
        ],
      },
    }),
    getMySubscription: () => Promise.resolve({ success: true, message: 'ok', data: { plan: 'free', subscription: null, entitlements: {} } }),
    subscribe: vi.fn(),
    cancel: vi.fn(),
  },
}))

vi.mock('@/services/hotel.service', () => ({
  default: { getMyHotels: () => Promise.resolve({ success: true, message: 'ok', data: [], meta: {}, links: {} }) },
}))

function renderPage() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={qc}>
      <AuthProvider>
        <ToastProvider>
          <MemoryRouter initialEntries={['/plans']}>
            <PlansPage />
          </MemoryRouter>
        </ToastProvider>
      </AuthProvider>
    </QueryClientProvider>,
  )
}

describe('PlansPage', () => {
  it('renderiza sem crashar e mostra os planos', async () => {
    renderPage()
    expect(screen.getByText('Escolha seu plano')).toBeTruthy()
    await waitFor(() => {
      expect(screen.getByText('PRO Consultor')).toBeTruthy()
    })
  })
})
