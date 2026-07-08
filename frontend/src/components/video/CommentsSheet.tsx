import { useState } from 'react'
import { Link } from 'react-router-dom'
import { X, Trash2, Send } from 'lucide-react'
import { Avatar } from '@/components/ui/Avatar'
import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import { useVideoComments, useAddComment, useDeleteComment } from '@/hooks/useVideos'
import { useAuth } from '@/hooks/useAuth'
import { formatRelative } from '@/utils/format'
import { MessageCircle } from 'lucide-react'

interface CommentsSheetProps {
  open: boolean
  videoUuid: string
  videoOwnerUuid: string
  onClose: () => void
  onAdded?: () => void
  onDeleted?: () => void
}

/**
 * Bottom sheet de comentários de um reel.
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
export function CommentsSheet({ open, videoUuid, videoOwnerUuid, onClose, onAdded, onDeleted }: CommentsSheetProps) {
  const { user } = useAuth()
  const { data, isLoading } = useVideoComments(videoUuid, open)
  const addComment = useAddComment(videoUuid)
  const deleteComment = useDeleteComment(videoUuid)
  const [text, setText] = useState('')

  const comments = data?.data ?? []

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const content = text.trim()
    if (!content) return
    addComment.mutate(content, {
      onSuccess: () => { setText(''); onAdded?.() },
    })
  }

  if (!open) return null

  return (
    <>
      <div className="fixed inset-0 z-[65] bg-black/50" onClick={onClose} aria-hidden="true" />
      <div className="fixed inset-x-0 bottom-0 z-[65] flex max-h-[75vh] flex-col rounded-t-2xl bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
          <h3 className="text-sm font-semibold text-gray-900">
            Comentários {data?.meta?.total ? `(${data.meta.total})` : ''}
          </h3>
          <button onClick={onClose} aria-label="Fechar" className="rounded-full p-1 text-gray-400 hover:bg-gray-100">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="flex-1 space-y-4 overflow-y-auto px-4 py-3">
          {isLoading ? (
            <p className="py-6 text-center text-sm text-gray-400">Carregando…</p>
          ) : comments.length === 0 ? (
            <EmptyState
              title="Nenhum comentário ainda"
              description="Seja o primeiro a comentar."
              icon={<MessageCircle className="h-7 w-7" />}
            />
          ) : (
            comments.map((c) => {
              const canDelete = user?.uuid === c.author.uuid || user?.uuid === videoOwnerUuid
              return (
                <div key={c.uuid} className="flex gap-2.5">
                  <Link to={`/users/${c.author.uuid}`} className="flex-shrink-0">
                    <Avatar src={c.author.avatar_url} name={c.author.name} size="sm" />
                  </Link>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-1">
                      <Link to={`/users/${c.author.uuid}`} className="text-sm font-medium text-gray-900 hover:underline">
                        {c.author.name}
                      </Link>
                      <PremiumBadge plan={c.author.plan} compact />
                      <span className="text-xs text-gray-400">· {formatRelative(c.created_at)}</span>
                    </div>
                    <p className="text-sm text-gray-700">{c.content}</p>
                  </div>
                  {canDelete && (
                    <button
                      onClick={() => deleteComment.mutate(c.uuid, { onSuccess: () => onDeleted?.() })}
                      aria-label="Remover comentário"
                      className="flex-shrink-0 rounded p-1 text-gray-300 hover:bg-red-50 hover:text-red-500"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  )}
                </div>
              )
            })
          )}
        </div>

        <form onSubmit={handleSubmit} className="flex items-center gap-2 border-t border-gray-100 p-3">
          <input
            value={text}
            onChange={(e) => setText(e.target.value)}
            maxLength={1000}
            placeholder="Adicione um comentário…"
            className="flex-1 rounded-full border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          />
          <Button type="submit" size="sm" loading={addComment.isPending} disabled={!text.trim()} icon={<Send className="h-4 w-4" />} />
        </form>
      </div>
    </>
  )
}
