import { useEffect, useRef, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ImagePlus, X } from 'lucide-react'
import marketplaceService from '@/services/marketplace.service'
import type { ListingDetail, ListingInput } from '@/types/marketplace.types'
import { Modal, btnGhost, btnPrimary, errMsg, inputCls, labelCls } from '../ui'

const MAX_MB = 5

interface Meta { categories: Record<string, string>; conditions: Record<string, string>; maxImages: number }

/** Criar ou editar anúncio: fotos com prévia, título, preço, categoria, condição e descrição. */
export default function ListingForm({ slug, meta, listing, onClose, onSaved }: { slug: string; meta: Meta; listing?: ListingDetail; onClose: () => void; onSaved: (l: ListingDetail) => void }) {
  const qc = useQueryClient()
  const fileRef = useRef<HTMLInputElement>(null)
  const [f, setF] = useState<ListingInput>({
    title: listing?.title ?? '', description: listing?.description ?? '', category: listing?.category ?? '', condition: listing?.condition ?? 'used',
    location: listing?.location ?? '', price: listing?.price_cents != null ? (listing.price_cents / 100).toFixed(2).replace('.', ',') : '',
  })
  const [files, setFiles] = useState<{ file: File; url: string }[]>([])
  const [current, setCurrent] = useState(listing?.images ?? [])
  const [error, setError] = useState('')
  const set = <K extends keyof ListingInput>(k: K, v: ListingInput[K]) => setF((x) => ({ ...x, [k]: v }))
  const room = meta.maxImages - current.length - files.length

  useEffect(() => () => files.forEach((x) => URL.revokeObjectURL(x.url)), [files])

  const pick = (list: FileList | null) => {
    if (!list) return
    const ok: { file: File; url: string }[] = []
    for (const file of Array.from(list).slice(0, Math.max(0, room))) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { setError('Use fotos JPG, PNG ou WebP.'); continue }
      if (file.size > MAX_MB * 1024 * 1024) { setError(`Cada foto pode ter até ${MAX_MB} MB.`); continue }
      ok.push({ file, url: URL.createObjectURL(file) })
    }
    if (ok.length) { setError(''); setFiles((x) => [...x, ...ok]) }
  }

  const done = (l: ListingDetail) => { qc.invalidateQueries({ queryKey: ['ws', slug, 'marketplace'] }); onSaved(l) }
  const save = useMutation({
    mutationFn: async () => {
      if (!listing) return marketplaceService.create(slug, f, files.map((x) => x.file))
      const price = f.price.trim() ? Number(f.price.replace(/\./g, '').replace(',', '.')) : null
      let l = await marketplaceService.update(slug, listing.uuid, { title: f.title.trim(), description: f.description.trim(), category: f.category, condition: f.condition, location: f.location.trim(), price })
      if (files.length) l = await marketplaceService.addImages(slug, listing.uuid, files.map((x) => x.file))
      return l
    },
    onSuccess: done, onError: (e) => setError(errMsg(e)),
  })
  const dropImage = useMutation({
    mutationFn: (uuid: string) => marketplaceService.removeImage(slug, listing!.uuid, uuid),
    onSuccess: (l) => setCurrent(l.images), onError: (e) => setError(errMsg(e)),
  })

  const priceBad = f.price.trim() !== '' && Number.isNaN(Number(f.price.replace(/\./g, '').replace(',', '.')))
  const valid = f.title.trim().length >= 3 && f.description.trim().length >= 5 && !!f.category && !priceBad && (current.length + files.length >= 1)

  return (
    <Modal title={listing ? 'Editar anúncio' : 'Novo anúncio'} onClose={onClose}>
      <div className="space-y-4">
        <div>
          <span className={labelCls}>Fotos ({current.length + files.length}/{meta.maxImages})</span>
          <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
            {current.map((img) => (
              <div key={img.uuid} className="relative aspect-square overflow-hidden rounded-xl bg-gray-100">
                <img src={img.url} alt="" className="h-full w-full object-cover" />
                {current.length + files.length > 1 && (
                  <button type="button" onClick={() => dropImage.mutate(img.uuid)} className="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white hover:bg-black/80" aria-label="Remover foto"><X className="h-3.5 w-3.5" /></button>
                )}
              </div>
            ))}
            {files.map((x, i) => (
              <div key={x.url} className="relative aspect-square overflow-hidden rounded-xl bg-gray-100">
                <img src={x.url} alt="" className="h-full w-full object-cover" />
                <button type="button" onClick={() => setFiles((all) => all.filter((_, k) => k !== i))} className="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white hover:bg-black/80" aria-label="Remover foto"><X className="h-3.5 w-3.5" /></button>
                {i === 0 && !current.length && <span className="absolute bottom-1 left-1 rounded bg-brand-600 px-1.5 text-[10px] font-semibold text-white">Capa</span>}
              </div>
            ))}
            {room > 0 && (
              <button type="button" onClick={() => fileRef.current?.click()} className="flex aspect-square flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-gray-300 text-xs text-gray-500 hover:border-brand-400 hover:text-brand-600">
                <ImagePlus className="h-6 w-6" /> Adicionar
              </button>
            )}
          </div>
          <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" multiple className="hidden" onChange={(e) => { pick(e.target.files); e.target.value = '' }} />
        </div>

        <div><label className={labelCls} htmlFor="l-title">Título</label><input id="l-title" className={inputCls} maxLength={120} value={f.title} onChange={(e) => set('title', e.target.value)} placeholder="O que você está vendendo?" /></div>
        <div className="grid grid-cols-2 gap-3">
          <div><label className={labelCls} htmlFor="l-price">Preço (R$) — vazio = a combinar</label><input id="l-price" inputMode="decimal" className={inputCls} value={f.price} onChange={(e) => set('price', e.target.value)} placeholder="0,00" aria-invalid={priceBad} /></div>
          <div><label className={labelCls} htmlFor="l-cat">Categoria</label>
            <select id="l-cat" className={inputCls} value={f.category} onChange={(e) => set('category', e.target.value)}>
              <option value="">Selecione…</option>{Object.entries(meta.categories).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select></div>
          <div><label className={labelCls} htmlFor="l-cond">Condição</label>
            <select id="l-cond" className={inputCls} value={f.condition} onChange={(e) => set('condition', e.target.value)}>
              {Object.entries(meta.conditions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select></div>
          <div><label className={labelCls} htmlFor="l-loc">Onde retirar (opcional)</label><input id="l-loc" className={inputCls} maxLength={80} value={f.location} onChange={(e) => set('location', e.target.value)} placeholder="Ex.: Recepção, 2º andar" /></div>
        </div>
        <div><label className={labelCls} htmlFor="l-desc">Descrição</label><textarea id="l-desc" rows={4} className={inputCls} maxLength={3000} value={f.description} onChange={(e) => set('description', e.target.value)} placeholder="Estado, tempo de uso, motivo da venda…" /></div>

        <p className="text-xs text-gray-400">Só os colegas da sua empresa veem este anúncio. A combinação de pagamento e entrega é entre vocês.</p>
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>Cancelar</button>
          <button type="button" className={btnPrimary} disabled={!valid || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Salvando…' : listing ? 'Salvar' : 'Publicar anúncio'}</button>
        </div>
      </div>
    </Modal>
  )
}
