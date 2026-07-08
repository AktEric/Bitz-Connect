import { Heart } from 'lucide-react'
import { useLikePost } from '@/hooks/useFeed'

interface LikeButtonProps {
  postUuid: string
  likesCount: number
  likedByMe: boolean
}

/**
 * Toggle like/unlike button with optimistic count update.
 * Requirements: 4.5, 4.6
 */
export default function LikeButton({ postUuid, likesCount, likedByMe }: LikeButtonProps) {
  const { mutate: toggleLike, isPending } = useLikePost()

  function handleClick() {
    if (isPending) return
    toggleLike({ uuid: postUuid, likedByMe })
  }

  return (
    <button
      onClick={handleClick}
      disabled={isPending}
      aria-label={likedByMe ? 'Descurtir publicação' : 'Curtir publicação'}
      className={`flex items-center gap-1.5 rounded-md px-2 py-1 text-sm transition-colors
        ${likedByMe
          ? 'text-rose-500 hover:bg-rose-50'
          : 'text-gray-500 hover:bg-gray-100 hover:text-rose-400'}
        disabled:opacity-60`}
    >
      <Heart
        className={`h-4 w-4 transition-all ${likedByMe ? 'fill-rose-500 stroke-rose-500' : ''}`}
      />
      <span>{likesCount}</span>
    </button>
  )
}
