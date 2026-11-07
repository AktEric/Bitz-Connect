import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ThumbsUp } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { FeedPost, ReactionType } from '@/types/workspace.types'
import { cn } from '@/utils/cn'

export const REACTION_META: Record<ReactionType, { emoji: string; label: string }> = {
  like: { emoji: '👍', label: 'Curtir' },
  love: { emoji: '❤️', label: 'Amei' },
  celebrate: { emoji: '🎉', label: 'Parabéns' },
  clap: { emoji: '👏', label: 'Palmas' },
}
const ORDER: ReactionType[] = ['like', 'love', 'celebrate', 'clap']

/** Botão de reação (clique = curtir/remover; passar o mouse ou tocar e segurar mostra as quatro) + resumo. */
export default function ReactionBar({ slug, post }: { slug: string; post: FeedPost }) {
  const qc = useQueryClient()
  const [picker, setPicker] = useState(false)
  const react = useMutation({
    mutationFn: (r: ReactionType | null) => workspaceService.reactPost(slug, post.uuid, r),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] }),
  })
  const mine = post.my_reaction
  const summary = ORDER.filter((r) => post.reactions[r] > 0).sort((a, b) => post.reactions[b] - post.reactions[a])

  const choose = (r: ReactionType) => { setPicker(false); react.mutate(mine === r ? null : r) }

  return (
    <div className="flex items-center gap-3">
      <div className="relative" onMouseEnter={() => setPicker(true)} onMouseLeave={() => setPicker(false)}>
        <button
          type="button"
          onClick={() => choose(mine ?? 'like')}
          onFocus={() => setPicker(true)}
          aria-pressed={!!mine}
          className={cn('flex items-center gap-1.5 rounded-lg px-1 py-0.5 transition-colors', mine ? 'font-medium text-violet-700' : 'hover:text-gray-700')}
        >
          {mine ? <span className="text-base leading-none">{REACTION_META[mine].emoji}</span> : <ThumbsUp className="h-4 w-4" />}
          {mine ? REACTION_META[mine].label : 'Reagir'}
        </button>
        {picker && (
          <div className="absolute bottom-full left-0 z-20 pb-1">
            <div className="flex gap-1 rounded-full border border-gray-100 bg-white px-2 py-1 shadow-lg" role="menu" aria-label="Reações">
              {ORDER.map((r) => (
                <button
                  key={r} type="button" role="menuitem" title={REACTION_META[r].label} aria-label={REACTION_META[r].label}
                  onClick={() => choose(r)}
                  className={cn('rounded-full px-1.5 py-0.5 text-xl transition-transform hover:scale-125', mine === r && 'bg-violet-50')}
                >
                  {REACTION_META[r].emoji}
                </button>
              ))}
            </div>
          </div>
        )}
      </div>
      {post.likes_count > 0 && (
        <span className="flex items-center gap-1 text-xs text-gray-500" title={summary.map((r) => `${REACTION_META[r].label}: ${post.reactions[r]}`).join(' · ')}>
          <span className="flex -space-x-1">{summary.slice(0, 3).map((r) => <span key={r} className="text-sm leading-none">{REACTION_META[r].emoji}</span>)}</span>
          {post.likes_count}
        </span>
      )}
    </div>
  )
}
