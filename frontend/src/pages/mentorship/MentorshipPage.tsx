import { useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { Handshake, Clock, Video, MapPin, Users, Pencil, Send, CheckCircle, XCircle } from 'lucide-react'
import { useMentorship, useBookMentorship, useMentorshipBookings, useUpdateBooking } from '@/hooks/useMentorships'
import { Button } from '@/components/ui/Button'
import { Avatar } from '@/components/ui/Avatar'
import { EmptyState } from '@/components/ui/EmptyState'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import { ReviewList } from '@/components/review/ReviewList'
import { formatRelative } from '@/utils/format'
import type { BookingStatus } from '@/types/mentorship.types'

const BOOKING_LABELS: Record<BookingStatus, string> = {
  requested: 'Solicitação enviada',
  accepted: 'Aceita',
  declined: 'Recusada',
  completed: 'Concluída',
}

/**
 * Detalhe da mentoria (§11): info + solicitar (aluno) e painel de solicitações
 * com aceitar/recusar/concluir (mentor dono).
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
export default function MentorshipPage() {
  const { uuid = '' } = useParams<{ uuid: string }>()
  const navigate = useNavigate()
  const { data: m, isLoading } = useMentorship(uuid)
  const book = useBookMentorship(uuid)
  const [message, setMessage] = useState('')

  if (isLoading) {
    return <div className="flex h-64 items-center justify-center"><div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" /></div>
  }
  if (!m) return null

  return (
    <div className="mx-auto max-w-2xl px-4 py-6">
      <div className="rounded-2xl border border-gray-100 bg-white p-5">
        <div className="flex items-center gap-2 text-primary">
          <Handshake className="h-6 w-6" />
          {m.category && <span className="rounded bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">{m.category}</span>}
          {m.status === 'draft' && <span className="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Rascunho</span>}
        </div>

        <h1 className="mt-2 text-2xl font-bold text-gray-900">{m.title}</h1>

        {m.mentor && (
          <Link to={`/creators/${m.mentor.uuid}`} className="mt-2 flex items-center gap-2">
            <Avatar src={m.mentor.avatar_url} name={m.mentor.name} size="sm" />
            <span className="text-sm font-medium text-gray-700">{m.mentor.name}</span>
            <PremiumBadge plan={m.mentor.plan} compact />
          </Link>
        )}

        {m.description && <p className="mt-3 text-sm text-gray-600 leading-relaxed">{m.description}</p>}

        <div className="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500">
          <span className="flex items-center gap-1"><Clock className="h-4 w-4" />{m.duration_minutes} min</span>
          <span className="flex items-center gap-1">
            {m.session_type === 'online' ? <Video className="h-4 w-4" /> : <MapPin className="h-4 w-4" />}
            {m.session_type === 'online' ? 'Online' : 'Presencial'}
          </span>
          <span className="flex items-center gap-1"><Users className="h-4 w-4" />{m.bookings_count} solicitações</span>
          <span className="font-semibold text-gray-900">
            {m.price_cents > 0 ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(m.price_cents / 100) : 'Gratuita'}
          </span>
        </div>

        {/* Ação */}
        <div className="mt-4">
          {m.is_owner ? (
            <Button variant="outline" icon={<Pencil className="h-4 w-4" />} onClick={() => navigate(`/mentorships/${m.uuid}/edit`)}>
              Gerenciar mentoria
            </Button>
          ) : m.my_booking_status ? (
            <span className="inline-flex items-center gap-1.5 rounded-lg bg-brand-50 px-3 py-2 text-sm font-medium text-brand-700">
              <CheckCircle className="h-4 w-4" /> {BOOKING_LABELS[m.my_booking_status]}
            </span>
          ) : (
            <div className="space-y-2">
              <textarea
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                rows={2}
                maxLength={1000}
                placeholder="Mensagem para o mentor (opcional)"
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
              />
              <Button icon={<Send className="h-4 w-4" />} loading={book.isPending} onClick={() => book.mutate(message.trim() || undefined)}>
                Solicitar mentoria
              </Button>
            </div>
          )}
        </div>
      </div>

      {/* Painel de solicitações (dono) */}
      {m.is_owner && <BookingsPanel mentorshipUuid={m.uuid} />}

      <h2 className="mt-8 mb-3 text-base font-semibold text-gray-900">Avaliações</h2>
      <ReviewList type="mentorship" uuid={m.uuid} />
    </div>
  )
}

function BookingsPanel({ mentorshipUuid }: { mentorshipUuid: string }) {
  const { data, isLoading } = useMentorshipBookings(mentorshipUuid)
  const updateBooking = useUpdateBooking(mentorshipUuid)
  const bookings = data?.data ?? []

  return (
    <div className="mt-6">
      <h2 className="mb-3 text-base font-semibold text-gray-900">Solicitações recebidas</h2>
      {isLoading ? (
        <p className="text-sm text-gray-400">Carregando…</p>
      ) : bookings.length === 0 ? (
        <EmptyState title="Nenhuma solicitação ainda" description="As solicitações de mentoria aparecerão aqui." icon={<Users className="h-7 w-7" />} />
      ) : (
        <div className="space-y-2">
          {bookings.map((b) => (
            <div key={b.uuid} className="rounded-xl border border-gray-100 bg-white p-4">
              <div className="flex items-start gap-3">
                {b.user && <Avatar src={b.user.avatar_url} name={b.user.name} size="sm" />}
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-1">
                    <span className="text-sm font-semibold text-gray-900">{b.user?.name}</span>
                    <PremiumBadge plan={b.user?.plan ?? null} compact />
                    <span className="text-xs text-gray-400">· {formatRelative(b.created_at)}</span>
                  </div>
                  {b.message && <p className="mt-0.5 text-sm text-gray-600">{b.message}</p>}
                  <p className="mt-1 text-xs font-medium text-gray-400">{BOOKING_LABELS[b.status]}</p>
                </div>
              </div>
              {b.status === 'requested' && (
                <div className="mt-3 flex gap-2">
                  <button onClick={() => updateBooking.mutate({ bookingUuid: b.uuid, status: 'accepted' })} className="flex items-center gap-1 rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100">
                    <CheckCircle className="h-3.5 w-3.5" /> Aceitar
                  </button>
                  <button onClick={() => updateBooking.mutate({ bookingUuid: b.uuid, status: 'declined' })} className="flex items-center gap-1 rounded-md bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-100">
                    <XCircle className="h-3.5 w-3.5" /> Recusar
                  </button>
                </div>
              )}
              {b.status === 'accepted' && (
                <div className="mt-3">
                  <button onClick={() => updateBooking.mutate({ bookingUuid: b.uuid, status: 'completed' })} className="flex items-center gap-1 rounded-md bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100">
                    <CheckCircle className="h-3.5 w-3.5" /> Marcar como concluída
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
