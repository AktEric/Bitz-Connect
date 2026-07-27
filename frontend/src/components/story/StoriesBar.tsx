import { useState } from 'react'
import { Plus } from 'lucide-react'
import { useStories } from '@/hooks/useStories'
import { useCan } from '@/hooks/useSubscription'
import { useMyHotels } from '@/hooks/useHotel'
import { StoryRing } from './StoryRing'
import { StoryViewer } from './StoryViewer'
import { CreateStoryModal } from './CreateStoryModal'

/**
 * Barra de stories (destaque de vaga/mentoria) no topo do feed.
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
export function StoriesBar() {
  const { data: groups, isLoading } = useStories()
  const { data: myHotels } = useMyHotels()

  const canCreatePersonal = useCan('publish_stories')
  const hasProHotel = (myHotels?.data ?? []).some((h) => h.is_admin && h.is_pro)
  const canCreate = canCreatePersonal || hasProHotel

  const [viewerIndex, setViewerIndex] = useState<number | null>(null)
  const [showCreate, setShowCreate] = useState(false)

  if (isLoading) return null
  if (!groups?.length && !canCreate) return null

  return (
    <>
      <div className="mb-3 flex items-center gap-3 overflow-x-auto rounded-xl border border-gray-100 bg-white p-3 shadow-sm">
        {canCreate && (
          <button onClick={() => setShowCreate(true)} className="flex w-16 flex-shrink-0 flex-col items-center gap-1">
            <span className="flex h-14 w-14 items-center justify-center rounded-full border-2 border-dashed border-violet-300 text-violet-500">
              <Plus className="h-5 w-5" />
            </span>
            <span className="max-w-[64px] truncate text-[11px] text-gray-600">Criar</span>
          </button>
        )}

        {groups?.map((group, i) => (
          <StoryRing
            key={`${group.owner.type}:${group.owner.uuid}`}
            src={group.owner.avatar_url}
            name={group.owner.name}
            label={group.owner.name}
            onClick={() => setViewerIndex(i)}
          />
        ))}
      </div>

      {viewerIndex !== null && groups && groups.length > 0 && (
        <StoryViewer groups={groups} initialIndex={viewerIndex} onClose={() => setViewerIndex(null)} />
      )}

      <CreateStoryModal open={showCreate} onClose={() => setShowCreate(false)} />
    </>
  )
}
