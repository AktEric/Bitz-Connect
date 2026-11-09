import { useState } from 'react'
import { Trash2, CornerDownRight, Send } from 'lucide-react'
import { formatDistanceToNow } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { usePostComments, useAddComment, useDeleteComment } from '@/hooks/useFeed'
import { useAuth } from '@/hooks/useAuth'
import { Avatar } from '@/components/ui/Avatar'
import { UserLink } from '@/components/common/UserLink'
import { ProfileAvatar } from '@/components/common/ProfileAvatar'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import type { Comment } from '@/types/feed.types'

// ──────────────────────────────────────────────────────────
// ReplyForm — inline form for responding to a comment
// ──────────────────────────────────────────────────────────

function ReplyForm({
  postUuid,
  parentUuid,
  replyingTo,
  onClose,
}: {
  postUuid: string
  parentUuid: string
  replyingTo: string
  onClose: () => void
}) {
  const { user } = useAuth()
  const [text, setText] = useState(`@${replyingTo} `)
  const add = useAddComment(postUuid)

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const trimmed = text.trim()
    if (!trimmed || add.isPending) return
    add.mutate(
      { content: trimmed, parentUuid },
      { onSuccess: () => { setText(''); onClose() } },
    )
  }

  return (
    <form onSubmit={handleSubmit} className="mt-2 flex items-center gap-2 pl-2">
      <Avatar src={user?.avatar_url ?? null} name={user?.name ?? 'U'} size="xs" />
      <div className="flex flex-1 items-center gap-1 rounded-full border border-brand-300 bg-white px-3 py-1 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-100 transition-all">
        <input
          autoFocus
          type="text"
          value={text}
          onChange={(e) => setText(e.target.value)}
          maxLength={1000}
          placeholder={`Responder @${replyingTo}...`}
          className="flex-1 bg-transparent text-xs outline-none text-gray-800 placeholder:text-gray-400"
        />
        <button
          type="submit"
          disabled={!text.trim() || add.isPending}
          className="text-brand-500 hover:text-brand-700 disabled:opacity-40 transition-colors"
          aria-label="Enviar resposta"
        >
          <Send className="h-3.5 w-3.5" />
        </button>
      </div>
      <button
        type="button"
        onClick={onClose}
        className="text-xs text-gray-400 hover:text-gray-600 transition-colors"
      >
        Cancelar
      </button>
    </form>
  )
}

// ──────────────────────────────────────────────────────────
// ReplyItem — a single reply (sub-comment)
// ──────────────────────────────────────────────────────────

function ReplyItem({
  reply,
  postUuid,
  onReply,
}: {
  reply: Comment
  postUuid: string
  onReply: (uuid: string, name: string) => void
}) {
  const { user } = useAuth()
  const deleteComment = useDeleteComment(postUuid)
  const isAuthor = user?.uuid === reply.user.uuid

  return (
    <div className="mt-2 flex gap-2 pl-3 border-l-2 border-brand-100">
      <div className="flex-shrink-0">
        <ProfileAvatar uuid={reply.user.uuid} name={reply.user.name} src={reply.user.avatar_url} size="xs" />
      </div>
      <div className="flex-1 min-w-0">
        <div className="rounded-xl bg-gray-50 px-3 py-2">
          <div className="flex items-center justify-between gap-2">
            <span className="flex items-center gap-1 min-w-0">
              <UserLink uuid={reply.user.uuid} name={reply.user.name} className="text-xs font-semibold text-gray-800 hover:text-brand-600 transition-colors" />
              <PremiumBadge plan={reply.user.plan} compact />
            </span>
            {isAuthor && (
              <button
                onClick={() => deleteComment.mutate(reply.uuid)}
                disabled={deleteComment.isPending}
                aria-label="Excluir resposta"
                className="text-gray-300 hover:text-red-400 disabled:opacity-50 transition-colors"
              >
                <Trash2 className="h-3 w-3" />
              </button>
            )}
          </div>
          <p className="mt-0.5 text-xs text-gray-700 leading-relaxed">{reply.content}</p>
        </div>
        <div className="mt-0.5 flex items-center gap-3 pl-1">
          <time className="text-[10px] text-gray-400">
            {formatDistanceToNow(new Date(reply.created_at), { addSuffix: true, locale: ptBR })}
          </time>
          <button
            onClick={() => onReply(reply.uuid, reply.user.name)}
            className="text-[10px] font-medium text-gray-400 hover:text-brand-600 transition-colors"
          >
            Responder
          </button>
        </div>
      </div>
    </div>
  )
}

// ──────────────────────────────────────────────────────────
// CommentItem — a single top-level comment with its replies
// ──────────────────────────────────────────────────────────

