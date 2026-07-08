import { useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import {
  MapPin, Globe, Pencil, Building2, MessageSquare,
  UserPlus, UserCheck, UserX, Clock, Users, FileText, BadgeCheck,
} from 'lucide-react'
import { useMyProfile, useUserProfile, useUserPosts } from '@/hooks/useProfile'
import { useAuth } from '@/hooks/useAuth'
import { useMyHotels } from '@/hooks/useHotel'
import {
  useMyConnections, useSendConnection, useRemoveConnection,
  useAcceptConnection, useRejectConnection,
} from '@/hooks/useConnections'
import AvatarUpload from '@/components/common/AvatarUpload'
import PostCard from '@/components/feed/PostCard'
import ExperienceTimeline from '@/components/profile/ExperienceTimeline'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import {
  useUploadAvatar, useUploadBanner,
  useAddExperience, useUpdateExperience, useDeleteExperience,
} from '@/hooks/useProfile'
import type { Hotel } from '@/types/hotel.types'
import type { ConnectionStatus } from '@/types/api.types'
import type { Post } from '@/types/feed.types'
import type { Experience } from '@/types/user.types'

// ──────────────────────────────────────────────────────────
// ConnectionButton
// ──────────────────────────────────────────────────────────

function ConnectionButton({ userUuid, status }: { userUuid: string; status: ConnectionStatus }) {
  const send   = useSendConnection(userUuid)
  const remove = useRemoveConnection(userUuid)
  const accept = useAcceptConnection(userUuid)
  const reject = useRejectConnection(userUuid)
  const loading = send.isPending || remove.isPending || accept.isPending || reject.isPending

  if (status === 'accepted') return (
    <button onClick={() => remove.mutate()} disabled={loading}
      className="flex items-center gap-1.5 rounded-full border border-primary px-4 py-1.5 text-sm font-medium text-primary hover:bg-red-50 hover:border-red-300 hover:text-red-600 disabled:opacity-50 transition-colors">
      <UserCheck className="h-4 w-4" /> Conectado
    </button>
  )
  if (status === 'pending_sent') return (
    <button onClick={() => remove.mutate()} disabled={loading}
      className="flex items-center gap-1.5 rounded-full border border-yellow-300 bg-yellow-50 px-4 py-1.5 text-sm font-medium text-yellow-700 disabled:opacity-50 transition-colors">
      <Clock className="h-4 w-4" /> Pendente
    </button>
  )
  if (status === 'pending_received') return (
    <div className="flex items-center gap-2">
      <button onClick={() => accept.mutate()} disabled={loading}
        className="flex items-center gap-1.5 rounded-full bg-primary px-4 py-1.5 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50 transition-colors">
        <UserCheck className="h-4 w-4" /> Aceitar
      </button>
      <button onClick={() => reject.mutate()} disabled={loading}
        className="flex items-center gap-1.5 rounded-full border border-gray-300 px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors">
        <UserX className="h-4 w-4" /> Recusar
      </button>
    </div>
  )
  return (
    <button onClick={() => send.mutate()} disabled={loading}
      className="flex items-center gap-1.5 rounded-full bg-primary px-4 py-1.5 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50 transition-colors">
      <UserPlus className="h-4 w-4" /> Conectar
    </button>
  )
}

// ──────────────────────────────────────────────────────────
// UserPostsFeed
// ──────────────────────────────────────────────────────────

function UserPostsFeed({ uuid }: { uuid: string }) {
  const [page, setPage] = useState(1)
  const { data, isLoading } = useUserPosts(uuid, page)
  const posts: Post[] = (data?.data ?? []) as Post[]
  const meta = data?.meta

  if (isLoading) return (
    <div className="flex justify-center py-8">
      <div className="h-6 w-6 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>
  )

  if (posts.length === 0) return (
    <div className="flex flex-col items-center py-10 text-gray-400">
      <FileText className="h-8 w-8 mb-2" />
      <p className="text-sm">Nenhuma publicação ainda.</p>
    </div>
  )

  return (
    <div className="space-y-4">
      {posts.map((post) => <PostCard key={post.uuid} post={post} />)}
      {meta && meta.last_page > 1 && (
        <div className="flex justify-center gap-3 pt-2">
          {page > 1 && (
            <button onClick={() => setPage((p) => p - 1)}
              className="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">← Anterior</button>
          )}
          <span className="self-center text-sm text-gray-400">{meta.current_page} / {meta.last_page}</span>
          {page < meta.last_page && (
            <button onClick={() => setPage((p) => p + 1)}
              className="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Próxima →</button>
          )}
        </div>
      )}
    </div>
  )
}

// ──────────────────────────────────────────────────────────
// HotelsTab
// ──────────────────────────────────────────────────────────

function HotelsTab({ hotels }: { hotels: Hotel[] }) {
  const current = hotels // All are current for now — future: separate by date
  if (hotels.length === 0) return (
    <div className="flex flex-col items-center py-10 text-gray-400">
      <Building2 className="h-8 w-8 mb-2" />
      <p className="text-sm">Nenhum hotel vinculado.</p>
    </div>
  )
  return (
    <div className="space-y-3">
      <p className="text-xs font-medium uppercase tracking-wide text-gray-400 mb-2">Atuais</p>
      {current.map((hotel) => (
        <Link key={hotel.uuid} to={`/hotels/${hotel.slug}`}
          className="flex items-center gap-3 rounded-xl border border-gray-100 p-4 hover:shadow-md transition-shadow bg-white">
          <div className="h-12 w-12 flex-shrink-0 overflow-hidden rounded-lg bg-primary/10">
            {hotel.logo_url
              ? <img src={hotel.logo_url} alt={hotel.name} className="h-full w-full object-cover" />
              : <div className="flex h-full w-full items-center justify-center text-base font-bold text-primary">{hotel.name.charAt(0).toUpperCase()}</div>
            }
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <p className="font-medium text-gray-900 truncate">{hotel.name}</p>
              <span title="Vínculo verificado">
                <BadgeCheck className="h-4 w-4 flex-shrink-0 text-primary" />
              </span>
            </div>
            {(hotel.city || hotel.state) && (
              <p className="flex items-center gap-1 text-xs text-gray-500 mt-0.5">
                <MapPin className="h-3 w-3" />
                {[hotel.city, hotel.state].filter(Boolean).join(', ')}
              </p>
            )}
          </div>
        </Link>
      ))}
    </div>
  )
}

// ──────────────────────────────────────────────────────────
// ProfilePage
// ──────────────────────────────────────────────────────────

type ProfileTab = 'posts' | 'experience' | 'hotels'

export default function ProfilePage() {
  const { uuid } = useParams<{ uuid?: string }>()
  const { user: me } = useAuth()
  const navigate = useNavigate()
  const [tab, setTab] = useState<ProfileTab>('posts')

  const isOwnProfile = !uuid || uuid === me?.uuid

  const myQuery   = useMyProfile()
  const userQuery = useUserProfile(uuid ?? '')
  const { data: profile, isLoading, isError } = isOwnProfile ? myQuery : userQuery

  const { data: myHotels }      = useMyHotels()
  const { data: myConnections } = useMyConnections()

  const uploadAvatar = useUploadAvatar()
  const uploadBanner = useUploadBanner()
  const addExp       = useAddExperience()
  const updateExp    = useUpdateExperience()
  const deleteExp    = useDeleteExperience()

  if (isLoading) return (
    <div className="flex h-64 items-center justify-center">
      <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>
  )
  if (isError || !profile) return (
    <div className="flex h-64 items-center justify-center text-gray-500">Perfil não encontrado.</div>
  )

  const experiences = ((profile as { experiences?: Experience[] }).experiences ?? []) as Experience[]
  const connectionStatus: ConnectionStatus = isOwnProfile ? null
    : ((profile as { connection_status?: ConnectionStatus }).connection_status ?? null)
  const hotels = myHotels?.data ?? []
  const connectionsCount = isOwnProfile ? (Array.isArray(myConnections) ? myConnections.length : 0) : 0
  const profileUuid = isOwnProfile ? (me?.uuid ?? '') : (uuid ?? '')

  const currentTitle = experiences.length > 0 ? `${experiences[0].title} · ${experiences[0].company}` : null
  const currentHotel = experiences.length > 0 ? experiences[0].hotel : null

  const TABS: { id: ProfileTab; label: string }[] = [
    { id: 'posts', label: 'Publicações' },
    { id: 'experience', label: 'Experiência' },
    { id: 'hotels', label: 'Hotéis' },
  ]

  return (
    <div className="mx-auto max-w-3xl px-4 pb-12">
      {/* ── Banner ── */}
      <div
        className={`relative h-44 rounded-t-2xl bg-gradient-to-r from-primary/30 to-primary/60 overflow-hidden ${isOwnProfile ? 'cursor-pointer group' : ''}`}
        style={profile.banner_url ? { backgroundImage: `url(${profile.banner_url})`, backgroundSize: 'cover', backgroundPosition: 'center' } : undefined}
        onClick={isOwnProfile ? () => {
          const input = document.createElement('input')
          input.type = 'file'; input.accept = 'image/jpeg,image/png,image/webp'
          input.onchange = (e) => { const f = (e.target as HTMLInputElement).files?.[0]; if (f) uploadBanner.mutate(f) }
          input.click()
        } : undefined}
      >
        {isOwnProfile && (
          <div className="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition-opacity group-hover:opacity-100">
            <span className="rounded-lg bg-white/90 px-3 py-1.5 text-xs font-medium text-gray-800">
              {uploadBanner.isPending ? 'Enviando...' : 'Alterar banner'}
            </span>
          </div>
        )}
      </div>

      {/* ── Header card ── */}
      <div className="relative rounded-b-2xl border border-t-0 border-gray-100 bg-white px-6 pb-6 shadow-sm">
        <div className="-mt-14 flex items-end justify-between flex-wrap gap-3">
          {/* Avatar */}
          {isOwnProfile ? (
            <AvatarUpload currentUrl={profile.avatar_url} name={profile.name}
              onFileSelect={(f) => uploadAvatar.mutate(f)} loading={uploadAvatar.isPending} size="lg" />
          ) : (
            <div className="h-28 w-28 flex-shrink-0 overflow-hidden rounded-full border-4 border-white shadow-md bg-primary/10">
              {profile.avatar_url
                ? <img src={profile.avatar_url} alt={profile.name} className="h-full w-full object-cover" />
                : <div className="flex h-full w-full items-center justify-center text-4xl font-semibold text-primary">{profile.name.charAt(0).toUpperCase()}</div>
              }
            </div>
          )}

          {/* Actions */}
          <div className="flex items-center gap-2 pt-2 flex-wrap">
            {isOwnProfile ? (
              <>
                <Link to="/profile/edit"
                  className="flex items-center gap-1.5 rounded-full bg-primary px-4 py-1.5 text-sm font-medium text-white hover:bg-primary/90 transition-colors">
                  <Pencil className="h-3.5 w-3.5" /> Editar perfil
                </Link>
              </>
            ) : (
              <>
                <ConnectionButton userUuid={profile.uuid} status={connectionStatus} />
                <button onClick={() => navigate('/chat', { state: { openUserUuid: profile.uuid } })}
                  className="flex items-center gap-1.5 rounded-full border border-gray-300 px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                  <MessageSquare className="h-4 w-4" /> Mensagem
                </button>
              </>
            )}
          </div>
        </div>

        {/* Name + info */}
        <div className="mt-4">
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold text-gray-900">{profile.name}</h1>
            <PremiumBadge plan={profile.plan} />
          </div>
          {currentTitle && <p className="mt-0.5 text-sm text-gray-600">{currentTitle}</p>}
          {currentHotel && (
            <Link
              to={`/hotels/${currentHotel.slug}`}
              className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
            >
              <Building2 className="h-3.5 w-3.5" />
              {currentHotel.name}
            </Link>
          )}
          {profile.bio && <p className="mt-2 text-sm text-gray-600 leading-relaxed">{profile.bio}</p>}

          <div className="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500">
            {(profile.city || profile.state) && (
              <span className="flex items-center gap-1">
                <MapPin className="h-4 w-4" />
                {[profile.city, profile.state].filter(Boolean).join(', ')}
              </span>
            )}
            {profile.website && (
              <a href={profile.website} target="_blank" rel="noopener noreferrer"
                className="flex items-center gap-1 text-primary hover:underline">
                <Globe className="h-4 w-4" />
                {profile.website.replace(/^https?:\/\//, '')}
              </a>
            )}
          </div>

          {/* Stats */}
          <div className="mt-4 flex flex-wrap items-center gap-5 border-t border-gray-100 pt-4">
            <Link to={isOwnProfile ? '/network' : '#'}
              className="flex items-center gap-1.5 text-sm hover:text-primary transition-colors">
              <Users className="h-4 w-4 text-gray-400" />
              <span className="font-semibold text-gray-900">{connectionsCount}</span>
              <span className="text-gray-500">conexões</span>
            </Link>
            <span className="flex items-center gap-1.5 text-sm text-gray-500">
              <FileText className="h-4 w-4 text-gray-400" />
              <span className="font-semibold text-gray-900">{experiences.length}</span>
              experiência{experiences.length !== 1 ? 's' : ''}
            </span>
          </div>
        </div>
      </div>

      {/* ── Tabs ── */}
      <div className="mt-4 rounded-xl border border-gray-100 bg-white shadow-sm">
        <div className="flex border-b border-gray-100 px-2">
          {TABS.map((t) => (
            <button key={t.id} onClick={() => setTab(t.id)}
              className={`px-5 py-3 text-sm font-medium border-b-2 transition-colors ${
                tab === t.id ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-800'
              }`}>
              {t.label}
            </button>
          ))}
        </div>

        <div className="p-6">
          {tab === 'posts' && <UserPostsFeed uuid={profileUuid} />}

          {tab === 'experience' && (
            <ExperienceTimeline
              experiences={experiences}
              editable={isOwnProfile}
              onAdd={isOwnProfile ? (data) => addExp.mutate(data) : undefined}
              onUpdate={isOwnProfile ? (id, data) => updateExp.mutate({ id, data }) : undefined}
              onDelete={isOwnProfile ? (id) => deleteExp.mutate(id) : undefined}
              mutating={addExp.isPending || updateExp.isPending || deleteExp.isPending}
            />
          )}

          {tab === 'hotels' && <HotelsTab hotels={hotels} />}
        </div>
      </div>
    </div>
  )
}
