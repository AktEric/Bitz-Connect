import { NavLink, Outlet, useNavigate, Link, useLocation } from 'react-router-dom'
import {
  Home, Briefcase, Search, MessageSquare, Users, Bell,
  LogOut, Settings, Bookmark, User, ChevronDown,
  Building2, Sparkles, Menu, X, Calendar, UsersRound,
  Video as VideoIcon, GraduationCap, Handshake, LayoutDashboard,
} from 'lucide-react'
import { useState, useRef, useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useAuth } from '@/hooks/useAuth'
import { useUnreadCount } from '@/hooks/useNotifications'
import { useUnreadMessages, chatKeys } from '@/hooks/useChat'
import { usePendingConnections } from '@/hooks/useConnections'
import { useMyHotels } from '@/hooks/useHotel'
import { Avatar } from '@/components/ui/Avatar'
import { cn } from '@/utils/cn'
import NotificationBell from '@/components/common/NotificationBell'
import Logo from '@/components/common/Logo'

/** Meu primeiro hotel administrado que tem plano PRO ativo, se houver. */
function useMyProHotel() {
  const { data } = useMyHotels()
  return data?.data.find((hotel) => hotel.is_pro)
}

// ──────────────────────────────────────────────────────────
// Nav config
// ──────────────────────────────────────────────────────────

const NAV_MAIN = [
  { to: '/feed',    label: 'Início',      Icon: Home },
  { to: '/network', label: 'Minha Rede',  Icon: Users },
  { to: '/videos',  label: 'Vídeos',      Icon: VideoIcon },
  { to: '/jobs',    label: 'Vagas',       Icon: Briefcase },
  { to: '/chat',    label: 'Mensagens',   Icon: MessageSquare },
  { to: '/profile', label: 'Perfil',      Icon: User },
] as const

const NAV_EXTRA = [
  { to: '/search',       label: 'Pesquisar',        Icon: Search },
  { to: '/courses',      label: 'Cursos',           Icon: GraduationCap },
  { to: '/mentorships',  label: 'Mentorias',        Icon: Handshake },
  { to: '/connections',  label: 'Conexões',         Icon: Users },
  { to: '/plans',        label: 'HotelConnect PRO', Icon: Sparkles },
] as const

// Bottom navigation (mobile) — 6 items
const NAV_BOTTOM = [
  { to: '/feed',          label: 'Início',       Icon: Home },
  { to: '/network',       label: 'Minha Rede',   Icon: Users },
  { to: '/jobs',          label: 'Vagas',        Icon: Briefcase },
  { to: '/chat',          label: 'Mensagens',    Icon: MessageSquare },
  { to: '/notifications', label: 'Notificações', Icon: Bell },
  { to: '/profile',       label: 'Perfil',       Icon: User },
] as const

// Drawer menu items (mobile)
const DRAWER_ITEMS = [
  { to: '/profile',  label: 'Perfil',        Icon: User,       enabled: true },
  { to: '/videos',   label: 'Vídeos',        Icon: VideoIcon,  enabled: true },
  { to: '/courses',  label: 'Cursos',        Icon: GraduationCap, enabled: true },
  { to: '/mentorships', label: 'Mentorias',  Icon: Handshake,  enabled: true },
  { to: '/plans',    label: 'HotelConnect PRO', Icon: Sparkles, enabled: true },
  { to: '/hotels',   label: 'Meus Hotéis',   Icon: Building2,  enabled: true },
  { to: '/saved',    label: 'Itens salvos',  Icon: Bookmark,   enabled: false },
  { to: '/events',   label: 'Eventos',       Icon: Calendar,   enabled: false },
  { to: '/groups',   label: 'Grupos',        Icon: UsersRound, enabled: false },
  { to: '/settings', label: 'Configurações', Icon: Settings,   enabled: false },
] as const

// ──────────────────────────────────────────────────────────
// Sidebar nav item
// ──────────────────────────────────────────────────────────

function SideNavItem({
  to, label, Icon, badge, collapsed,
}: {
  to: string; label: string; Icon: React.ElementType
  badge?: number; collapsed?: boolean
}) {
  return (
    <NavLink
      to={to}
      end
      title={collapsed ? label : undefined}
      className={({ isActive }) =>
        cn(
          'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-150',
          isActive
            ? 'bg-violet-50 text-violet-700'
            : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
          collapsed && 'justify-center px-2',
        )
      }
    >
      {({ isActive }) => (
        <>
          <div className="relative flex-shrink-0">
            <Icon className={cn('h-5 w-5 flex-shrink-0', isActive ? 'text-violet-600' : '')} />
            {badge && badge > 0 && (
              <span className="absolute -top-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white">
                {badge > 9 ? '9+' : badge}
              </span>
            )}
          </div>
          {!collapsed && <span className="truncate">{label}</span>}
          {isActive && !collapsed && (
            <span className="ml-auto h-5 w-1 rounded-full bg-violet-600" />
          )}
        </>
      )}
    </NavLink>
  )
}

