import { useState, useRef, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Bell, UserPlus, UserCheck, UserX, Briefcase, Heart,
  MessageCircle, Reply, AtSign, Building2, CheckCheck, Trash2,
} from 'lucide-react'
import { formatDistanceToNow } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import {
  useNotificationsBell,
  useUnreadCount,
  useMarkNotificationRead,
  useMarkAllRead,
  useDeleteNotification,
} from '@/hooks/useNotifications'
import type { AppNotification, NotificationType } from '@/types/notification.types'

// ── Icon map ──────────────────────────────────────────────────

function NotificationIcon({ type }: { type: NotificationType }) {
  const cls = 'h-4 w-4 flex-shrink-0'
  switch (type) {
    case 'connection_invite':         return <UserPlus       className={`${cls} text-blue-500`} />
    case 'connection_accepted':       return <UserCheck      className={`${cls} text-green-500`} />
    case 'connection_rejected':
    case 'connection_cancelled':      return <UserX         className={`${cls} text-gray-400`} />
    case 'new_message':
    case 'unread_messages':           return <MessageCircle  className={`${cls} text-indigo-500`} />
    case 'post_liked':
    case 'comment_liked':             return <Heart          className={`${cls} text-red-500`} />
    case 'post_commented':            return <MessageCircle  className={`${cls} text-purple-500`} />
    case 'comment_replied':           return <Reply          className={`${cls} text-purple-400`} />
    case 'mention_in_post':
    case 'mention_in_comment':        return <AtSign         className={`${cls} text-pink-500`} />
    case 'job_application_received':  return <Briefcase      className={`${cls} text-amber-500`} />
    case 'job_application_accepted':  return <Briefcase      className={`${cls} text-green-500`} />
    case 'job_application_rejected':  return <Briefcase      className={`${cls} text-red-400`} />
    case 'job_application_updated':   return <Briefcase      className={`${cls} text-blue-400`} />
    case 'new_job_from_hotel':
    case 'new_job_from_connection':   return <Briefcase      className={`${cls} text-primary`} />
    case 'hotel_link_requested':
    case 'hotel_link_approved':
    case 'hotel_link_rejected':
    case 'hotel_admin_added':         return <Building2      className={`${cls} text-amber-600`} />
    default:                          return <Bell           className={`${cls} text-gray-400`} />
  }
}

// ── Filters ───────────────────────────────────────────────────

type Filter = 'all' | 'unread'

// ── Component ─────────────────────────────────────────────────

