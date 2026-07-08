import { useState, useEffect, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Bell, UserPlus, UserCheck, UserX, Briefcase, Heart,
  MessageCircle, Reply, AtSign, Building2, CheckCheck, Trash2,
} from 'lucide-react'
import { formatDistanceToNow } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import {
  useInfiniteNotifications,
  useUnreadCount,
  useMarkNotificationRead,
  useMarkAllRead,
  useDeleteNotification,
} from '@/hooks/useNotifications'
import { ProfileAvatar } from '@/components/common/ProfileAvatar'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import type { AppNotification, NotificationCategory, NotificationType } from '@/types/notification.types'

// ── Tab config ────────────────────────────────────────────────

interface Tab {
  key:   NotificationCategory | 'all' | 'unread'
  label: string
  category?: NotificationCategory
  unreadOnly?: boolean
}

const TABS: Tab[] = [
  { key: 'all',        label: 'Todas' },
  { key: 'unread',     label: 'Não lidas', unreadOnly: true },
  { key: 'connection', label: 'Conexões',  category: 'connection' },
  { key: 'message',    label: 'Mensagens', category: 'message' },
  { key: 'feed',       label: 'Feed',      category: 'feed' },
  { key: 'job',        label: 'Vagas',     category: 'job' },
  { key: 'system',     label: 'Sistema',   category: 'system' },
]

// ── Icon map ──────────────────────────────────────────────────

function NotifIcon({ type }: { type: NotificationType }) {
  const cls = 'h-5 w-5 flex-shrink-0'
  switch (type) {
    case 'connection_invite':         return <UserPlus      className={`${cls} text-blue-500`} />
    case 'connection_accepted':       return <UserCheck     className={`${cls} text-green-500`} />
    case 'connection_rejected':
    case 'connection_cancelled':      return <UserX        className={`${cls} text-gray-400`} />
    case 'new_message':
    case 'unread_messages':           return <MessageCircle className={`${cls} text-indigo-500`} />
    case 'post_liked':
    case 'comment_liked':             return <Heart         className={`${cls} text-red-500`} />
    case 'post_commented':            return <MessageCircle className={`${cls} text-purple-500`} />
    case 'comment_replied':           return <Reply         className={`${cls} text-purple-400`} />
    case 'mention_in_post':
    case 'mention_in_comment':        return <AtSign        className={`${cls} text-pink-500`} />
    case 'job_application_received':  return <Briefcase     className={`${cls} text-amber-500`} />
    case 'job_application_accepted':  return <Briefcase     className={`${cls} text-green-500`} />
    case 'job_application_rejected':  return <Briefcase     className={`${cls} text-red-400`} />
    case 'job_application_updated':   return <Briefcase     className={`${cls} text-blue-400`} />
    case 'new_job_from_hotel':
    case 'new_job_from_connection':   return <Briefcase     className={`${cls} text-primary`} />
    case 'hotel_link_requested':
    case 'hotel_link_approved':
    case 'hotel_link_rejected':
    case 'hotel_admin_added':         return <Building2     className={`${cls} text-amber-600`} />
    default:                          return <Bell          className={`${cls} text-gray-400`} />
  }
}

// ── Item ──────────────────────────────────────────────────────

function NotificationItem({
  n,
  onRead,
  onDelete,
}: {
  n: AppNotification
  onRead:   (uuid: string) => void
  onDelete: (uuid: string) => void
}) {
  const navigate = useNavigate()

  function handleClick() {
    if (!n.read) onRead(n.uuid)
    if (n.link) navigate(n.link)
  }

  return (
    <div
      onClick={handleClick}
      className={`group flex items-start gap-4 px-6 py-4 cursor-pointer hover:bg-gray-50 transition-colors border-b border-gray-50 ${
        !n.read ? 'bg-blue-50/30' : 'bg-white'
      }`}
    >
      {/* Avatar + badge */}
      <div className="relative flex-shrink-0">
        {n.actor ? (
          <ProfileAvatar uuid={n.actor.uuid} name={n.actor.name} src={n.actor.avatar_url} size="md" />
        ) : (
          <div className="h-11 w-11 rounded-full bg-gray-100 flex items-center justify-center">
            <Bell className="h-5 w-5 text-gray-300" />
          </div>
        )}
        <span className="absolute -bottom-0.5 -right-0.5 rounded-full bg-white p-0.5 shadow">
          <NotifIcon type={n.type} />
        </span>
      </div>

      {/* Body */}
      <div className="flex-1 min-w-0">
        <p className={`text-sm leading-snug ${!n.read ? 'font-semibold text-gray-900' : 'text-gray-700'}`}>
          {n.title}
        </p>
        {n.description && (
          <p className="mt-0.5 text-sm text-gray-500 line-clamp-2">{n.description}</p>
        )}
        <p className="mt-1 flex items-center gap-1 text-xs text-gray-400">
          {formatDistanceToNow(new Date(n.created_at), { addSuffix: true, locale: ptBR })}
          {n.actor?.plan && <PremiumBadge plan={n.actor.plan} compact />}
        </p>
      </div>

      {/* Actions */}
      <div className="flex flex-col items-center gap-2 flex-shrink-0">
        {!n.read && <span className="h-2.5 w-2.5 rounded-full bg-blue-500" />}
        <button
          onClick={(e) => { e.stopPropagation(); onDelete(n.uuid) }}
          className="opacity-0 group-hover:opacity-100 transition-opacity text-gray-300 hover:text-red-400 mt-1"
          aria-label="Excluir notificação"
        >
          <Trash2 className="h-4 w-4" />
        </button>
      </div>
    </div>
  )
}

