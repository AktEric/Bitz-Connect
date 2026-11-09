import { useEffect, useState } from 'react'
import { NavLink, Outlet, Link, useParams, useLocation, useNavigate } from 'react-router-dom'
import {
  Home, Clock, Newspaper, Users, ClipboardList, Wallet, Target, GraduationCap, MessageSquare,
  Menu, X, Settings2, CalendarDays, ShieldCheck, ScrollText, Search, LayoutDashboard, Rocket, FileBarChart, ArrowLeftRight, LogOut, Briefcase, HeartPulse, Network, Moon, Sun, Globe, CheckCircle2, ChevronDown,
} from 'lucide-react'
import { useWorkspaceInfo } from '@/hooks/useWorkspace'
import { useAuth } from '@/hooks/useAuth'
import { Avatar } from '@/components/ui/Avatar'
import NotificationBell from '@/components/common/NotificationBell'
import ChatSidebar from '@/components/chat/ChatSidebar'
import VitoriaChat from '@/components/help/VitoriaChat'
import { cn } from '@/utils/cn'
import { getTheme, setTheme, type Theme } from '@/utils/theme'
import CommandPalette from './CommandPalette'
import { Spinner } from './ui'
import WorkspaceRail, { NavGroups, type NavGroup, type NavItem } from './WorkspaceRail'
import ShortcutsBar from './ShortcutsBar'
import PresenceRail, { type ChatPerson } from './PresenceRail'

/**
 * Casca do Workspace. Desktop: barra de ícones à esquerda, topo com busca central, barra de atalhos
 * e fileira de pessoas (chat) à direita. Celular: navegação inferior e gaveta.
 */
