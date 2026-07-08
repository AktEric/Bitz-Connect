import { useState } from 'react'
import { Pencil, Trash2, MessageCircle, Building2, MoreHorizontal, Share2, Bookmark } from 'lucide-react'
import { formatDistanceToNow } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import type { Post } from '@/types/feed.types'
import type { JobMetadata } from '@/types/api.types'
import { useAuth } from '@/hooks/useAuth'
import { useDeletePost, useUpdatePost } from '@/hooks/useFeed'
import { JobShareCard } from '@/components/chat/JobShareCard'
import { UserLink } from '@/components/common/UserLink'
import { ProfileAvatar } from '@/components/common/ProfileAvatar'
import { CompanyLink } from '@/components/common/CompanyLink'
import { PremiumBadge } from '@/components/subscription/PremiumBadge'
import LikeButton from './LikeButton'
import CommentList from './CommentList'

interface PostCardProps {
  post: Post
}

/**
 * Card that displays a single feed post with author info, image, actions and comments.
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.7
 */
export default function PostCard({ post }: PostCardProps) {
  const { user } = useAuth()
  const [showComments, setShowComments] = useState(false)
  const [isEditing, setIsEditing]       = useState(false)
  const [editContent, setEditContent]   = useState(post.content)
  const [menuOpen, setMenuOpen]         = useState(false)
  const [shareCopied, setShareCopied]   = useState(false)

  const deletePost = useDeletePost()
  const updatePost = useUpdatePost()

  const isAuthor = user?.uuid === post.user.uuid

  function handleDelete() {
    if (!confirm('Tem certeza que deseja excluir esta publicação?')) return
    setMenuOpen(false)
    deletePost.mutate(post.uuid)
  }

  function handleEditSubmit(e: React.FormEvent) {
    e.preventDefault()
    const trimmed = editContent.trim()
    if (!trimmed || trimmed === post.content || updatePost.isPending) return
    updatePost.mutate({ uuid: post.uuid, content: trimmed }, { onSuccess: () => setIsEditing(false) })
  }

  function handleEditCancel() {
    setEditContent(post.content)
    setIsEditing(false)
  }

  async function handleShare() {
    const url = `${window.location.origin}/posts/${post.uuid}`
    const text = post.content.slice(0, 120)

    if (navigator.share) {
      try {
        await navigator.share({ title: 'HotelConnect', text, url })
        return
      } catch {
        // user cancelled or not supported — fall through to clipboard
      }
    }

    try {
      await navigator.clipboard.writeText(url)
      setShareCopied(true)
      setTimeout(() => setShareCopied(false), 2000)
    } catch {
      // clipboard also failed — nothing to do
    }
  }

  return (
    <article className="rounded-2xl border border-gray-100 bg-white shadow-card overflow-hidden animate-fade-in">
      {/* Header */}
      <div className="flex items-start gap-3 px-5 pt-5">
        <div className="flex-shrink-0">
          <ProfileAvatar uuid={post.user.uuid} name={post.user.name} src={post.user.avatar_url} size="md" />
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-1.5">
            <UserLink uuid={post.user.uuid} name={post.user.name} className="font-semibold text-gray-900 hover:text-violet-600 transition-colors text-sm" />
            <PremiumBadge plan={post.user.plan} compact />
            {post.hotel && (
              <>
                <span className="text-gray-300">·</span>
                <CompanyLink slug={post.hotel.slug} name={post.hotel.name} className="flex items-center gap-1 text-xs text-gray-500 hover:text-violet-600 transition-colors">
                  <Building2 className="h-3 w-3" />
                  {post.hotel.name}
                </CompanyLink>
              </>
            )}
          </div>
          <p className="text-xs text-gray-400 mt-0.5 flex items-center gap-1">
            <time dateTime={post.created_at}>
              {formatDistanceToNow(new Date(post.created_at), { addSuffix: true, locale: ptBR })}
            </time>
            {post.edited && <><span>·</span><span>editado</span></>}
          </p>
        </div>

        {/* Menu */}
        <div className="relative flex-shrink-0">
          <button
            onClick={() => setMenuOpen((v) => !v)}
            className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors"
            aria-label="Opções"
          >
            <MoreHorizontal className="h-4 w-4" />
          </button>

          {menuOpen && (
            <div className="absolute right-0 top-8 z-10 w-44 rounded-xl border border-gray-100 bg-white shadow-panel py-1 animate-scale-in">
              <button onClick={() => { setMenuOpen(false) }}
                className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                <Bookmark className="h-4 w-4 text-gray-400" /> Salvar
              </button>
              {isAuthor && (
                <>
                  <button onClick={() => { setIsEditing(true); setMenuOpen(false) }}
                    className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    <Pencil className="h-4 w-4 text-gray-400" /> Editar
                  </button>
                  <button onClick={handleDelete} disabled={deletePost.isPending}
                    className="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-500 hover:bg-red-50 disabled:opacity-50 transition-colors">
                    <Trash2 className="h-4 w-4" /> Excluir
                  </button>
                </>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Content */}
      <div className="px-5 mt-3">
        {isEditing ? (
          <form onSubmit={handleEditSubmit}>
            <textarea
              value={editContent}
              onChange={(e) => setEditContent(e.target.value)}
              rows={4}
              maxLength={3000}
              className="w-full resize-none rounded-xl border border-gray-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 transition"
              autoFocus
            />
            <div className="mt-2 flex justify-end gap-2">
              <button type="button" onClick={handleEditCancel}
                className="rounded-lg px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 transition-colors">
                Cancelar
              </button>
              <button type="submit"
                disabled={!editContent.trim() || editContent.trim() === post.content || updatePost.isPending}
                className="rounded-lg bg-violet-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50 transition-colors">
                {updatePost.isPending ? 'Salvando...' : 'Salvar'}
              </button>
            </div>
          </form>
        ) : (
          <p className="whitespace-pre-wrap text-sm text-gray-800 leading-relaxed">{post.content}</p>
        )}
      </div>

      {/* Job share card — rendered for job_share posts */}
      {!isEditing && post.type === 'job_share' && (
        <div className="mt-3 px-5">
          <JobShareCard
            metadata={(post.metadata as JobMetadata | null) ?? null}
            isMine={false}
          />
        </div>
      )}

      {/* Post image — only shown for regular posts */}
      {!isEditing && post.type !== 'job_share' && post.image_url && (
        <div className="mt-3 px-5">
          <img
            src={post.image_url}
            alt="Imagem da publicação"
            className="w-full rounded-xl object-cover max-h-[480px] border border-gray-100"
            loading="lazy"
          />
        </div>
      )}

      {/* Actions */}
      {!isEditing && (
        <div className="mt-3 flex items-center gap-1 border-t border-gray-100 px-5 py-2.5">
          <LikeButton
            postUuid={post.uuid}
            likesCount={post.likes_count}
            likedByMe={post.liked_by_me}
          />

          <button
            onClick={() => setShowComments((v) => !v)}
            aria-label={showComments ? 'Ocultar comentários' : 'Ver comentários'}
            aria-expanded={showComments}
            className={`flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium transition-colors
              ${showComments ? 'bg-violet-50 text-violet-600' : 'text-gray-500 hover:bg-gray-100'}`}
          >
            <MessageCircle className="h-4 w-4" />
            <span>{post.comments_count}</span>
          </button>

          {/* Share */}
          <button
            onClick={handleShare}
            className="ml-auto flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-100 transition-colors"
            aria-label="Compartilhar"
          >
            <Share2 className="h-4 w-4" />
            <span className="hidden sm:inline">
              {shareCopied ? 'Link copiado!' : 'Compartilhar'}
            </span>
          </button>
        </div>
      )}

      {/* Comments */}
      {showComments && !isEditing && (
        <div className="px-5 pb-4">
          <CommentList postUuid={post.uuid} />
        </div>
      )}
    </article>
  )
}