// ── Skeleton ──────────────────────────────────────────────────

function NotificationSkeleton() {
  return (
    <div className="animate-pulse flex items-start gap-4 px-6 py-4 border-b border-gray-50">
      <div className="h-11 w-11 rounded-full bg-gray-200 flex-shrink-0" />
      <div className="flex-1 space-y-2">
        <div className="h-3.5 w-3/4 rounded bg-gray-200" />
        <div className="h-3 w-1/2 rounded bg-gray-100" />
        <div className="h-2.5 w-20 rounded bg-gray-100" />
      </div>
    </div>
  )
}

// ── Page ──────────────────────────────────────────────────────

export default function NotificationsPage() {
  const [activeTab, setActiveTab] = useState<Tab>(TABS[0])
  const loaderRef = useRef<HTMLDivElement>(null)

  const { data: unreadData } = useUnreadCount()
  const unread = unreadData?.count ?? 0

  const queryParams = {
    ...(activeTab.category  ? { category: activeTab.category } : {}),
    ...(activeTab.unreadOnly ? { unread: true }                : {}),
  }

  const {
    data,
    isLoading,
    isError,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteNotifications(queryParams)

  const markRead   = useMarkNotificationRead()
  const markAll    = useMarkAllRead(activeTab.category)
  const deleteNotif = useDeleteNotification()

  const pages         = data?.pages ?? []
  const notifications = pages.flatMap((p) => p.data)

  // Infinite scroll via IntersectionObserver
  useEffect(() => {
    if (!loaderRef.current) return
    const observer = new IntersectionObserver(
      ([entry]) => { if (entry.isIntersecting && hasNextPage) fetchNextPage() },
      { threshold: 0.1 },
    )
    observer.observe(loaderRef.current)
    return () => observer.disconnect()
  }, [fetchNextPage, hasNextPage])

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      {/* Header */}
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Notificações</h1>
          {unread > 0 && (
            <p className="mt-1 text-sm text-gray-500">
              {unread} não lida{unread !== 1 ? 's' : ''}
            </p>
          )}
        </div>
        {unread > 0 && (
          <button
            onClick={() => markAll.mutate()}
            disabled={markAll.isPending}
            className="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors disabled:opacity-50"
          >
            <CheckCheck className="h-4 w-4" />
            Marcar todas como lidas
          </button>
        )}
      </div>

      {/* Tabs */}
      <div className="mb-4 flex gap-1 overflow-x-auto rounded-xl border border-gray-100 bg-white p-1 shadow-sm">
        {TABS.map((tab) => {
          const tabUnread = tab.category
            ? (unreadData?.by_category?.[tab.category] ?? 0)
            : tab.unreadOnly
              ? unread
              : unread

          return (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab)}
              className={`relative flex-shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                activeTab.key === tab.key
                  ? 'bg-primary text-white shadow-sm'
                  : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700'
              }`}
            >
              {tab.label}
              {tabUnread > 0 && (
                <span
                  className={`ml-1.5 rounded-full px-1.5 py-0.5 text-[10px] font-bold ${
                    activeTab.key === tab.key
                      ? 'bg-white/30 text-white'
                      : 'bg-red-100 text-red-600'
                  }`}
                >
                  {tabUnread > 99 ? '99+' : tabUnread}
                </span>
              )}
            </button>
          )
        })}
      </div>

      {/* List */}
      <div className="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
        {isLoading ? (
          Array.from({ length: 6 }).map((_, i) => <NotificationSkeleton key={i} />)
        ) : isError ? (
          <div className="py-10 text-center text-sm text-red-500">
            Erro ao carregar notificações. Tente recarregar.
          </div>
        ) : notifications.length === 0 ? (
          <div className="py-16 text-center">
            <Bell className="mx-auto mb-3 h-10 w-10 text-gray-200" />
            <p className="text-gray-400 text-sm">Nenhuma notificação aqui.</p>
          </div>
        ) : (
          <>
            {notifications.map((n) => (
              <NotificationItem
                key={n.uuid}
                n={n}
                onRead={(uuid) => markRead.mutate(uuid)}
                onDelete={(uuid) => deleteNotif.mutate(uuid)}
              />
            ))}

            {/* Infinite scroll trigger */}
            <div ref={loaderRef} className="py-2">
              {isFetchingNextPage && (
                <div className="flex justify-center py-3">
                  <div className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                </div>
              )}
            </div>
          </>
        )}
      </div>
    </div>
  )
}
