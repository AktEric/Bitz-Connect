import { useRef, useState } from 'react'
import { Image, X, Loader2 } from 'lucide-react'
import { useCreatePost } from '@/hooks/useFeed'
import { useAuth } from '@/hooks/useAuth'
import { Avatar } from '@/components/ui/Avatar'

const MAX_LENGTH = 3000

/**
 * Form to create a new post — supports text + optional image.
 * Requirements: 4.1
 */
export default function PostForm() {
  const { user } = useAuth()
  const [content, setContent]         = useState('')
  const [image, setImage]             = useState<File | null>(null)
  const [preview, setPreview]         = useState<string | null>(null)
  const fileRef                        = useRef<HTMLInputElement>(null)
  const createPost                     = useCreatePost()

  const remaining  = MAX_LENGTH - content.length
  const isOverLimit = remaining < 0
  const isEmpty    = content.trim().length === 0 && !image

  function handleImageSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (!file) return
    setImage(file)
    setPreview(URL.createObjectURL(file))
  }

  function removeImage() {
    setImage(null)
    if (preview) URL.revokeObjectURL(preview)
    setPreview(null)
    if (fileRef.current) fileRef.current.value = ''
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (isEmpty || isOverLimit || createPost.isPending) return

    createPost.mutate(
      { content: content.trim() || ' ', hotel_id: undefined, image: image ?? undefined },
      {
        onSuccess: () => {
          setContent('')
          removeImage()
        },
      },
    )
  }

  if (!user) return null

  return (
    <form onSubmit={handleSubmit} className="rounded-2xl border border-gray-100 bg-white p-4 shadow-card">
      <div className="flex gap-3">
        <Avatar src={user.avatar_url} name={user.name} size="md" className="flex-shrink-0 mt-0.5" />

        <div className="flex-1 min-w-0">
          <textarea
            value={content}
            onChange={(e) => setContent(e.target.value)}
            placeholder="Compartilhe novidades com a comunidade hoteleira..."
            rows={preview ? 2 : 3}
            maxLength={MAX_LENGTH + 1}
            className={[
              'w-full resize-none rounded-xl border bg-gray-50 px-4 py-3 text-sm outline-none transition-all',
              'focus:bg-white focus:ring-2',
              isOverLimit
                ? 'border-red-300 focus:border-red-400 focus:ring-red-100'
                : 'border-gray-200 focus:border-violet-400 focus:ring-violet-100',
            ].join(' ')}
          />

          {/* Image preview */}
          {preview && (
            <div className="relative mt-2 inline-block">
              <img
                src={preview}
                alt="Preview"
                className="max-h-64 w-auto rounded-xl object-cover border border-gray-200"
              />
              <button
                type="button"
                onClick={removeImage}
                className="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-gray-800 text-white hover:bg-gray-900 shadow-sm transition-colors"
                aria-label="Remover imagem"
              >
                <X className="h-3.5 w-3.5" />
              </button>
            </div>
          )}

          {/* Footer */}
          <div className="mt-2.5 flex items-center justify-between">
            <div className="flex items-center gap-1">
              {/* Image upload button */}
              <input
                ref={fileRef}
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                className="hidden"
                onChange={handleImageSelect}
              />
              <button
                type="button"
                onClick={() => fileRef.current?.click()}
                disabled={!!image}
                className="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-violet-600 disabled:opacity-40 transition-colors"
                aria-label="Adicionar imagem"
              >
                <Image className="h-4 w-4" />
                <span className="hidden sm:inline">Imagem</span>
              </button>
            </div>

            <div className="flex items-center gap-3">
              {content.length > 0 && (
                <span className={`text-xs tabular-nums ${isOverLimit ? 'text-red-500 font-medium' : remaining <= 200 ? 'text-amber-500' : 'text-gray-400'}`}>
                  {remaining}
                </span>
              )}
              <button
                type="submit"
                disabled={isEmpty || isOverLimit || createPost.isPending}
                className="flex items-center gap-1.5 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 active:bg-violet-800 disabled:opacity-50 transition-colors shadow-sm"
              >
                {createPost.isPending && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                {createPost.isPending ? 'Publicando...' : 'Publicar'}
              </button>
            </div>
          </div>

          {createPost.isError && (
            <p className="mt-2 text-xs text-red-500">Erro ao publicar. Tente novamente.</p>
          )}
        </div>
      </div>
    </form>
  )
}
