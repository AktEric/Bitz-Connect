import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Heart, Package, Plus, Search, SlidersHorizontal, Store, Tag, X } from 'lucide-react'
import marketplaceService from '@/services/marketplace.service'
import type { Listing, ListingDetail as Detail, MarketplaceFilters, MarketplacePage } from '@/types/marketplace.types'
import { cn } from '@/utils/cn'
import { useCan } from './permissions'
import { Empty, PageHeader, Spinner, btnGhost, btnPrimary, inputCls, labelCls, useWs } from './ui'
import ListingCard from './marketplace/ListingCard'
import ListingDetail from './marketplace/ListingDetail'
import ListingForm from './marketplace/ListingForm'

const SORTS: [NonNullable<MarketplaceFilters['sort']>, string][] = [['recent', 'Mais recentes'], ['price_asc', 'Menor preço'], ['price_desc', 'Maior preço'], ['popular', 'Mais curtidos']]

/** Marketplace da empresa: colaboradores anunciam, filtram, curtem, repostam e chamam no privado. */
export default function WorkspaceMarketplacePage() {
  const { slug } = useWs()
  const qc = useQueryClient()
  const canSell = useCan('marketplace.sell')
  const [params, setParams] = useSearchParams()
  const [form, setForm] = useState<{ listing?: Detail } | null>(null)
  const [drawer, setDrawer] = useState(false)

  const filters: MarketplaceFilters = useMemo(() => ({
    q: params.get('q') ?? '', category: params.get('categoria') ?? '', condition: params.get('condicao') ?? '',
    min: params.get('min') ?? '', max: params.get('max') ?? '',
    sort: (params.get('ordem') as MarketplaceFilters['sort']) ?? 'recent', scope: (params.get('aba') as MarketplaceFilters['scope']) ?? 'all',
  }), [params])
  const openUuid = params.get('anuncio')
  const [search, setSearch] = useState(filters.q ?? '')

  const set = (patch: Record<string, string | null>) => setParams((p) => {
    const n = new URLSearchParams(p)
    Object.entries(patch).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k)))
    return n
  }, { replace: true })

  // busca com atraso, para não consultar a cada tecla
  useEffect(() => { const t = setTimeout(() => { if (search !== (filters.q ?? '')) set({ q: search.trim() || null }) }, 350); return () => clearTimeout(t) /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [search])

  const list = useInfiniteQuery({
    queryKey: ['ws', slug, 'marketplace', 'list', filters],
    queryFn: ({ pageParam }) => marketplaceService.list(slug, { ...filters, page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: (last: MarketplacePage) => (last.page < last.last_page ? last.page + 1 : undefined),
  })
  const pages = list.data?.pages ?? []
  const meta = pages[0] ? { categories: pages[0].categories, conditions: pages[0].conditions, maxImages: pages[0].max_images } : null
  const items = pages.flatMap((p) => p.items)

  const like = useMutation({
    mutationFn: (l: Listing) => marketplaceService.like(slug, l.uuid),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['ws', slug, 'marketplace'] }),
  })

  const activeFilters = [filters.category, filters.condition, filters.min, filters.max].filter(Boolean).length
  const clear = () => { setSearch(''); set({ q: null, categoria: null, condicao: null, min: null, max: null }) }

  const Filters = (
    <div className="space-y-5">
      <div>
        <h2 className="mb-2 text-sm font-semibold text-gray-900">Categorias</h2>
        <ul className="space-y-0.5">
          <li><button type="button" onClick={() => set({ categoria: null })} className={cn('w-full rounded-lg px-3 py-1.5 text-left text-sm', !filters.category ? 'bg-brand-50 font-semibold text-brand-700' : 'text-gray-700 hover:bg-gray-100')}>Todas</button></li>
          {meta && Object.entries(meta.categories).map(([k, v]) => (
            <li key={k}><button type="button" onClick={() => set({ categoria: k })} aria-current={filters.category === k} className={cn('w-full rounded-lg px-3 py-1.5 text-left text-sm', filters.category === k ? 'bg-brand-50 font-semibold text-brand-700' : 'text-gray-700 hover:bg-gray-100')}>{v}</button></li>
          ))}
        </ul>
      </div>
      <div>
        <label className={labelCls} htmlFor="mk-cond">Condição</label>
        <select id="mk-cond" className={inputCls} value={filters.condition} onChange={(e) => set({ condicao: e.target.value || null })}>
          <option value="">Qualquer</option>{meta && Object.entries(meta.conditions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
      </div>
      <div>
        <span className={labelCls}>Preço (R$)</span>
        <div className="flex items-center gap-2">
          <input aria-label="Preço mínimo" inputMode="numeric" className={inputCls} placeholder="Mín." defaultValue={filters.min} onBlur={(e) => set({ min: e.target.value.replace(/\D/g, '') || null })} />
          <span className="text-gray-400">–</span>
          <input aria-label="Preço máximo" inputMode="numeric" className={inputCls} placeholder="Máx." defaultValue={filters.max} onBlur={(e) => set({ max: e.target.value.replace(/\D/g, '') || null })} />
        </div>
      </div>
      {(activeFilters > 0 || filters.q) && <button type="button" onClick={clear} className="text-sm font-medium text-brand-600 hover:underline">Limpar filtros</button>}
    </div>
  )

  return (
    <div>
      <PageHeader
        title="Marketplace"
        subtitle="Compre, venda e troque com os colegas da empresa."
        actions={canSell ? <button type="button" className={btnPrimary} onClick={() => setForm({})} disabled={!meta}><Plus size={16} /> Anunciar</button> : undefined}
      />

      <div className="mb-4 flex flex-wrap items-center gap-2">
        <div role="tablist" aria-label="Anúncios" className="inline-flex rounded-xl bg-gray-100 p-1">
          {([['all', 'Todos', Store], ['mine', 'Meus anúncios', Package], ['liked', 'Favoritos', Heart]] as const).map(([v, label, Icon]) => (
            <button key={v} type="button" role="tab" aria-selected={filters.scope === v} onClick={() => set({ aba: v === 'all' ? null : v })}
              className={cn('inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium', filters.scope === v ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700')}>
              <Icon className="h-3.5 w-3.5" /> {label}
            </button>
          ))}
        </div>
        <div className="relative min-w-[12rem] flex-1 sm:max-w-sm">
          <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
          <input type="search" aria-label="Buscar no marketplace" className={cn(inputCls, 'pl-9')} placeholder="Buscar anúncios" value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
        <select aria-label="Ordenar" className={cn(inputCls, 'w-auto')} value={filters.sort} onChange={(e) => set({ ordem: e.target.value === 'recent' ? null : e.target.value })}>
          {SORTS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
        <button type="button" className={cn(btnGhost, 'lg:hidden')} onClick={() => setDrawer(true)}><SlidersHorizontal className="h-4 w-4" /> Filtros{activeFilters ? ` (${activeFilters})` : ''}</button>
      </div>

      <div className="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <aside className="ws-card hidden h-fit rounded-2xl bg-white p-4 lg:sticky lg:top-20 lg:block" aria-label="Filtros">{Filters}</aside>

        <section aria-label="Anúncios">
          {list.isLoading ? <Spinner /> : !items.length ? (
            <Empty text={filters.scope === 'mine' ? 'Você ainda não anunciou nada. Que tal vender aquilo que está parado em casa?' : filters.scope === 'liked' ? 'Você ainda não curtiu nenhum anúncio.' : filters.q || activeFilters ? 'Nenhum anúncio encontrado com esses filtros.' : 'Nenhum anúncio por enquanto. Seja o primeiro a anunciar!'} />
          ) : (
            <>
              <p className="mb-3 flex items-center gap-1.5 text-sm text-gray-500"><Tag className="h-3.5 w-3.5" /> {pages[0]?.total} {pages[0]?.total === 1 ? 'anúncio' : 'anúncios'}</p>
              <div className="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 xl:grid-cols-4">
                {items.map((l) => <ListingCard key={l.uuid} item={l} onOpen={() => set({ anuncio: l.uuid })} onLike={() => like.mutate(l)} />)}
              </div>
              {list.hasNextPage && <div className="mt-6 flex justify-center"><button type="button" className={btnGhost} onClick={() => list.fetchNextPage()} disabled={list.isFetchingNextPage}>{list.isFetchingNextPage ? 'Carregando…' : 'Ver mais'}</button></div>}
            </>
          )}
        </section>
      </div>

      {drawer && (
        <div className="fixed inset-0 z-[70] bg-black/40 lg:hidden" onClick={() => setDrawer(false)}>
          <div role="dialog" aria-modal="true" aria-label="Filtros" className="absolute inset-y-0 left-0 w-[85%] max-w-xs overflow-y-auto bg-white p-5 shadow-xl" onClick={(e) => e.stopPropagation()}>
            <div className="mb-4 flex items-center justify-between"><h2 className="text-lg font-semibold">Filtros</h2><button type="button" onClick={() => setDrawer(false)} className="rounded-lg p-1.5 hover:bg-gray-100" aria-label="Fechar filtros"><X className="h-5 w-5" /></button></div>
            {Filters}
            <button type="button" className={cn(btnPrimary, 'mt-6 w-full')} onClick={() => setDrawer(false)}>Ver resultados</button>
          </div>
        </div>
      )}

      {openUuid && meta && !form && (
        <ListingDetail slug={slug} uuid={openUuid} meta={meta} onClose={() => set({ anuncio: null })} onEdit={(l) => setForm({ listing: l })} />
      )}
      {form && meta && (
        <ListingForm slug={slug} meta={meta} listing={form.listing} onClose={() => setForm(null)}
          onSaved={(l) => { setForm(null); qc.setQueryData(['ws', slug, 'marketplace', 'detail', l.uuid], l); set({ anuncio: l.uuid }) }} />
      )}
    </div>
  )
}