export default function WorkspaceLayout() {
  const { slug = '' } = useParams()
  const { data: info, isLoading, isError } = useWorkspaceInfo(slug)
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [chatOpen, setChatOpen] = useState(false)
  const [drawer, setDrawer] = useState(false)
  const [menu, setMenu] = useState(false)
  const [expanded, setExpanded] = useState(false)
  const [direct, setDirect] = useState<ChatPerson | null>(null)
  const [themeNow, setThemeNow] = useState<Theme>(getTheme)
  const [searchOpen, setSearchOpen] = useState(false)

  // Ctrl/Cmd+K abre a busca global em qualquer tela do Workspace
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); setSearchOpen((v) => !v) }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  useEffect(() => { setDrawer(false); setMenu(false); setExpanded(false) }, [location.pathname])

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
        { to: 'mural', label: 'Mural', Icon: Newspaper, badge: c.pending_acks },
        { to: 'calendario', label: 'Calendário', Icon: CalendarDays },
      ],
    },
    {
      title: 'Meu RH',
      items: [
        { to: 'solicitacoes', label: 'Férias e solicitações', Icon: ClipboardList, badge: lead ? 0 : c.my_open_requests },
        { to: 'recebimentos', label: 'Holerites e recebimentos', Icon: Wallet, badge: c.new_payslips },
        { to: 'metas', label: 'Metas', Icon: Target },
        { to: 'desenvolvimento', label: 'Desenvolvimento', Icon: GraduationCap },
        { to: 'pesquisas', label: 'Pesquisa de clima', Icon: HeartPulse },
      ],
    },
    { title: 'Empresa', items: [{ to: 'equipe', label: 'Pessoas', Icon: Users }, { to: 'organograma', label: 'Organograma', Icon: Network }, { to: 'vagas', label: 'Vagas', Icon: Briefcase }] },
  ]
  if (info.me.can_see_dashboard) {
    groups.splice(groups.length - 1, 0, { title: 'Diretoria', items: [{ to: 'painel', label: 'Painel da diretoria', Icon: LayoutDashboard }] })
  }
  if (info.me.is_admin) {
    groups[groups.length - 1].items.push({ to: 'comecar', label: 'Primeiros passos', Icon: Rocket })
    groups[groups.length - 1].items.push({ to: 'configuracoes', label: 'Papéis e estrutura', Icon: Settings2 })
    groups[groups.length - 1].items.push({ to: 'auditoria', label: 'Auditoria', Icon: ScrollText })
  }
  if (lead || info.me.can_see_dashboard) {
    groups[groups.length - 1].items.push({ to: 'relatorios', label: 'Relatórios', Icon: FileBarChart })
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

  const firstName = user?.name?.split(' ')[0] ?? ''
  const openChat = () => { setDirect(null); setChatOpen(true) }

  return (
    <div className="min-h-screen bg-slate-50">
      <a href="#conteudo" className="skip-link">Pular para o conteúdo</a>
      <WorkspaceRail groups={groups} expanded={expanded} onToggle={() => setExpanded((v) => !v)} />

      <div className="lg:pl-[68px] xl:pr-16">
        <header className="sticky top-0 z-40 grid h-14 grid-cols-[1fr_auto] items-center gap-3 border-b border-gray-200 bg-white/90 px-3 backdrop-blur sm:px-5 md:grid-cols-[1fr_minmax(0,28rem)_1fr] 2xl:px-8">
          <div className="flex min-w-0 items-center gap-2">
            <button type="button" className="rounded-lg p-2 text-gray-600 hover:bg-gray-100 lg:hidden" onClick={() => setDrawer(true)} aria-label="Abrir menu">
              <Menu className="h-5 w-5" />
            </button>
            <Link to={`/w/${slug}`} className="flex min-w-0 items-center gap-2.5">
              <Avatar src={info.hotel.avatar_url} name={info.hotel.name} size="sm" />
              <span className="truncate text-sm font-semibold text-gray-900">{info.hotel.name}</span>
            </Link>
          </div>

          <button
            type="button" onClick={() => setSearchOpen(true)} aria-label="Buscar (Ctrl+K)"
            className="hidden w-full items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-4 py-2 text-sm text-gray-400 transition hover:border-violet-300 hover:bg-white md:flex"
          >
            <span className="flex-1 truncate text-left">Buscar pessoas, páginas…</span>
            <kbd className="hidden rounded border border-gray-200 px-1.5 py-0.5 text-[10px] font-normal text-gray-400 lg:block">Ctrl K</kbd>
            <Search className="h-4 w-4 text-gray-500" />
          </button>

          <div className="flex items-center justify-end gap-1">
            <button type="button" onClick={() => setSearchOpen(true)} aria-label="Buscar" className="rounded-xl p-2 text-gray-600 hover:bg-gray-100 md:hidden">
              <Search className="h-[18px] w-[18px]" />
            </button>
            <button
              type="button"
              onClick={openChat}
              className="inline-flex items-center gap-2 rounded-xl p-2 text-sm font-medium text-gray-600 hover:bg-gray-100 xl:hidden"
              aria-label="Abrir mensagens"
            >
              <MessageSquare className="h-[18px] w-[18px]" />
            </button>
            <NotificationBell />
            <div className="relative">
              <button type="button" onClick={() => setMenu((v) => !v)} className="ml-1 flex items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-gray-100" aria-label="Minha conta" aria-expanded={menu}>
                <Avatar src={user?.avatar_url} name={user?.name ?? 'U'} size="sm" />
                <span className="hidden max-w-[10rem] truncate text-sm font-semibold text-gray-800 sm:block">{firstName}</span>
                <ChevronDown className="hidden h-4 w-4 text-gray-400 sm:block" />
              </button>
              {menu && (
                <div className="absolute right-0 mt-2 w-60 rounded-2xl border border-gray-100 bg-white p-2 shadow-xl">
                  <div className="px-3 py-2">
                    <p className="truncate text-sm font-semibold text-gray-900">{user?.name}</p>
                    <p className="truncate text-xs text-gray-400">{roleLabel}</p>
                  </div>
                  <Link to={`/w/${slug}/perfil`} className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><Users className="h-4 w-4" /> Meu perfil</Link>
                  <Link to={`/w/${slug}/seguranca`} className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><ShieldCheck className="h-4 w-4" /> Segurança</Link>
                  <Link to="/w" className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><ArrowLeftRight className="h-4 w-4" /> Trocar de empresa</Link>
                  <Link to="/feed" className="flex items-center gap-2 rounded-xl px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><Globe className="h-4 w-4" /> Rede profissional</Link>
                  <button type="button" onClick={() => { const n = getTheme() === "dark" ? "light" : "dark"; setTheme(n); setThemeNow(n) }} className="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    {themeNow === "dark" ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />} {themeNow === "dark" ? "Tema claro" : "Tema escuro"}
                  </button>
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

        <ShortcutsBar slug={slug} items={groups.flatMap((g) => g.items)} />

        <main id="conteudo" tabIndex={-1} className="min-w-0 px-3 pb-24 pt-4 outline-none sm:px-5 lg:px-6 lg:pb-8 lg:pt-6 2xl:px-8">
          {info.me.needs_two_factor && (
            <div className="mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
              <ShieldCheck className="h-5 w-5 flex-shrink-0" />
              <span className="min-w-0 flex-1">Sua empresa exige a <b>verificação em duas etapas</b> para o seu perfil. Ative para continuar usando o Workspace.</span>
              {!location.pathname.endsWith('/seguranca') && <Link to={`/w/${slug}/seguranca`} className="rounded-xl bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-700">Ativar agora</Link>}
            </div>
          )}
          {info.me.needs_two_factor && !location.pathname.endsWith('/seguranca') ? null : <Outlet context={{ slug, info }} />}
        </main>
      </div>

      <PresenceRail slug={slug} onOpenChat={openChat} onOpenPerson={(p) => { setDirect({ ...p }); setChatOpen(true) }} />

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
        <button type="button" onClick={openChat} className="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] font-medium text-gray-500">
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
            <NavGroups groups={groups} onNavigate={() => setDrawer(false)} />
          </div>
        </div>
      )}

      {searchOpen && <CommandPalette slug={slug} pages={groups.flatMap((g) => g.items.map((i) => ({ to: i.to, label: i.label })))} onClose={() => setSearchOpen(false)} />}

      <ChatSidebar open={chatOpen} onClose={() => setChatOpen(false)} requestedDirect={direct} />

      <VitoriaChat workspace={slug} className="xl:right-[4.5rem]" />
    </div>
  )
}
