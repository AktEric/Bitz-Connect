import { ImageOff } from 'lucide-react'
import { cn } from '@/utils/cn'
import { useAuthBlob } from './useAuthBlob'

/** Imagem de uma figurinha própria (entregue só a quem tem a figurinha ou a recebeu). */
export default function StickerImage({ uuid, className, alt = 'Figurinha' }: { uuid: string; className?: string; alt?: string }) {
  const { url, failed } = useAuthBlob(`/stickers/${uuid}/image`)
  if (failed) return <span className={cn('flex items-center justify-center rounded-2xl bg-gray-100 text-gray-300', className)}><ImageOff className="h-6 w-6" /></span>

  return url
    ? <img src={url} alt={alt} className={cn('object-contain', className)} draggable={false} />
    : <span className={cn('animate-pulse rounded-2xl bg-gray-100', className)} aria-hidden />
}
