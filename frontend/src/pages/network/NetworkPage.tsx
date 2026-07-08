import { useState } from 'react'
import { Link } from 'react-router-dom'
import { UserCheck, UserX, UserPlus, Users, Clock, MapPin } from 'lucide-react'
import {
  usePendingConnections,
  useMyConnections,
  useAcceptConnection,
  useRejectConnection,
} from '@/hooks/useConnections'
import { useAuth } from '@/hooks/useAuth'
import { UserLink } from '@/components/common/UserLink'
import { ProfileAvatar } from '@/components/common/ProfileAvatar'
import type { ConnectionRecord } from '@/services/connection.service'

// ──────────────────────────────────────────────────────────
// Pending invite card
// ──────────────────────────────────────────────────────────

function PendingCard({ connection }: { connection: ConnectionRecord }) {
  const { user: me } = useAuth()
  const accept = useAcceptConnection(connection.requester.uuid)
  const reject = useRejectConnection(connection.requester.uuid)
  const loading = accept.isPending || reject.isPending

  const other = connection.requester.uuid === me?.uuid
    ? connection.addressee
    : connection.requester

  return (
    <div className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4">
      <ProfileAvatar uuid={other.uuid} name={other.name} src={other.avatar_url} size="md" />
      <div className="flex-1 min-w-0">
        <UserLink uuid={other.uuid} name={other.name} className="font-semibold text-gray-900 hover:underline text-sm" />
        {other.city && (
          <p className="flex items-center gap-1 text-xs text-gray-500 mt-0.5">
            <MapPin className="h-3 w-3" />
            {other.city}
          </p>
        )}
      </div>
      <div className="flex items-center gap-2 flex-shrink-0">
        <button
          onClick={() => accept.mutate()}
          disabled={loading}
          className="flex items-center gap-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary/90 disabled:opacity-50 transition-colors"
        >
          <UserCheck className="h-3.5 w-3.5" />
          Aceitar
        </button>
        <button
          onClick={() => reject.mutate()}
          disabled={loading}
          className="flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors"
        >
          <UserX className="h-3.5 w-3.5" />
          Recusar
        </button>
      </div>
    </div>
  )
}

// ──────────────────────────────────────────────────────────
// Connection card (accepted)
// ──────────────────────────────────────────────────────────

function ConnectionCard({ connection }: { connection: ConnectionRecord }) {
  const { user: me } = useAuth()
  const other = connection.requester.uuid === me?.uuid
    ? connection.addressee
    : connection.requester

  return (
    <Link
      to={`/users/${other.uuid}`}
      className="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-4 hover:shadow-md transition-shadow"
    >
      <ProfileAvatar uuid={other.uuid} name={other.name} src={other.avatar_url} size="md" />
      <div className="flex-1 min-w-0">
        <p className="font-semibold text-gray-900 text-sm">{other.name}</p>
        {other.city && (
          <p className="flex items-center gap-1 text-xs text-gray-500 mt-0.5">
            <MapPin className="h-3 w-3" />
            {other.city}
          </p>
        )}
      </div>
    </Link>
  )
}

// ──────────────────────────────────────────────────────────
// NetworkPage
// ──────────────────────────────────────────────────────────

type Tab = 'invites' | 'connections'

export default function NetworkPage() {
  const [tab, setTab] = useState<Tab>('invites')

  const { data: pending = [] } = usePendingConnections()
  const { data: connections = [] } = useMyConnections()

  return (
    <div className="mx-auto max-w-2xl px-4">
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Minha Rede</h1>

      {/* Tabs */}
      <div className="flex border-b border-gray-200 mb-6">
        <button
          onClick={() => setTab('invites')}
          className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
            tab === 'invites'
              ? 'border-primary text-primary'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <Clock className="h-4 w-4" />
          Convites
          {pending.length > 0 && (
            <span className="ml-1 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-white">
              {pending.length}
            </span>
          )}
        </button>
        <button
          onClick={() => setTab('connections')}
          className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
            tab === 'connections'
              ? 'border-primary text-primary'
              : 'border-transparent text-gray-500 hover:text-gray-700'
          }`}
        >
          <Users className="h-4 w-4" />
          Conexões
          {connections.length > 0 && (
            <span className="ml-1 text-xs text-gray-400">({connections.length})</span>
          )}
        </button>
      </div>

      {/* Invites tab */}
      {tab === 'invites' && (
        <div>
          {pending.length === 0 ? (
            <div className="rounded-xl border border-gray-100 bg-white p-10 text-center">
              <UserPlus className="mx-auto mb-3 h-8 w-8 text-gray-300" />
              <p className="text-gray-500">Nenhum convite pendente.</p>
            </div>
          ) : (
            <div className="space-y-3">
              {pending.map((c) => (
                <PendingCard key={c.uuid} connection={c} />
              ))}
            </div>
          )}
        </div>
      )}

      {/* Connections tab */}
      {tab === 'connections' && (
        <div>
          {connections.length === 0 ? (
            <div className="rounded-xl border border-gray-100 bg-white p-10 text-center">
              <Users className="mx-auto mb-3 h-8 w-8 text-gray-300" />
              <p className="text-gray-500">Você ainda não tem conexões.</p>
              <Link to="/search" className="mt-3 inline-block text-sm text-primary hover:underline">
                Encontrar pessoas
              </Link>
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {connections.map((c) => (
                <ConnectionCard key={c.uuid} connection={c} />
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