export default function NotificationBell() {
  const [open, setOpen]     = useState(false)
  const [filter, setFilter] = useState<Filter>('all')
  const panelRef            = useRef<HTMLDivElement>(null)
  const navigate            = useNavigate()

  const { data: allNotifications = [] } = useNotificationsBell()
  const { data: unreadData }            = useUnreadCount()
  const unread                          = unreadData?.count ?? 0

  const markRead   = useMarkNotificationRead()
  const markAll    = useMarkAllRead()
  const deleteNotif = useDeleteNotification()

  const notifications = filter === 'unread'
    ? allNotifications.filter((n) => !n.read)
    : allNotifications

  // Close on outside click
  useEffect(() => {
    function handle(e: MouseEvent) {
      if (panelRef.current && !panelRef.current.contains(e.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handle)
    return () => document.removeEventListener('mousedown', handle)
  }, [])

  function handleClick(n: AppNotification) {
    if (!n.read) markRead.mutate(n.uuid)
    if (n.link) navigate(n.link)
    setOpen(false)
  }

  function handleDelete(e: React.MouseEvent, uuid: string) {
    e.stopPropagation()
    deleteNotif.mutate(uuid)
  }

  return (
    <div className="relative" ref={panelRef}>
      {/* Bell button */}
      <button
        onClick={() => setOpen((v) => !v)}
        className="relative flex flex-col items-center gap-0.5 px-3 py-2 rounded-lg text-gray-500 hover:text-primary hover:bg-primary/5 transition-colors"
        aria-label="Notificações"
      >
        <Bell className="h-5 w-5" />
        <span className="text-xs font-medium hidden md:block">Notificações</span>
        {unread > 0 && (
          <span className="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
            {unread > 9 ? '9+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-full mt-2 w-96 rounded-xl border border-gray-100 bg-white shadow-xl z-50 flex flex-col max-h-[80vh]">

          {/* Header */}
          <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3 flex-shrink-0">
            <h3 className="font-semibold text-gray-900">Notificações</h3>
            {unread > 0 && (
              <button
                onClick={() => markAll.mutate()}
                className="flex items-center gap-1 text-xs text-primary hover:underline"
              >
                <CheckCheck className="h-3.5 w-3.5" />
                Marcar todas como lidas
              </button>
            )}
          </div>

          {/* Filters */}
          <div className="flex border-b border-gray-100 px-4 gap-3 flex-shrink-0">
            {(['all', 'unread'] as Filter[]).map((f) => (
              <button
                key={f}
                onClick={() => setFilter(f)}
                className={`py-2 text-sm font-medium border-b-2 transition-colors ${
                  filter === f
                    ? 'border-primary text-primary'
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                }`}
              >
                {f === 'all' ? 'Todas' : 'Não lidas'}
                {f === 'unread' && unread > 0 && (
                  <span className="ml-1.5 rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-600">
                    {unread}
                  </span>
                )}
              </button>
            ))}
          </div>

          {/* List */}
          <div className="overflow-y-auto divide-y divide-gray-50 flex-1">
            {notifications.length === 0 ? (
              <p className="py-10 text-center text-sm text-gray-400">
                {filter === 'unread' ? 'Nenhuma notificação não lida.' : 'Nenhuma notificação ainda.'}
              </p>
            ) : (
              notifications.map((n) => (
                <div
                  key={n.uuid}
                  className={`group relative flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors ${
                    !n.read ? 'bg-blue-50/40' : ''
                  }`}
                  onClick={() => handleClick(n)}
                >
                  {/* Avatar or icon */}
                  <div className="relative flex-shrink-0 mt-0.5">
                    {n.actor?.avatar_url ? (
                      <img
                        src={n.actor.avatar_url}
                        alt={n.actor.name}
                        className="h-9 w-9 rounded-full object-cover"
                      />
                    ) : (
                      <div className="h-9 w-9 rounded-full bg-gray-100 flex items-center justify-center">
                        <Bell className="h-4 w-4 text-gray-400" />
                      </div>
                    )}
                    {/* Type icon badge */}
                    <span className="absolute -bottom-0.5 -right-0.5 rounded-full bg-white p-0.5 shadow-sm">
                      <NotificationIcon type={n.type} />
                    </span>
                  </div>

                  {/* Content */}
                  <div className="flex-1 min-w-0">
                    <p className={`text-sm leading-snug ${!n.read ? 'font-semibold text-gray-900' : 'text-gray-700'}`}>
                      {n.title}
                    </p>
                    {n.description && (
                      <p className="mt-0.5 text-xs text-gray-500 truncate">{n.description}</p>
                    )}
                    <p className="mt-1 text-[10px] text-gray-400">
                      {formatDistanceToNow(new Date(n.created_at), { addSuffix: true, locale: ptBR })}
                    </p>
                  </div>

                  {/* Unread dot + delete */}
                  <div className="flex flex-col items-center gap-2 mt-1 flex-shrink-0">
                    {!n.read && (
                      <span className="h-2 w-2 rounded-full bg-blue-500" />
                    )}
                    <button
                      onClick={(e) => handleDelete(e, n.uuid)}
                      className="opacity-0 group-hover:opacity-100 transition-opacity text-gray-300 hover:text-red-400"
                      aria-label="Excluir notificação"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              ))
            )}
          </div>

          {/* Footer */}
          <div className="border-t border-gray-100 p-2 flex-shrink-0">
            <button
              onClick={() => { navigate('/notifications'); setOpen(false) }}
              className="w-full py-2 text-sm font-medium text-primary hover:bg-primary/5 rounded-lg transition-colors"
            >
              Ver todas as notificações
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