// ──────────────────────────────────────────────────────────
// Premium card
// ──────────────────────────────────────────────────────────

const PREMIUM_CARD_DISMISSED_KEY = 'premium_card_dismissed'

function PremiumCard({ collapsed }: { collapsed: boolean }) {
  const navigate = useNavigate()
  const { user } = useAuth()
  const [dismissed, setDismissed] = useState(
    () => localStorage.getItem(PREMIUM_CARD_DISMISSED_KEY) === '1',
  )

  if (collapsed || dismissed || user?.is_pro) return null

  function handleDismiss() {
    localStorage.setItem(PREMIUM_CARD_DISMISSED_KEY, '1')
    setDismissed(true)
  }

  return (
    <div className="relative mx-3 mb-3 rounded-2xl bg-gradient-to-br from-violet-600 to-violet-800 p-4 text-white">
      <button
        onClick={handleDismiss}
        aria-label="Fechar"
        className="absolute right-2 top-2 rounded-md p-1 text-violet-200 hover:bg-white/10 hover:text-white transition-colors"
      >
        <X className="h-3.5 w-3.5" />
      </button>
      <div className="flex items-center gap-2 mb-2">
        <Sparkles className="h-4 w-4 text-yellow-300" />
        <span className="text-xs font-semibold">HotelConnect Pro</span>
      </div>
      <p className="text-xs text-violet-200 leading-snug mb-3">
        Acesse análises avançadas, destaque seu perfil e muito mais.
      </p>
      <button
        onClick={() => navigate('/plans')}
        className="w-full rounded-lg bg-white/20 py-1.5 text-xs font-medium text-white hover:bg-white/30 transition-colors"
      >
        Conhecer planos
      </button>
    </div>
  )
}

// ──────────────────────────────────────────────────────────
// Sidebar — desktop only (hidden below lg: 1024px)
// ──────────────────────────────────────────────────────────

function Sidebar({ collapsed, onToggle }: { collapsed: boolean; onToggle: () => void }) {
  const { user, logout } = useAuth()
  const { data: unreadData } = useUnreadCount()
  const unread = unreadData?.count ?? 0
  const { data: unreadMsgData } = useUnreadMessages()
  const unreadMessages = unreadMsgData?.count ?? 0

  if (!user) return null

  return (
    <aside
      className={cn(
        // hidden on mobile, flex on desktop
        'fixed left-0 top-0 z-30 hidden h-screen flex-col border-r border-gray-100 bg-white transition-all duration-300 lg:flex',
        collapsed ? 'w-16' : 'w-64',
      )}
    >
      {/* Logo */}
      <div className={cn('flex h-16 items-center border-b border-gray-100 px-4 flex-shrink-0', collapsed && 'justify-center px-2')}>
        {collapsed ? (
          <Link to="/feed">
            <Logo iconClassName="h-8 w-8" showText={false} />
          </Link>
        ) : (
          <Link to="/feed" className="flex items-center gap-2">
            <Logo iconClassName="h-8 w-8" textClassName="text-lg" />
          </Link>
        )}
        <button
          onClick={onToggle}
          className={cn('ml-auto rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 transition-colors', collapsed && 'ml-0')}
        >
          {collapsed ? <Menu className="h-4 w-4" /> : <X className="h-4 w-4" />}
        </button>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-2 py-3 space-y-0.5">
        {NAV_MAIN.map(({ to, label, Icon }) => (
          <SideNavItem
            key={to} to={to} label={label} Icon={Icon} collapsed={collapsed}
            badge={to === '/chat' && unreadMessages > 0 ? unreadMessages : undefined}
          />
        ))}

        {/* Notifications with badge */}
        <NavLink
          to="/notifications"
          title={collapsed ? 'Notificações' : undefined}
          className={({ isActive }) =>
            cn(
              'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-150',
              isActive ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
              collapsed && 'justify-center px-2',
            )
          }
        >
          <div className="relative flex-shrink-0">
            <Bell className="h-5 w-5" />
            {unread > 0 && (
              <span className="absolute -top-1 -right-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white">
                {unread > 9 ? '9+' : unread}
              </span>
            )}
          </div>
          {!collapsed && <span className="truncate">Notificações</span>}
        </NavLink>

        {!collapsed && (
          <>
            <div className="my-3 h-px bg-gray-100" />
            <p className="mb-1 px-3 text-[10px] font-semibold uppercase tracking-widest text-gray-400">Mais</p>
            {NAV_EXTRA.map(({ to, label, Icon }) => (
              <SideNavItem key={`${to}-extra`} to={to} label={label} Icon={Icon} />
            ))}
            <SideNavItem to="/saved" label="Itens Salvos" Icon={Bookmark} />

            <div className="my-3 h-px bg-gray-100" />
            <p className="mb-1 px-3 text-[10px] font-semibold uppercase tracking-widest text-gray-400">Empresas / Hotéis</p>
            <SideNavItem to="/hotels" label="Meus Hotéis" Icon={Building2} />
          </>
        )}
      </nav>

      <PremiumCard collapsed={collapsed} />

      {/* User footer */}
      <div className={cn('border-t border-gray-100 p-3', collapsed && 'flex flex-col items-center gap-2')}>
        {collapsed ? (
          <>
            <Avatar src={user.avatar_url} name={user.name} size="sm" />
            <button onClick={() => logout()} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-red-500 transition-colors">
              <LogOut className="h-4 w-4" />
            </button>
          </>
        ) : (
          <div className="flex items-center gap-2.5">
            <Avatar src={user.avatar_url} name={user.name} size="sm" />
            <div className="flex-1 min-w-0">
              <p className="truncate text-sm font-medium text-gray-900">{user.name}</p>
              <p className="truncate text-xs text-gray-500">{user.email}</p>
            </div>
            <button onClick={() => logout()} title="Sair" className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-red-500 transition-colors">
              <LogOut className="h-4 w-4" />
            </button>
          </div>
        )}
      </div>
    </aside>
  )
}