function CommentItem({
  comment,
  postUuid,
}: {
  comment: Comment
  postUuid: string
}) {
  const { user } = useAuth()
  const deleteComment = useDeleteComment(postUuid)
  const isAuthor = user?.uuid === comment.user.uuid

  const [replyTarget, setReplyTarget] = useState<{ uuid: string; name: string } | null>(null)
  const [showReplies, setShowReplies] = useState(true)

  const replies = comment.replies ?? []

  function openReply(uuid: string, name: string) {
    setReplyTarget({ uuid, name })
    setShowReplies(true)
  }

  return (
    <li className="flex gap-2.5">
      <div className="flex-shrink-0">
        <ProfileAvatar uuid={comment.user.uuid} name={comment.user.name} src={comment.user.avatar_url} size="sm" />
      </div>

      <div className="flex-1 min-w-0">
        {/* Bubble */}
        <div className="rounded-2xl bg-gray-50 px-3.5 py-2.5">
          <div className="flex items-center justify-between gap-2">
            <span className="flex items-center gap-1 min-w-0">
              <UserLink uuid={comment.user.uuid} name={comment.user.name} className="text-xs font-semibold text-gray-900 hover:text-brand-600 transition-colors" />
              <PremiumBadge plan={comment.user.plan} compact />
            </span>
            {isAuthor && (
              <button
                onClick={() => deleteComment.mutate(comment.uuid)}
                disabled={deleteComment.isPending}
                aria-label="Excluir comentário"
                className="text-gray-300 hover:text-red-400 disabled:opacity-50 transition-colors"
              >
                <Trash2 className="h-3 w-3" />
              </button>
            )}
          </div>
          <p className="mt-0.5 text-xs text-gray-700 leading-relaxed">{comment.content}</p>
        </div>

        {/* Meta + actions */}
        <div className="mt-1 flex items-center gap-3 pl-1">
          <time className="text-[10px] text-gray-400">
            {formatDistanceToNow(new Date(comment.created_at), { addSuffix: true, locale: ptBR })}
          </time>
          <button
            onClick={() => openReply(comment.uuid, comment.user.name)}
            className="flex items-center gap-0.5 text-[10px] font-medium text-gray-400 hover:text-brand-600 transition-colors"
          >
            <CornerDownRight className="h-3 w-3" />
            Responder
          </button>
          {replies.length > 0 && (
            <button
              onClick={() => setShowReplies((v) => !v)}
              className="text-[10px] font-medium text-brand-500 hover:text-brand-700 transition-colors"
            >
              {showReplies ? 'Ocultar' : `Ver ${replies.length} resposta${replies.length > 1 ? 's' : ''}`}
            </button>
          )}
        </div>

        {/* Reply form */}
        {replyTarget && (
          <ReplyForm
            postUuid={postUuid}
            parentUuid={replyTarget.uuid}
            replyingTo={replyTarget.name}
            onClose={() => setReplyTarget(null)}
          />
        )}

        {/* Replies */}
        {showReplies && replies.length > 0 && (
          <div className="mt-1 space-y-1">
            {replies.map((reply) => (
              <ReplyItem
                key={reply.uuid}
                reply={reply}
                postUuid={postUuid}
                onReply={openReply}
              />
            ))}
          </div>
        )}
      </div>
    </li>
  )
}

// ──────────────────────────────────────────────────────────
// CommentList
// ──────────────────────────────────────────────────────────

interface CommentListProps {
  postUuid: string
}

export default function CommentList({ postUuid }: CommentListProps) {
  const { user } = useAuth()
  const [newComment, setNewComment] = useState('')

  const { data: comments = [], isLoading } = usePostComments(postUuid)
  const addComment = useAddComment(postUuid)

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    const trimmed = newComment.trim()
    if (!trimmed || addComment.isPending) return
    addComment.mutate({ content: trimmed }, { onSuccess: () => setNewComment('') })
  }

  return (
    <div className="space-y-4">
      {/* List */}
      {isLoading ? (
        <div className="flex justify-center py-4">
          <div className="h-5 w-5 animate-spin rounded-full border-2 border-brand-400 border-t-transparent" />
        </div>
      ) : comments.length === 0 ? (
        <p className="py-2 text-center text-xs text-gray-400">Nenhum comentário ainda. Seja o primeiro!</p>
      ) : (
        <ul className="space-y-4">
          {comments.map((comment) => (
            <CommentItem key={comment.uuid} comment={comment} postUuid={postUuid} />
          ))}
        </ul>
      )}

      {/* New comment */}
      {user && (
        <form onSubmit={handleSubmit} className="flex items-center gap-2.5">
          <Avatar src={user.avatar_url} name={user.name} size="sm" className="flex-shrink-0" />
          <div className="flex flex-1 items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-4 py-1.5 focus-within:border-brand-400 focus-within:bg-white focus-within:ring-2 focus-within:ring-brand-100 transition-all">
            <input
              type="text"
              value={newComment}
              onChange={(e) => setNewComment(e.target.value)}
              placeholder="Escreva um comentário..."
              maxLength={1000}
              className="flex-1 bg-transparent text-sm outline-none text-gray-800 placeholder:text-gray-400"
            />
            <button
              type="submit"
              disabled={!newComment.trim() || addComment.isPending}
              className="text-brand-500 hover:text-brand-700 disabled:opacity-40 transition-colors"
              aria-label="Enviar comentário"
            >
              <Send className="h-4 w-4" />
            </button>
          </div>
        </form>
      )}
    </div>
  )
}
