import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ThumbsUp } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { FeedPost, ReactionType } from '@/types/workspace.types'
import { cn } from '@/utils/cn'
import PersonLink, { PersonAvatarLink } from './PersonLink'
import { Modal, Spinner } from './ui'

export const REACTION_META: Record<ReactionType, { emoji: string; label: string }> = {
  like: { emoji: '👍', label: 'Curtir' },
  love: { emoji: '❤️', label: 'Amei' },
  celebrate: { emoji: '🎉', label: 'Parabéns' },
  clap: { emoji: '👏', label: 'Palmas' },
  laugh: { emoji: '😂', label: 'Risada' },
  surprised: { emoji: '😮', label: 'Surpreso' },
  thinking: { emoji: '🤔', label: 'Pensando' },
  sad: { emoji: '😢', label: 'Triste' },
}
export const REACTION_ORDER: ReactionType[] = ['like', 'love', 'celebrate', 'clap', 'laugh', 'surprised', 'thinking', 'sad']

/** Quem reagiu, com filtro por tipo. Cada pessoa leva ao perfil. */
export function ReactionsModal({ slug, post, onClose }: { slug: string; post: FeedPost; onClose: () => void }) {
  const [filter, setFilter] = useState<ReactionType | 'all'>('all')
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'reactions', post.uuid], queryFn: () => workspaceService.postReactions(slug, post.uuid) })
  const items = (data?.items ?? []).filter((i) => filter === 'all' || i.reaction === filter)

  return (
    <Modal title="Reações" onClose={onClose}>
      {isLoading || !data ? <Spinner /> : (
        <>
          <div role="tablist" aria-label="Filtrar por reação" className="mb-3 flex flex-wrap gap-1.5">
            <button type="button" role="tab" aria-selected={filter === 'all'} onClick={() => setFilter('all')}
              className={cn('rounded-full px-3 py-1 text-sm font-medium', filter === 'all' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200')}>
              Todas {data.total}
            </button>
            {REACTION_ORDER.filter((r) => data.counts[r] > 0).map((r) => (
              <button key={r} type="button" role="tab" aria-selected={filter === r} onClick={() => setFilter(r)} title={REACTION_META[r].label}
                className={cn('rounded-full px-3 py-1 text-sm', filter === r ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200')}>
                {REACTION_META[r].emoji} {data.counts[r]}
              </button>
            ))}
          </div>
          <ul className="max-h-80 divide-y divide-gray-100 overflow-y-auto">
            {items.map((i) => (
              <li key={i.person.uuid} className="flex items-center gap-3 py-2">
                <PersonAvatarLink person={i.person} />
                <div className="min-w-0 flex-1">
                  <PersonLink person={i.person} className="block truncate text-sm font-medium text-gray-900" />
                  {i.person.job_title && <p className="truncate text-xs text-gray-500">{i.person.job_title}</p>}
                </div>
                <span className="text-xl" title={REACTION_META[i.reaction].label}>{REACTION_META[i.reaction].emoji}</span>
              </li>
            ))}
          </ul>
        </>
      )}
    </Modal>
  )
}

/** Botão de reação (clique = curtir/remover; passar o mouse ou tocar e segurar mostra todas) + resumo clicável de quem reagiu. */
export default function ReactionBar({ slug, post }: { slug: string; post: FeedPost }) {
  const qc = useQueryClient()
  const [picker, setPicker] = useState(false)
  const [who, setWho] = useState(false)
  const react = useMutation({
    mutationFn: (r: ReactionType | null) => workspaceService.reactPost(slug, post.uuid, r),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] }); qc.invalidateQueries({ queryKey: ['ws', slug, 'reactions', post.uuid] }) },
  })
  const mine = post.my_reaction
  const summary = REACTION_ORDER.filter((r) => post.reactions[r] > 0).sort((a, b) => post.reactions[b] - post.reactions[a])

  const choose = (r: ReactionType) => { setPicker(false); react.mutate(mine === r ? null : r) }

  return (
    <div className="flex items-center gap-3">
      <div className="relative" onMouseEnter={() => setPicker(true)} onMouseLeave={() => setPicker(false)}>
        <button
          type="button"
          onClick={() => choose(mine ?? 'like')}
          onFocus={() => setPicker(true)}
          aria-pressed={!!mine}
          className={cn('flex items-center gap-1.5 rounded-lg px-1 py-0.5 transition-colors', mine ? 'font-medium text-brand-700' : 'hover:text-gray-700')}
        >
          {mine ? <span className="text-base leading-none">{REACTION_META[mine].emoji}</span> : <ThumbsUp className="h-4 w-4" />}
          {mine ? REACTION_META[mine].label : 'Reagir'}
        </button>
        {picker && (
          <div className="absolute bottom-full left-0 z-20 pb-1">
            <div className="flex gap-1 rounded-full border border-gray-100 bg-white px-2 py-1 shadow-lg" role="menu" aria-label="Reações">
              {REACTION_ORDER.map((r) => (
                <button
                  key={r} type="button" role="menuitem" title={REACTION_META[r].label} aria-label={REACTION_META[r].label}
                  onClick={() => choose(r)}
                  className={cn('rounded-full px-1.5 py-0.5 text-xl transition-transform hover:scale-125', mine === r && 'bg-brand-50')}
                >
                  {REACTION_META[r].emoji}
                </button>
              ))}
            </div>
          </div>
        )}
      </div>
      {post.likes_count > 0 && (
        <button type="button" onClick={() => setWho(true)} aria-label={`Ver quem reagiu (${post.likes_count})`}
          className="flex items-center gap-1 rounded-lg text-xs text-gray-500 hover:text-gray-800 hover:underline"
          title={summary.map((r) => `${REACTION_META[r].label}: ${post.reactions[r]}`).join(' · ')}>
          <span className="flex -space-x-1">{summary.slice(0, 3).map((r) => <span key={r} className="text-sm leading-none">{REACTION_META[r].emoji}</span>)}</span>
          {post.likes_count}
        </button>
      )}
      {who && <ReactionsModal slug={slug} post={post} onClose={() => setWho(false)} />}
    </div>
  )
}
