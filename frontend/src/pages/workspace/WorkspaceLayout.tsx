import { useEffect, useState } from 'react'
import { NavLink, Outlet, Link, useParams, useLocation, useNavigate } from 'react-router-dom'
import {
  Home, Clock, Newspaper, Users, ClipboardList, Wallet, Target, GraduationCap, MessageSquare,
  Menu, X, Settings2, ArrowLeftRight, LogOut, Globe, CheckCircle2, type LucideIcon,
} from 'lucide-react'
import { useWorkspaceInfo } from '@/hooks/useWorkspace'
import { useAuth } from '@/hooks/useAuth'
import { Avatar } from '@/components/ui/Avatar'
import NotificationBell from '@/components/common/NotificationBell'
import ChatSidebar from '@/components/chat/ChatSidebar'
import { cn } from '@/utils/cn'
import { Spinner } from './ui'

interface NavItem { to: string; label: string; Icon: LucideIcon; end?: boolean; badge?: number }
interface NavGroup { title: string; items: NavItem[] }

/** Casca do Workspace: barra superior, menu por área (desktop), navegação inferior e gaveta (mobile). */
export default function WorkspaceLayout() {
  const { slug = '' } = useParams()
  const { data: info, isLoading, isError } = useWorkspaceInfo(slug)
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [chatOpen, setChatOpen] = useState(false)
  const [drawer, setDrawer] = useState(false)
  const [menu, setMenu] = useState(false)

  useEffect(() => { setDrawer(false); setMenu(false) }, [location.pathname])

  if (isLoading) return <div className="min-h-screen bg-slate-50"><Spinner /></div>
  if (isError || !info) {
    return (
      <div className="min-h-screen bg-slate-50 px-4 py-20 text-center">
        <p className="font-medium text-gray-700">Você não tem acesso a este Workspace.</p>
        <p className="mt-1 text-sm text-gray-400">O acesso é liberado após o vínculo com a empresa ser aprovado.</p>
        <Link to="/w" className="mt-4 inline-block text-sm font-medium text-violet-600">Ver minhas empresas</Link>
      </div>
    )
  }

  const c = info.counts
  const lead = info.me.is_admin || info.me.is_manager
  const groups: NavGroup[] = [
    {
      title: 'Meu dia',
      items: [
        { to: '', label: 'Início', Icon: Home, end: true },
        { to: 'ponto', label: 'Ponto', Icon: Clock },
        { to: 'mural', label: 'Mural', Icon: Newspaper },
      ],
    },
    {
      title: 'Meu RH',
      items: [
        { to: 'solicitacoes', label: 'Férias e solicitações', Icon: ClipboardList, badge: lead ? 0 : c.my_open_requests },
        { to: 'recebimentos', label: 'Holerites e recebimentos', Icon: Wallet, badge: c.new_payslips },
        { to: 'metas', label: 'Metas', Icon: Target },
        { to: 'desenvolvimento', label: 'Desenvolvimento', Icon: GraduationCap },
      ],
    },
    { title: 'Empresa', items: [{ to: 'equipe', label: 'Pessoas', Icon: Users }] },
  ]
  if (info.me.is_admin) {
    groups[groups.length - 1].items.push({ to: 'configuracoes', label: 'Papéis e estrutura', Icon: Settings2 })
  }
  if (lead) {
    groups.splice(2, 0, {
      title: 'Gestão',
      items: [
        { to: 'solicitacoes?aba=aprovar', label: 'Aprovações', Icon: CheckCircle2, badge: c.requests_to_review },
        { to: 'ponto?aba=time', label: 'Ponto do time', Icon: Clock },
      ],
    })
  }
  const mobileTabs: NavItem[] = [
    { to: '', label: 'Início', Icon: Home, end: true },
    { to: 'ponto', label: 'Ponto', Icon: Clock },
    { to: 'mural', label: 'Mural', Icon: Newspaper },
  ]

  const roleLabel = info.me.job_title ?? (info.me.is_admin ? 'RH / Gestão' : 'Colaborador')

  const renderGroups = (onNavigate?: () => void) => (
    <nav className="space-y-5" aria-label="Menu do Workspace">
      {groups.map((g) => (
        <div key={g.title}>
          <p className="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{g.title}</p>
          <ul className="space-y-0.5">
            {g.items.map(({ to, label, Icon, end, badge }) => (
              <li key={to + label}>
                <NavLink
                  to={to}
                  end={end}
                  onClick={onNavigate}
                  className={({ isActive }) =>
                    cn(
                      'flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition-colors',
                      isActive && !to.includes('?') ? 'bg-violet-50 text-violet-700' : 'text-gray-600 hover:bg-gray-100',
                    )
                  }
                >
                  <Icon className="h-[18px] w-[18px]" />
                  <span className="flex-1 truncate">{label}</span>
                  {!!badge && badge > 0 && (
                    <span className="rounded-full bg-violet-600 px-1.5 py-0.5 text-[11px] font-semibold leading-none text-white">{badge}</span>
                  )}
                </NavLink>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </nav>
  )

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="sticky top-0 z-40 flex h-14 items-center gap-3 border-b border-gray-200 bg-white/90 px-3 backdrop-blur sm:px-5">
        <button type="button" className="rounded-lg p-2 text-gray-600 hover:bg-gray-100 lg:hidden" onClick={() => setDrawer(true)} aria-label="Abrir menu">
          <Menu className="h-5 w-5" />
        </button>
        <Link to={`/w/${slug}`} className="flex min-w-0 items-center gap-2.5">
          <Avatar src={info.hotel.avatar_url} name={info.hotel.name} size="sm" />
          <span className="truncate text-sm font-semibold text-gray-900">{info.hotel.name}</span>
        </Link>
        <div className="ml-auto flex items-center gap-1">
          <button
            type="button"
            onClick={() => setChatOpen(true)}
            className="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 xl:hidden"
            aria-label="Abrir mensagens"
          >
            <MessageSquare className="h-[18px] w-[18px]" /> <span className="hidden sm:inline">Mensagens</span>
          </button>
          <NotificationBell />
          <div className="relative">
            <button type="button" onClick={() => setMenu((v) => !v)} className="ml-1 flex items-center rounded-full ring-offset-2 hover:ring-2 hover:ring-violet-200" aria-label="Minha conta">
              <Avatar src={user?.avatar_url} name={user?.name ?? 'U'} size="sm" />
            </button>
            {menu && (
              <div className="absolute right-0 mt-2 w-60 rounded-2xl border border-gray-100 bg-white p-2 shadow-xl">
                <div className="px-3 py-2">
                  <p className="truncate text-sm font-semibold text-gray-900">{user?.name}</p>
                  <p className="truncate text-xs text-gray-400">{roleLabel}</p>
                </div>
                <Link to="/w" className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><ArrowLeftRight className="h-4 w-4" /> Trocar de empresa</Link>
                <Link to="/feed" className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><Globe className="h-4 w-4" /> Rede profissional</Link>
                <button
                  type="button"
                  onClick={async () => { await logout(); navigate('/entrar', { replace: true }) }}
                  className="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-red-600 hover:bg-red-50"
                >
                  <LogOut className="h-4 w-4" /> Sair
                </button>
              </div>
            )}
          </div>
        </div>
      </header>

      <div className="flex w-full gap-6 px-3 pb-24 pt-4 sm:px-5 2xl:px-8 lg:pb-8 lg:pt-6">
        <aside className="hidden w-60 flex-shrink-0 lg:block">
          <div className="sticky top-20">{renderGroups()}</div>
        </aside>
        <main className="min-w-0 flex-1">
          <Outlet context={{ slug, info }} />
        </main>
        <aside className="hidden w-[340px] flex-shrink-0 xl:block" aria-label="Chat">
          <div className="sticky top-20 h-[calc(100vh-6.5rem)] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <ChatSidebar docked />
          </div>
        </aside>
      </div>

      {/* Navegação inferior (mobile) */}
      <nav className="fixed inset-x-0 bottom-0 z-40 flex border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Atalhos">
        {mobileTabs.map(({ to, label, Icon, end }) => (
          <NavLink
            key={label}
            to={to}
            end={end}
            className={({ isActive }) => cn('flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] font-medium', isActive ? 'text-violet-700' : 'text-gray-500')}
          >
            <Icon className="h-5 w-5" /> {label}
          </NavLink>
        ))}
        <button type="button" onClick={() => setChatOpen(true)} className="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] font-medium text-gray-500">
          <MessageSquare className="h-5 w-5" /> Chat
        </button>
        <button type="button" onClick={() => setDrawer(true)} className="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] font-medium text-gray-500">
          <Menu className="h-5 w-5" /> Mais
        </button>
      </nav>

      {drawer && (
        <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
          <div className="absolute inset-0 bg-black/40" onClick={() => setDrawer(false)} />
          <div className="absolute inset-y-0 left-0 w-72 overflow-y-auto bg-white p-4 shadow-xl">
            <div className="mb-4 flex items-center justify-between">
              <div className="flex min-w-0 items-center gap-2.5">
                <Avatar src={info.hotel.avatar_url} name={info.hotel.name} size="sm" />
                <span className="truncate text-sm font-semibold">{info.hotel.name}</span>
              </div>
              <button type="button" onClick={() => setDrawer(false)} className="rounded-lg p-1.5 hover:bg-gray-100" aria-label="Fechar menu"><X className="h-5 w-5" /></button>
            </div>
            {renderGroups(() => setDrawer(false))}
          </div>
        </div>
      )}

      <ChatSidebar open={chatOpen} onClose={() => setChatOpen(false)} />
    </div>
  )
}