// ──────────────────────────────────────────────────────────
// Mobile drawer — slides in from the left
// ──────────────────────────────────────────────────────────

function MobileDrawer({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { user, logout } = useAuth()

  useEffect(() => {
    document.body.style.overflow = open ? 'hidden' : ''
    return () => { document.body.style.overflow = '' }
  }, [open])

  if (!user) return null

  return (
    <>
      {/* Backdrop */}
      <div
        className={cn(
          'fixed inset-0 z-[60] bg-black/40 transition-opacity duration-200 lg:hidden',
          open ? 'opacity-100 pointer-events-auto' : 'pointer-events-none opacity-0',
        )}
        onClick={onClose}
        aria-hidden="true"
      />

      {/* Panel */}
      <div
        className={cn(
          'fixed inset-y-0 left-0 z-[70] flex w-[82%] max-w-xs flex-col bg-white shadow-2xl transition-transform duration-300 ease-out lg:hidden',
          open ? 'translate-x-0' : '-translate-x-full',
        )}
        role="dialog"
        aria-modal="true"
      >
        {/* Profile header */}
        <div className="flex items-center gap-3 border-b border-gray-100 p-4">
          <Avatar src={user.avatar_url} name={user.name} size="md" />
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-semibold text-gray-900">{user.name}</p>
            <p className="truncate text-xs text-gray-500">{user.email}</p>
          </div>
          <button
            onClick={onClose}
            className="flex-shrink-0 rounded-lg p-2 text-gray-400 hover:bg-gray-100"
            aria-label="Fechar menu"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Menu items */}
        <nav className="flex-1 overflow-y-auto p-2">
          {DRAWER_ITEMS.map(({ to, label, Icon, enabled }) =>
            enabled ? (
              <NavLink
                key={to}
                to={to}
                onClick={onClose}
                className={({ isActive }) =>
                  cn(
                    'flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium transition-colors',
                    isActive ? 'bg-violet-50 text-violet-700' : 'text-gray-700 hover:bg-gray-50',
                  )
                }
              >
                <Icon className="h-5 w-5 flex-shrink-0" />
                {label}
              </NavLink>
            ) : (
              <div
                key={to}
                className="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium text-gray-400"
              >
                <Icon className="h-5 w-5 flex-shrink-0" />
                {label}
                <span className="ml-auto rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-400">
                  Em breve
                </span>
              </div>
            ),
          )}
        </nav>

        <div className="border-t border-gray-100 p-2">
          <button
            onClick={() => { onClose(); logout() }}
            className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium text-red-500 hover:bg-red-50 transition-colors"
          >
            <LogOut className="h-5 w-5 flex-shrink-0" />
            Sair
          </button>
        </div>
      </div>
    </>
  )
}

