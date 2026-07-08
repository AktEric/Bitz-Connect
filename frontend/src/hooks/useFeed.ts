import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import feedService from '@/services/feed.service'
import type { Post } from '@/types/feed.types'

/**
 * Hook that fetches the feed with cursor-based infinite pagination.
 * Requirements: 4.2
 */
export function useFeed() {
  const query = useInfiniteQuery({
    queryKey: ['feed'],
    queryFn: ({ pageParam }) =>
      feedService.getFeed(pageParam as string | undefined),
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
    initialPageParam: undefined as string | undefined,
  })

  const posts = query.data?.pages.flatMap((p) => p.data) ?? []

  return { ...query, posts }
}

/**
 * Mutation hook to create a new post.
 * Requirements: 4.1
 */
export function useCreatePost() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ content, hotel_id, image }: { content: string; hotel_id?: number; image?: File }) =>
      feedService.createPost(content, hotel_id, image),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['feed'] }),
  })
}

/**
 * Mutation hook to update an existing post.
 * Requirements: 4.3
 */
export function useUpdatePost() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ uuid, content }: { uuid: string; content: string }) =>
      feedService.updatePost(uuid, content),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['feed'] }),
  })
}

/**
 * Mutation hook to delete a post.
 * Requirements: 4.4
 */
export function useDeletePost() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (uuid: string) => feedService.deletePost(uuid),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['feed'] }),
  })
}

/**
 * Mutation hook that toggles like/unlike with optimistic UI update.
 * Requirements: 4.5, 4.6
 */
export function useLikePost() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ uuid, likedByMe }: { uuid: string; likedByMe: boolean }) =>
      likedByMe ? feedService.unlikePost(uuid) : feedService.likePost(uuid),

    // Optimistic update: toggle liked_by_me and adjust likes_count immediately
    onMutate: async ({ uuid, likedByMe }) => {
      await qc.cancelQueries({ queryKey: ['feed'] })

      const previous = qc.getQueryData(['feed'])

      qc.setQueryData<ReturnType<typeof qc.getQueryData<{ pages: { data: Post[] }[] }>>>(['feed'], (old: any) => {
        if (!old) return old
        return {
          ...old,
          pages: old.pages.map((page: { data: Post[] }) => ({
            ...page,
            data: page.data.map((post: Post) =>
              post.uuid === uuid
                ? {
                    ...post,
                    liked_by_me: !likedByMe,
                    likes_count: likedByMe ? post.likes_count - 1 : post.likes_count + 1,
                  }
                : post,
            ),
          })),
        }
      })

      return { previous }
    },

    onError: (_err, _vars, context: any) => {
      if (context?.previous) {
        qc.setQueryData(['feed'], context.previous)
      }
    },

    onSettled: () => qc.invalidateQueries({ queryKey: ['feed'] }),
  })
}

/**
 * Hook to fetch comments for a post.
 * Requirements: 4.7
 */
export function usePostComments(postUuid: string) {
  return useQuery({
    queryKey: ['post-comments', postUuid],
    queryFn: () => feedService.getComments(postUuid),
    enabled: !!postUuid,
  })
}

/**
 * Mutation hook to add a comment to a post.
 * Requirements: 4.7
 */
export function useAddComment(postUuid: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ content, parentUuid }: { content: string; parentUuid?: string }) =>
      feedService.addComment(postUuid, content, parentUuid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['post-comments', postUuid] })
      qc.invalidateQueries({ queryKey: ['feed'] })
    },
  })
}

/**
 * Mutation hook to delete a comment.
 * Requirements: 4.8
 */
export function useDeleteComment(postUuid: string) {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: (commentUuid: string) => feedService.deleteComment(postUuid, commentUuid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['post-comments', postUuid] })
      qc.invalidateQueries({ queryKey: ['feed'] })
    },
  })
}
