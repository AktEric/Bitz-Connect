import { useRef } from 'react'
import { Camera } from 'lucide-react'

interface LogoUploadProps {
  currentUrl: string | null
  name: string
  onFileSelect: (file: File) => void
  loading?: boolean
}

/**
 * Hotel logo display with click-to-upload overlay.
 * Requirements: 3.4, 3.5
 */
export default function LogoUpload({ currentUrl, name, onFileSelect, loading = false }: LogoUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null)

  function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (file) onFileSelect(file)
    e.target.value = ''
  }

  return (
    <div
      className="relative h-20 w-20 flex-shrink-0 cursor-pointer overflow-hidden rounded-xl border border-gray-200 bg-white"
      onClick={() => !loading && inputRef.current?.click()}
    >
      {currentUrl ? (
        <img src={currentUrl} alt={name} className="h-full w-full object-cover" />
      ) : (
        <div className="flex h-full w-full items-center justify-center bg-primary/10 text-2xl font-bold text-primary">
          {name.charAt(0).toUpperCase()}
        </div>
      )}

      <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity hover:opacity-100">
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