// ──────────────────────────────────────────────────────────
// Bottom navigation — mobile only
// ──────────────────────────────────────────────────────────

function BottomNav() {
  const { data: unreadData } = useUnreadCount()
  const unread = unreadData?.count ?? 0
  const { data: unreadMsgData } = useUnreadMessages()
  const unreadMessages = unreadMsgData?.count ?? 0
  const { data: pending = [] } = usePendingConnections()
  const pendingCount = Array.isArray(pending) ? pending.length : 0

  const badgeFor = (to: string): number => {
    if (to === '/chat') return unreadMessages
    if (to === '/notifications') return unread
    if (to === '/network') return pendingCount
    return 0
  }

  return (
    <nav
      className="fixed inset-x-0 bottom-0 z-40 flex items-stretch justify-around border-t border-gray-100 bg-white/95 backdrop-blur-sm lg:hidden"
      style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
    >
      {NAV_BOTTOM.map(({ to, label, Icon }) => {
        const badge = badgeFor(to)
        return (
          <NavLink
            key={to}
            to={to}
            end={to === '/feed'}
            className={({ isActive }) =>
              cn(
                'flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[10px] font-medium transition-colors',
                isActive ? 'text-violet-600' : 'text-gray-500',
              )
            }
          >
            <div className="relative">
              <Icon className="h-5 w-5" />
              {badge > 0 && (
                <span className="absolute -top-1.5 -right-2 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white">
                  {badge > 9 ? '9+' : badge}
                </span>
              )}
            </div>
            <span className="truncate">{label}</span>
          </NavLink>
        )
      })}
    </nav>
  )
}

// ──────────────────────────────────────────────────────────
// Messages badge (desktop topbar)
// ──────────────────────────────────────────────────────────

/** Ícone de volta ao painel do hotel — só aparece para admins de hotel com plano PRO. */
function HotelPanelButton() {
  const proHotel = useMyProHotel()

  if (!proHotel) return null

  return (
    <Link
      to={`/hotel/${proHotel.slug}/dashboard`}
      title={`Painel do hotel — ${proHotel.name}`}
      className="relative flex items-center justify-center h-9 w-9 rounded-xl text-amber-600 hover:bg-amber-50 transition-colors"
      aria-label="Painel do hotel"
    >
      <LayoutDashboard className="h-5 w-5" />
      <Sparkles className="absolute -top-1 -right-1 h-3 w-3 text-amber-500" />
    </Link>
  )
}

function MessagesBadgeButton() {
  const navigate = useNavigate()
  const qc = useQueryClient()
  const { data } = useUnreadMessages()
  const count = data?.count ?? 0

  return (
    <button
      onClick={() => {
        qc.invalidateQueries({ queryKey: chatKeys.unread })
        navigate('/chat')
      }}
      className="relative flex items-center justify-center h-9 w-9 rounded-xl text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors"
      aria-label="Mensagens"
    >
      <MessageSquare className="h-5 w-5" />
      {count > 0 && (
        <span className="absolute top-0.5 right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
          {count > 9 ? '9+' : count}
        </span>
      )}
    </button>
  )
}

// ──────────────────────────────────────────────────────────
// Topbar — desktop only (hidden below lg: 1024px)
// ──────────────────────────────────────────────────────────

