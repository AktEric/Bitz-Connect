import { useState } from 'react'
import { X, Send, Search, Check, Loader2, Users, Rss } from 'lucide-react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useMyConnections } from '@/hooks/useConnections'
import { useAuth } from '@/hooks/useAuth'
import { Avatar } from '@/components/ui/Avatar'
import chatService from '@/services/chat.service'
import feedService from '@/services/feed.service'
import { chatKeys } from '@/hooks/useChat'
import { JobShareCard } from '@/components/chat/JobShareCard'
import type { Job } from '@/types/job.types'
import type { ConnectionRecord } from '@/services/connection.service'
import type { JobMetadata } from '@/types/api.types'

interface Props {
  job: Job
  onClose: () => void
}

type Tab = 'friend' | 'feed'

/**
 * Modal to share a job opening — either via chat to a connection, or as a feed post.
 */
export default function ShareJobModal({ job, onClose }: Props) {
  const { user: me } = useAuth()
  const qc = useQueryClient()
  const { data: connections = [] } = useMyConnections()
  const [tab, setTab] = useState<Tab>('friend')
  const [search, setSearch] = useState('')
  const [sent, setSent] = useState<Set<string>>(new Set())
  const [feedShared, setFeedShared] = useState(false)
  const [feedText, setFeedText] = useState(
    `Compartilhei uma vaga: ${job.title} — ${job.hotel.name}`,
  )

  // Build JobMetadata from the job object for the preview card
  const jobMetadata: JobMetadata = {
    job_uuid:      job.uuid,
    title:         job.title,
    hotel_name:    job.hotel.name,
    hotel_logo:    job.hotel.logo_url ?? null,
    location:      job.city ? `${job.city}, ${job.state}` : null,
    contract_type: job.contract_type,
    is_active:     job.status === 'active',
  }

  // ── Chat share mutation ────────────────────────────────────────────────────
  const sendMsg = useMutation({
    mutationFn: ({ userUuid, jobUuid }: { userUuid: string; jobUuid: string }) =>
      chatService.sendJobShare(userUuid, jobUuid),
    onSuccess: (_, { userUuid }) => {
      setSent((prev) => new Set([...prev, userUuid]))
      qc.invalidateQueries({ queryKey: chatKeys.conversations })
      qc.invalidateQueries({ queryKey: chatKeys.unread })
    },
  })

  // ── Feed share mutation ────────────────────────────────────────────────────
  const shareToFeed = useMutation({
    mutationFn: () =>
      feedService.createPost(
        feedText.trim() || `Compartilhei uma vaga: ${job.title} — ${job.hotel.name}`,
        undefined,
        undefined,
        'job_share',
        jobMetadata as unknown as Record<string, unknown>,
      ),
    onSuccess: () => {
      setFeedShared(true)
      qc.invalidateQueries({ queryKey: ['feed'] })
    },
  })

  // Resolve the "other" user from each connection record
  function getOtherUser(conn: ConnectionRecord) {
    return conn.requester.uuid === me?.uuid ? conn.addressee : conn.requester
  }

  const filtered = (connections as ConnectionRecord[]).filter((conn) => {
    const other = getOtherUser(conn)
    return other.name.toLowerCase().includes(search.toLowerCase())
  })

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center bg-black/40 backdrop-blur-sm animate-fade-in">
      <div className="w-full max-w-md rounded-2xl border border-gray-100 bg-white shadow-panel animate-scale-in mx-4 mb-4 sm:mb-0 overflow-hidden">

        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
          <div>
            <h2 className="font-semibold text-gray-900 text-sm">Compartilhar vaga</h2>
            <p className="text-xs text-gray-500 mt-0.5 truncate max-w-xs">
              {job.title} · {job.hotel.name}
            </p>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
            aria-label="Fechar"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Tabs */}
        <div className="flex border-b border-gray-100">
          <button
            onClick={() => setTab('friend')}
            className={[
              'flex flex-1 items-center justify-center gap-2 py-3 text-sm font-medium transition-colors',
              tab === 'friend'
                ? 'text-violet-600 border-b-2 border-violet-600'
                : 'text-gray-500 hover:text-gray-700',
            ].join(' ')}
          >
            <Users className="h-4 w-4" />
            Para um amigo
          </button>
          <button
            onClick={() => setTab('feed')}
            className={[
              'flex flex-1 items-center justify-center gap-2 py-3 text-sm font-medium transition-colors',
              tab === 'feed'
                ? 'text-violet-600 border-b-2 border-violet-600'
                : 'text-gray-500 hover:text-gray-700',
            ].join(' ')}
          >
            <Rss className="h-4 w-4" />
            No feed
          </button>
        </div>

        {/* ── Tab: Para um amigo ─────────────────────────────────────────── */}
        {tab === 'friend' && (
          <>
            {/* Search */}
            <div className="px-4 pt-3 pb-2">
              <div className="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 focus-within:border-violet-400 focus-within:ring-2 focus-within:ring-violet-100 transition-all">
                <Search className="h-3.5 w-3.5 text-gray-400 flex-shrink-0" />
                <input
                  autoFocus
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Buscar conexão..."
                  className="flex-1 bg-transparent text-sm outline-none text-gray-800 placeholder:text-gray-400"
                />
              </div>
            </div>

            {/* Connection list */}
            <div className="max-h-64 overflow-y-auto px-2 pb-3">
              {filtered.length === 0 ? (
                <p className="py-8 text-center text-sm text-gray-400">
                  {connections.length === 0
                    ? 'Você ainda não tem conexões.'
                    : 'Nenhuma conexão encontrada.'}
                </p>
              ) : (
                <ul className="space-y-1">
                  {filtered.map((conn) => {
                    const other = getOtherUser(conn)
                    const isSent = sent.has(other.uuid)
                    const isLoading =
                      sendMsg.isPending && sendMsg.variables?.userUuid === other.uuid

                    return (
                      <li key={conn.uuid}>
                        <div className="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-gray-50 transition-colors">
                          <Avatar src={other.avatar_url ?? null} name={other.name} size="sm" />
                          <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-gray-900 truncate">
                              {other.name}
                            </p>
                            {other.city && (
                              <p className="text-xs text-gray-400 truncate">{other.city}</p>
                            )}
                          </div>
                          <button
                            onClick={() => {
                              if (isSent || isLoading) return
                              sendMsg.mutate({ userUuid: other.uuid, jobUuid: job.uuid })
                            }}
                            disabled={isSent || isLoading}
                            className={[
                              'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-all',
                              isSent
                                ? 'bg-green-50 text-green-600 cursor-default'
                                : 'bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-60',
                            ].join(' ')}
                          >
                            {isLoading ? (
                              <Loader2 className="h-3.5 w-3.5 animate-spin" />
                            ) : isSent ? (
                              <><Check className="h-3.5 w-3.5" /> Enviado</>
                            ) : (
                              <><Send className="h-3.5 w-3.5" /> Enviar</>
                            )}
                          </button>
                        </div>
                      </li>
                    )
                  })}
                </ul>
              )}
            </div>

            <div className="border-t border-gray-100 px-5 py-3 bg-gray-50">
              <p className="text-xs text-gray-400 text-center">
                A vaga será enviada como card na conversa com a conexão.
              </p>
            </div>
          </>
        )}

        {/* ── Tab: No feed ───────────────────────────────────────────────── */}
        {tab === 'feed' && (
          <div className="px-5 py-5 flex flex-col gap-4">
            {feedShared ? (
              <div className="flex flex-col items-center gap-4 py-4 text-center">
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-green-50">
                  <Check className="h-6 w-6 text-green-500" />
                </div>
                <div>
                  <p className="font-semibold text-gray-900 text-sm">Publicado no feed!</p>
                  <p className="text-xs text-gray-500 mt-1">
                    Sua rede já pode ver esta oportunidade.
                  </p>
                </div>
                <button
                  onClick={onClose}
                  className="rounded-xl bg-violet-600 px-6 py-2 text-sm font-medium text-white hover:bg-violet-700 transition-colors"
                >
                  Fechar
                </button>
              </div>
            ) : (
              <>
                {/* Editable text */}
                <div>
                  <label className="block text-xs font-medium text-gray-600 mb-1.5">
                    Escreva algo (opcional)
                  </label>
                  <textarea
                    value={feedText}
                    onChange={(e) => setFeedText(e.target.value)}
                    rows={3}
                    maxLength={3000}
                    placeholder="Escreva uma mensagem para acompanhar a vaga..."
                    className="w-full resize-none rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-200 focus:border-violet-400 transition"
                  />
                </div>

                {/* Job card preview */}
                <div>
                  <p className="text-xs font-medium text-gray-600 mb-1.5">Preview do card</p>
                  <JobShareCard metadata={jobMetadata} isMine={false} />
                </div>

                <button
                  onClick={() => shareToFeed.mutate()}
                  disabled={shareToFeed.isPending}
                  className="w-full rounded-xl bg-violet-600 py-2.5 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-60 transition-colors flex items-center justify-center gap-2"
                >
                  {shareToFeed.isPending ? (
                    <><Loader2 className="h-4 w-4 animate-spin" /> Publicando...</>
                  ) : (
                    <><Rss className="h-4 w-4" /> Publicar no feed</>
                  )}
                </button>

                {shareToFeed.isError && (
                  <p className="text-xs text-red-500 text-center">
                    Erro ao publicar. Tente novamente.
                  </p>
                )}
              </>
            )}
          </div>
        )}

      </div>
    </div>
  )
}
