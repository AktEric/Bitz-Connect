import { Heart, ImageOff, MapPin } from 'lucide-react'
import type { Listing } from '@/types/marketplace.types'
import { cn } from '@/utils/cn'
import { fmtMoney, timeAgo } from '../ui'

export const priceLabel = (cents: number | null) => (cents == null ? 'A combinar' : cents === 0 ? 'Grátis' : fmtMoney(cents))

/** Cartão da grade, no estilo do Marketplace do Facebook: foto quadrada, preço em destaque e coração. */
export default function ListingCard({ item, onOpen, onLike }: { item: Listing; onOpen: () => void; onLike: () => void }) {
  const sold = item.status === 'sold'

  return (
    <article className="group relative">
      <button type="button" onClick={onOpen} className="block w-full text-left" aria-label={`Abrir anúncio: ${item.title}`}>
        <div className="relative aspect-square overflow-hidden rounded-2xl bg-gray-100">
          {item.cover_url
            ? <img src={item.cover_url} alt="" loading="lazy" className={cn('h-full w-full object-cover transition duration-300 group-hover:scale-105', sold && 'opacity-60 grayscale')} />
            : <div className="flex h-full w-full items-center justify-center text-gray-300"><ImageOff className="h-10 w-10" /></div>}
          {sold && <span className="absolute left-2 top-2 rounded-full bg-gray-900/80 px-2.5 py-1 text-xs font-semibold text-white">Vendido</span>}
          {item.status === 'hidden' && <span className="absolute left-2 top-2 rounded-full bg-amber-500 px-2.5 py-1 text-xs font-semibold text-white">Oculto</span>}
          {item.images_count > 1 && <span className="absolute bottom-2 left-2 rounded-full bg-black/55 px-2 py-0.5 text-[11px] font-medium text-white">{item.images_count} fotos</span>}
        </div>
        <div className="px-0.5 pt-2">
          <p className="text-base font-bold text-gray-900">{priceLabel(item.price_cents)}</p>
          <p className="truncate text-sm text-gray-700">{item.title}</p>
          <p className="mt-0.5 flex items-center gap-1 truncate text-xs text-gray-400">
            {item.location && <><MapPin className="h-3 w-3 flex-shrink-0" /> {item.location} ·</>} {item.is_mine ? 'Você' : item.seller?.name.split(' ')[0]} · {timeAgo(item.created_at)}
          </p>
        </div>
      </button>
      <button
        type="button" onClick={onLike} aria-pressed={item.liked} aria-label={item.liked ? 'Remover dos favoritos' : 'Curtir anúncio'}
        className="absolute right-2 top-2 flex items-center gap-1 rounded-full bg-white/90 px-2 py-1 text-xs font-medium text-gray-700 shadow hover:bg-white"
      >
        <Heart className={cn('h-4 w-4', item.liked ? 'fill-red-500 text-red-500' : 'text-gray-500')} />
        {item.likes_count > 0 && item.likes_count}
      </button>
    </article>
  )
}
