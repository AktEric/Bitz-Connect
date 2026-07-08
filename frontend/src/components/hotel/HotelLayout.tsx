import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import {
  LayoutDashboard,
  Briefcase,
  Users,
  FileText,
  Settings,
  Shield,
  LogOut,
  Menu,
  X,
  Building2,
} from 'lucide-react'
import { useHotelAuth } from '@/hooks/useHotelAuth'

interface NavItem {
  label: string
  to: string
  Icon: React.ElementType
}

function buildNavItems(slug: string): NavItem[] {
  return [
    { label: 'Editar Perfil',           to: `/hotel/${slug}/dashboard`,  Icon: LayoutDashboard },
    { label: 'Gerenciar Vagas',          to: `/hotel/${slug}/jobs`,       Icon: Briefcase },
    { label: 'Gerenciar Colaboradores',  to: `/hotel/${slug}/employees`,  Icon: Users },
    { label: 'Publicações',              to: `/hotel/${slug}/posts`,      Icon: FileText },
    { label: 'Configurações',            to: `/hotel/${slug}/settings`,   Icon: Settings },
    { label: 'Administradores',          to: `/hotel/${slug}/admins`,     Icon: Shield },
  ]
}

const navLinkBase =
  'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors'

const navLinkInactive =
  'text-amber-100 hover:bg-amber-700/60 hover:text-white'

const navLinkActive =
  'bg-white/15 text-white shadow-sm'

/**
 * Exclusive layout for hotel dashboard routes.
 * Renders a sidebar with hotel-specific menus — never shares menus with AppLayout.
 * Logout calls exitHotel() and redirects to /login/hotel.
 * Requirements: 3.2, 3.4
 */
export default function HotelLayout() {
  const { session, exitHotel } = useHotelAuth()
  const navigate = useNavigate()
  const [sidebarOpen, setSidebarOpen] = useState(false)

  const slug = session?.hotelSlug ?? ''
  const hotelName = session?.hotelName ?? 'Hotel'
  const navItems = buildNavItems(slug)

  function handleLogout() {
    exitHotel()
    navigate('/login/hotel', { replace: true })
  }

  function closeSidebar() {
    setSidebarOpen(false)
  }

  const SidebarContent = (
    <div className="flex h-full flex-col">
      {/* Hotel brand */}
      <div className="flex items-center gap-3 border-b border-amber-700/50 px-4 py-5">
        <div className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-white/20">
          <Building2 className="h-5 w-5 text-white" />
        </div>
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-white leading-tight">{hotelName}</p>
          <p className="text-xs text-amber-200">Painel do Hotel</p>
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-3 py-4">
        <ul className="space-y-1">
          {navItems.map(({ label, to, Icon }) => (
            <li key={to}>
              <NavLink
                to={to}
                end={to.endsWith('/dashboard')}
                onClick={closeSidebar}
                className={({ isActive }) =>
                  `${navLinkBase} ${isActive ? navLinkActive : navLinkInactive}`
                }
              >
                <Icon className="h-4 w-4 flex-shrink-0" />
                <span>{label}</span>
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>

      {/* Logout */}
      <div className="border-t border-amber-700/50 px-3 py-4">
        <button
          type="button"
          onClick={handleLogout}
          className={`${navLinkBase} w-full text-amber-200 hover:bg-red-600/40 hover:text-white`}
        >
          <LogOut className="h-4 w-4 flex-shrink-0" />
          <span>Sair</span>
        </button>
      </div>
    </div>
  )

  return (
    <div className="flex h-screen bg-gray-50">
      {/* ── Desktop sidebar ── */}
      <aside className="hidden w-64 flex-shrink-0 bg-gradient-to-b from-amber-600 to-amber-700 lg:flex lg:flex-col">
        {SidebarContent}
      </aside>

      {/* ── Mobile: overlay + drawer ── */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/40 lg:hidden"
          aria-hidden="true"
          onClick={closeSidebar}
        />
      )}

      <aside
        className={`fixed inset-y-0 left-0 z-50 w-64 flex-shrink-0 flex-col bg-gradient-to-b from-amber-600 to-amber-700 transition-transform duration-200 lg:hidden ${
          sidebarOpen ? 'flex translate-x-0' : '-translate-x-full'
        }`}
        aria-label="Menu de navegação do hotel"
      >
        {/* Close button */}
        <button
          type="button"
          onClick={closeSidebar}
          className="absolute right-3 top-3 rounded-lg p-1.5 text-amber-200 hover:bg-amber-700/60 hover:text-white lg:hidden"
          aria-label="Fechar menu"
        >
          <X className="h-5 w-5" />
        </button>
        {SidebarContent}
      </aside>

      {/* ── Main content area ── */}
      <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
        {/* Mobile top bar */}
        <header className="flex items-center gap-3 border-b border-gray-200 bg-white px-4 py-3 lg:hidden">
          <button
            type="button"
            onClick={() => setSidebarOpen(true)}
            className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100"
            aria-label="Abrir menu"
          >
            <Menu className="h-5 w-5" />
          </button>
          <div className="flex items-center gap-2 min-w-0">
            <Building2 className="h-5 w-5 flex-shrink-0 text-amber-500" />
            <span className="truncate text-sm font-semibold text-gray-800">{hotelName}</span>
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 overflow-y-auto">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