function Topbar({ sidebarWidth }: { sidebarWidth: number }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [userOpen, setUserOpen] = useState(false)
  const userRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    function h(e: MouseEvent) {
      if (userRef.current && !userRef.current.contains(e.target as Node)) setUserOpen(false)
    }
    document.addEventListener('mousedown', h)
    return () => document.removeEventListener('mousedown', h)
  }, [])

  return (
    <header
      className="fixed top-0 right-0 z-20 hidden h-16 items-center border-b border-gray-100 bg-white/95 backdrop-blur-sm px-4 gap-3 transition-all duration-300 lg:flex"
      style={{ left: sidebarWidth }}
    >
      <Link
        to="/search"
        className="flex flex-1 max-w-sm items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-400 hover:border-violet-300 hover:bg-white transition-all"
      >
        <Search className="h-4 w-4 flex-shrink-0" />
        <span>Pesquisar usuários, hotéis, vagas...</span>
      </Link>

      <div className="flex-1" />

      <div className="flex items-center gap-1">
        <HotelPanelButton />
        <NotificationBell />
        <MessagesBadgeButton />

        {user && (
          <div className="relative" ref={userRef}>
            <button
              onClick={() => setUserOpen((v) => !v)}
              className="flex items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-gray-100 transition-colors"
            >
              <Avatar src={user.avatar_url} name={user.name} size="sm" />
              <ChevronDown className={cn('h-3.5 w-3.5 text-gray-400 transition-transform duration-200', userOpen && 'rotate-180')} />
            </button>

            {userOpen && (
              <div className="absolute right-0 top-full mt-2 w-56 animate-scale-in rounded-2xl border border-gray-100 bg-white shadow-panel py-1.5 z-50">
                <div className="px-4 py-3 border-b border-gray-100">
                  <p className="font-semibold text-sm text-gray-900 truncate">{user.name}</p>
                  <p className="text-xs text-gray-500 truncate mt-0.5">{user.email}</p>
                </div>
                {[
                  { label: 'Meu perfil', to: '/profile' },
                  { label: 'Editar perfil', to: '/profile/edit' },
                ].map(({ label, to }) => (
                  <button key={to} onClick={() => { navigate(to); setUserOpen(false) }}
                    className="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    {label}
                  </button>
                ))}
                <div className="border-t border-gray-100 mt-1">
                  <button onClick={() => logout()}
                    className="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors">
                    <LogOut className="h-4 w-4" /> Sair
                  </button>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </header>
  )
}

// ──────────────────────────────────────────────────────────
// Mobile topbar — compact header for small screens
// ──────────────────────────────────────────────────────────

function MobileTopbar({ onOpenDrawer }: { onOpenDrawer: () => void }) {
  const { data: unreadData } = useUnreadCount()
  const unread = unreadData?.count ?? 0

  return (
    <header
      className="fixed inset-x-0 top-0 z-30 flex h-14 items-center gap-2 border-b border-gray-100 bg-white/95 px-3 backdrop-blur-sm lg:hidden"
      style={{ paddingTop: 'env(safe-area-inset-top)' }}
    >
      <button
        onClick={onOpenDrawer}
        className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl text-gray-600 hover:bg-gray-100 transition-colors"
        aria-label="Abrir menu"
      >
        <Menu className="h-5 w-5" />
      </button>

      <Link to="/feed" className="flex items-center gap-1.5">
        <Logo iconClassName="h-7 w-7" textClassName="text-base" />
      </Link>

      <div className="flex-1" />

      <HotelPanelButton />

      <Link
        to="/search"
        className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl text-gray-600 hover:bg-gray-100 transition-colors"
        aria-label="Pesquisar"
      >
        <Search className="h-5 w-5" />
      </Link>

      <Link
        to="/notifications"
        className="relative flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl text-gray-600 hover:bg-gray-100 transition-colors"
        aria-label="Notificações"
      >
        <Bell className="h-5 w-5" />
        {unread > 0 && (
          <span className="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white">
            {unread > 9 ? '9+' : unread}
          </span>
        )}
      </Link>
    </header>
  )
}

// ──────────────────────────────────────────────────────────
// AppLayout
// ──────────────────────────────────────────────────────────

export default function AppLayout() {
  const [collapsed, setCollapsed] = useState(false)
  const [drawerOpen, setDrawerOpen] = useState(false)
  const sidebarWidth = collapsed ? 64 : 256
  const location = useLocation()

  // Close drawer on navigation
  useEffect(() => {
    setDrawerOpen(false)
  }, [location.pathname])

  return (
    <div className="min-h-screen bg-gray-50/80">
      {/* Desktop chrome */}
      <Sidebar collapsed={collapsed} onToggle={() => setCollapsed((v) => !v)} />
      <Topbar sidebarWidth={sidebarWidth} />

      {/* Mobile chrome */}
      <MobileTopbar onOpenDrawer={() => setDrawerOpen(true)} />
      <MobileDrawer open={drawerOpen} onClose={() => setDrawerOpen(false)} />
      <BottomNav />

      {/* Main content */}
      <div
        className={cn(
          'min-h-screen transition-all duration-300',
          // Mobile: top padding for topbar (h-14) + bottom padding for bottom nav
          'pt-14 pb-20',
          // Desktop: top padding for topbar (h-16) + left padding for sidebar, no bottom nav
          'lg:pb-0 lg:pt-16',
          collapsed ? 'lg:pl-16' : 'lg:pl-64',
        )}
      >
        <main className="py-4 lg:py-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
