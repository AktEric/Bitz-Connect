import { Avatar } from '@/components/ui/Avatar'

interface StoryRingProps {
  src?: string | null
  name: string
  label: string
  onClick: () => void
}

/**
 * Avatar com anel em degradê da marca, usado nos círculos de story.
 * Sprint: monetization-hotelconnect-pro (Stories)
 */
export function StoryRing({ src, name, label, onClick }: StoryRingProps) {
  return (
    <button onClick={onClick} className="flex w-16 flex-shrink-0 flex-col items-center gap-1">
      <div className="rounded-full bg-gradient-to-tr from-[#0b0d12] to-[#4d8ff5] p-[2px]">
        <div className="rounded-full bg-white p-[2px]">
          <Avatar src={src} name={name} size="lg" />
        </div>
      </div>
      <span className="max-w-[64px] truncate text-[11px] text-gray-600">{label}</span>
    </button>
  )
}
