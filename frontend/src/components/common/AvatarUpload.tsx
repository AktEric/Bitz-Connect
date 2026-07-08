import { useRef } from 'react'
import { Camera } from 'lucide-react'

interface AvatarUploadProps {
  currentUrl: string | null
  name: string
  onFileSelect: (file: File) => void
  loading?: boolean
  size?: 'sm' | 'md' | 'lg'
}

const sizes = {
  sm: 'h-10 w-10 text-sm',
  md: 'h-16 w-16 text-xl',
  lg: 'h-24 w-24 text-3xl',
}

/**
 * Avatar display with click-to-upload overlay.
 * Requirements: 2.3, 2.4
 */
export default function AvatarUpload({
  currentUrl,
  name,
  onFileSelect,
  loading = false,
  size = 'lg',
}: AvatarUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null)

  function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (file) onFileSelect(file)
    // reset so same file can be re-selected
    e.target.value = ''
  }

  const initials = name
    .split(' ')
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()

  return (
    <div
      className={`relative ${sizes[size]} flex-shrink-0 cursor-pointer rounded-full`}
      onClick={() => !loading && inputRef.current?.click()}
    >
      {currentUrl ? (
        <img
          src={currentUrl}
          alt={name}
          className="h-full w-full rounded-full object-cover"
        />
      ) : (
        <div className="flex h-full w-full items-center justify-center rounded-full bg-primary/10 font-semibold text-primary">
          {initials}
        </div>
      )}

      {/* overlay */}
      <div className="absolute inset-0 flex items-center justify-center rounded-full bg-black/40 opacity-0 transition-opacity hover:opacity-100">
        {loading ? (
          <div className="h-5 w-5 animate-spin rounded-full border-2 border-white border-t-transparent" />
        ) : (
          <Camera className="h-5 w-5 text-white" />
        )}
      </div>

      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        className="hidden"
        onChange={handleChange}
      />
    </div>
  )
}
