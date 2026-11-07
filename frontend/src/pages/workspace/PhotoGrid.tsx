import { useEffect, useState } from 'react'
import { ChevronLeft, ChevronRight, X } from 'lucide-react'
import type { FeedMedia } from '@/types/workspace.types'
import { cn } from '@/utils/cn'

/** Galeria de fotos da publicação: 1 grande, 2 lado a lado, 3+ em grade; clique abre ampliado. */
export default function PhotoGrid({ media }: { media: FeedMedia[] }) {
  const [open, setOpen] = useState<number | null>(null)

  useEffect(() => {
    if (open === null) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(null)
      if (e.key === 'ArrowRight') setOpen((i) => (i === null ? i : (i + 1) % media.length))
      if (e.key === 'ArrowLeft') setOpen((i) => (i === null ? i : (i - 1 + media.length) % media.length))
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open, media.length])

  if (!media.length) return null
  const shown = media.slice(0, 4)
  const extra = media.length - shown.length

  return (
    <>
      <div className={cn('mt-3 grid gap-1 overflow-hidden rounded-2xl', shown.length === 1 ? 'grid-cols-1' : 'grid-cols-2')}>
        {shown.map((m, i) => (
          <button
            key={m.uuid}
            type="button"
            onClick={() => setOpen(i)}
            className={cn('relative block overflow-hidden bg-gray-100', shown.length === 1 ? 'max-h-[28rem]' : 'aspect-square', shown.length === 3 && i === 0 && 'row-span-2 aspect-auto')}
            aria-label={`Ampliar foto ${i + 1} de ${media.length}`}
          >
            <img src={m.url} alt="" loading="lazy" className="h-full w-full object-cover" />
            {extra > 0 && i === shown.length - 1 && (
              <span className="absolute inset-0 flex items-center justify-center bg-black/50 text-2xl font-semibold text-white">+{extra}</span>
            )}
          </button>
        ))}
      </div>

      {open !== null && (
        <div className="fixed inset-0 z-[80] flex items-center justify-center bg-black/90 p-4" role="dialog" aria-modal="true" aria-label="Foto ampliada" onClick={() => setOpen(null)}>
          <img src={media[open].url} alt="" className="max-h-full max-w-full rounded-lg object-contain" onClick={(e) => e.stopPropagation()} />
          <button type="button" className="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Fechar" onClick={() => setOpen(null)}><X className="h-5 w-5" /></button>
          {media.length > 1 && (
            <>
              <button type="button" className="absolute left-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Foto anterior" onClick={(e) => { e.stopPropagation(); setOpen((open - 1 + media.length) % media.length) }}><ChevronLeft className="h-6 w-6" /></button>
              <button type="button" className="absolute right-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Próxima foto" onClick={(e) => { e.stopPropagation(); setOpen((open + 1) % media.length) }}><ChevronRight className="h-6 w-6" /></button>
              <span className="absolute bottom-4 rounded-full bg-black/60 px-3 py-1 text-xs text-white">{open + 1} / {media.length}</span>
            </>
          )}
        </div>
      )}
    </>
  )
}
