import { useParams } from 'react-router-dom'
import { FileText } from 'lucide-react'
import { useHotel } from '@/hooks/useHotel'
import { useHotelPosts } from '@/hooks/useFeed'
import PostForm from '@/components/feed/PostForm'
import PostCard from '@/components/feed/PostCard'
import { EmptyState } from '@/components/ui/EmptyState'
import { SkeletonCard } from '@/components/ui/Skeleton'

/**
 * HotelPostsPage — publica e lista as publicações feitas em nome do hotel.
 */
export default function HotelPostsPage() {
  const { slug = '' } = useParams<{ slug: string }>()
  const { data: hotel } = useHotel(slug)
  const { data: postsData, isLoading, refetch } = useHotelPosts(slug)

  const posts = postsData?.data ?? []

  return (
    <div className="mx-auto max-w-2xl space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-semibold">Publicações</h1>
        <p className="mt-2 text-muted-foreground">Publique novidades em nome do {hotel?.name ?? 'hotel'}.</p>
      </div>

      {hotel && (
        <PostForm
          hotelUuid={hotel.uuid}
          hotelName={hotel.name}
          hotelLogoUrl={hotel.logo_url}
          placeholder={`Compartilhe novidades em nome de ${hotel.name}...`}
          onPosted={() => refetch()}
        />
      )}

      {isLoading && (
        <div className="space-y-3">
          <SkeletonCard />
          <SkeletonCard />
        </div>
      )}

      {!isLoading && posts.length === 0 && (
        <EmptyState
          title="Nenhuma publicação ainda"
          description="Publicações feitas em nome do hotel aparecerão aqui e no feed."
          icon={<FileText className="h-8 w-8" />}
        />
      )}

      <div className="space-y-4">
        {posts.map((post) => (
          <PostCard key={post.uuid} post={post} />
        ))}
      </div>
    </div>
  )
}
