import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, ChevronLeft, ChevronRight, EyeOff, Heart, Link2, MessageCircle, Pencil, Repeat2, Trash2, X } from 'lucide-react'
import marketplaceService from '@/services/marketplace.service'
import chatService from '@/services/chat.service'
import { useChatDock } from '@/components/chat/ChatDock'
import { Avatar } from '@/components/ui/Avatar'
import type { ListingDetail as Detail } from '@/types/marketplace.types'
import { cn } from '@/utils/cn'
import PersonLink from '../PersonLink'
import { Pill, Spinner, btnGhost, btnPrimary, errMsg, inputCls, timeAgo } from '../ui'
import { priceLabel } from './ListingCard'

interface Meta { categories: Record<string, string>; conditions: Record<string, string> }

/** Anúncio em tela grande, como no Marketplace do Facebook: galeria à esquerda; preço, vendedor e ações à direita. */
export default function ListingDetail({ slug, uuid, meta, onClose, onEdit }: { slug: string; uuid: string; meta: Meta; onClose: () => void; onEdit: (l: Detail) => void }) {
  const qc = useQueryClient()
  const dock = useChatDock()
  const key = ['ws', slug, 'marketplace', 'detail', uuid]
  const { data, isLoading, isError } = useQuery({ queryKey: key, queryFn: () => marketplaceService.get(slug, uuid) })
  const [idx, setIdx] = useState(0)
  const [reposting, setReposting] = useState(false)
  const [comment, setComment] = useState('')
  const [notice, setNotice] = useState('')
  const [chatSent, setChatSent] = useState(false)

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
      if (e.key === 'ArrowRight') setIdx((i) => Math.min(i + 1, (data?.images.length ?? 1) - 1))
      if (e.key === 'ArrowLeft') setIdx((i) => Math.max(i - 1, 0))
    }
    window.addEventListener('keydown', onKey)
    const prev = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => { window.removeEventListener('keydown', onKey); document.body.style.overflow = prev }
  }, [onClose, data?.images.length])

  const refresh = (l?: Detail) => {
    if (l) qc.setQueryData(key, l)
    qc.invalidateQueries({ queryKey: ['ws', slug, 'marketplace'], predicate: (q) => q.queryKey[3] !== 'detail' })
  }
  const like = useMutation({
    mutationFn: () => marketplaceService.like(slug, uuid),
    onSuccess: (r) => { qc.setQueryData<Detail>(key, (d) => d && { ...d, liked: r.liked, likes_count: r.likes_count }); refresh() },
  })
  const setStatus = useMutation({
    mutationFn: (status: 'active' | 'sold' | 'hidden') => marketplaceService.update(slug, uuid, { status }),
    onSuccess: refresh, onError: (e) => setNotice(errMsg(e)),
  })
  const remove = useMutation({ mutationFn: () => marketplaceService.remove(slug, uuid), onSuccess: () => { refresh(); onClose() }, onError: (e) => setNotice(errMsg(e)) })
  const repost = useMutation({
    mutationFn: () => marketplaceService.repost(slug, uuid, comment.trim() || undefined),
    onSuccess: (r) => { setReposting(false); setComment(''); setNotice('Anúncio repostado no mural!'); qc.setQueryData<Detail>(key, (d) => d && { ...d, reposts_count: r.reposts_count }); qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] }) },
    onError: (e) => setNotice(errMsg(e)),
  })
  const startChat = useMutation({
    mutationFn: () => chatService.sendRich(data!.seller!.uuid, { type: 'listing', metadata: { listing_uuid: uuid }, content: `Olá! Tenho interesse em "${data!.title}".` }),
    onSuccess: () => setChatSent(true), onError: (e) => setNotice(errMsg(e)),
  })

  const openChat = () => {
    if (!data?.seller) return
    dock.open({ kind: 'direct', uuid: data.seller.uuid, name: data.seller.name, avatar_url: data.seller.avatar_url })
    if (!chatSent && !startChat.isPending) startChat.mutate()
  }
  const copyLink = async () => {
    try { await navigator.clipboard.writeText(`${window.location.origin}/w/${slug}/marketplace?anuncio=${uuid}`); setNotice('Link copiado!') } catch { setNotice('Não foi possível copiar o link.') }
  }

  const imgs = data?.images ?? []
  const sold = data?.status === 'sold'

  return (
    <div className="fixed inset-0 z-[70] flex bg-black/70 sm:items-center sm:justify-center sm:p-6" onClick={onClose} role="dialog" aria-modal="true" aria-label={data?.title ?? 'Anúncio'}>
      <div className="relative flex h-full w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-[min(44rem,92vh)] sm:max-w-5xl sm:flex-row sm:rounded-2xl" onClick={(e) => e.stopPropagation()}>
        <button type="button" onClick={onClose} className="absolute right-3 top-3 z-10 rounded-full bg-white/90 p-2 text-gray-900 shadow hover:bg-white" aria-label="Fechar anúncio"><X className="h-5 w-5" /></button>

        {isLoading ? <div className="flex flex-1 items-center justify-center"><Spinner /></div> : isError || !data ? (
          <div className="flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center text-gray-500"><p>Este anúncio não está mais disponível.</p><button type="button" className={btnGhost} onClick={onClose}>Voltar</button></div>
        ) : (
          <>
            <div className="relative flex h-72 flex-shrink-0 flex-col bg-gray-950 sm:h-auto sm:flex-1">
              <div className="relative flex min-h-0 flex-1 items-center justify-center">
                {imgs[idx] && <img src={imgs[idx].url} alt={`${data.title} — foto ${idx + 1}`} className={cn('max-h-full max-w-full object-contain', sold && 'opacity-60')} />}
                {sold && <span className="absolute left-3 top-3 rounded-full bg-gray-900/85 px-3 py-1 text-sm font-semibold text-white">Vendido</span>}
                {idx > 0 && <button type="button" onClick={() => setIdx(idx - 1)} className="absolute left-3 rounded-full bg-white/85 p-2 text-gray-900 hover:bg-white" aria-label="Foto anterior"><ChevronLeft className="h-5 w-5" /></button>}
                {idx < imgs.length - 1 && <button type="button" onClick={() => setIdx(idx + 1)} className="absolute right-3 rounded-full bg-white/85 p-2 text-gray-900 hover:bg-white" aria-label="Próxima foto"><ChevronRight className="h-5 w-5" /></button>}
              </div>
              {imgs.length > 1 && (
                <div className="flex justify-center gap-1.5 overflow-x-auto bg-black/40 p-2">
                  {imgs.map((im, i) => (
                    <button key={im.uuid} type="button" onClick={() => setIdx(i)} aria-label={`Ver foto ${i + 1}`} aria-current={i === idx}
                      className={cn('h-12 w-12 flex-shrink-0 overflow-hidden rounded-lg ring-2', i === idx ? 'ring-brand-400' : 'ring-transparent opacity-70 hover:opacity-100')}>
                      <img src={im.url} alt="" className="h-full w-full object-cover" />
                    </button>
                  ))}
                </div>
              )}
            </div>

            <aside className="flex w-full flex-col overflow-y-auto p-5 sm:w-[24rem]">
              <p className="text-2xl font-bold text-gray-900">{priceLabel(data.price_cents)}</p>
              <h1 className="mt-1 text-lg font-semibold text-gray-800">{data.title}</h1>
              <p className="mt-0.5 text-xs text-gray-400">Publicado há {timeAgo(data.created_at)}{data.location ? ` · ${data.location}` : ''}{data.views_count != null ? ` · ${data.views_count} visualizações` : ''}</p>
              <div className="mt-3 flex flex-wrap gap-1.5">
                <Pill tone="brand">{meta.categories[data.category] ?? data.category}</Pill>
                <Pill tone="gray">{meta.conditions[data.condition] ?? data.condition}</Pill>
                {data.status === 'hidden' && <Pill tone="amber">Oculto pelo RH</Pill>}
              </div>

              <div className="mt-4 flex flex-wrap gap-2">
                {!data.is_mine && data.status === 'active' && data.seller && (
                  <button type="button" className={cn(btnPrimary, 'flex-1')} onClick={openChat} disabled={startChat.isPending}><MessageCircle className="h-4 w-4" /> {chatSent ? 'Abrir conversa' : 'Chamar no privado'}</button>
                )}
                <button type="button" className={btnGhost} onClick={() => like.mutate()} aria-pressed={data.liked} aria-label={data.liked ? 'Remover curtida' : 'Curtir'}>
                  <Heart className={cn('h-4 w-4', data.liked && 'fill-red-500 text-red-500')} /> {data.likes_count || ''}
                </button>
                {data.status === 'active' && <button type="button" className={btnGhost} onClick={() => setReposting((v) => !v)} aria-expanded={reposting}><Repeat2 className="h-4 w-4" /> {data.reposts_count || 'Repostar'}</button>}
                <button type="button" className={btnGhost} onClick={copyLink} aria-label="Copiar link do anúncio"><Link2 className="h-4 w-4" /></button>
              </div>

              {reposting && (
                <div className="mt-3 space-y-2 rounded-xl bg-gray-50 p-3">
                  <textarea rows={2} className={inputCls} maxLength={500} placeholder="Diga algo sobre o anúncio (opcional)" value={comment} onChange={(e) => setComment(e.target.value)} />
                  <div className="flex justify-end gap-2">
                    <button type="button" className={btnGhost} onClick={() => setReposting(false)}>Cancelar</button>
                    <button type="button" className={btnPrimary} disabled={repost.isPending} onClick={() => repost.mutate()}>Repostar no mural</button>
                  </div>
                </div>
              )}
              {notice && <p className="mt-2 flex items-center gap-1 text-sm text-emerald-700" role="status"><Check className="h-4 w-4" /> {notice}</p>}

              <h2 className="mt-5 text-sm font-semibold text-gray-900">Descrição</h2>
              <p className="mt-1 whitespace-pre-wrap break-words text-sm text-gray-700">{data.description}</p>

              {data.seller && (
                <div className="mt-5 flex items-center gap-3 rounded-xl border border-gray-100 p-3">
                  <PersonLink person={data.seller}><Avatar src={data.seller.avatar_url} name={data.seller.name} size="lg" /></PersonLink>
                  <div className="min-w-0"><p className="text-xs text-gray-400">Vendedor(a)</p><PersonLink person={data.seller} className="block truncate font-semibold text-gray-900" /></div>
                </div>
              )}

              {(data.can_edit || data.can_moderate) && (
                <div className="mt-5 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                  {data.can_edit && <button type="button" className={btnGhost} onClick={() => onEdit(data)}><Pencil className="h-4 w-4" /> Editar</button>}
                  {data.can_edit && <button type="button" className={btnGhost} disabled={setStatus.isPending} onClick={() => setStatus.mutate(sold ? 'active' : 'sold')}><Check className="h-4 w-4" /> {sold ? 'Voltar a vender' : 'Marcar como vendido'}</button>}
                  {!data.can_edit && data.can_moderate && <button type="button" className={btnGhost} disabled={setStatus.isPending} onClick={() => setStatus.mutate(data.status === 'hidden' ? 'active' : 'hidden')}><EyeOff className="h-4 w-4" /> {data.status === 'hidden' ? 'Reexibir' : 'Ocultar'}</button>}
                  <button type="button" className="inline-flex items-center gap-1 px-2 text-sm text-gray-400 hover:text-red-500" onClick={() => { if (confirm('Remover este anúncio?')) remove.mutate() }}><Trash2 className="h-4 w-4" /> Remover</button>
                </div>
              )}
            </aside>
          </>
        )}
      </div>
    </div>
  )
}
