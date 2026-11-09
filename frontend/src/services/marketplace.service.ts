import api from './api'
import type { ApiResponse } from '@/types/api.types'
import type { ListingDetail, ListingInput, MarketplaceFilters, MarketplacePage } from '@/types/marketplace.types'

const base = (slug: string) => `/workspaces/${slug}/marketplace`
const data = <T>(p: Promise<{ data: ApiResponse<T> }>) => p.then((r) => r.data.data)

/** Marketplace interno do Workspace. */
const marketplaceService = {
  list: (slug: string, f: MarketplaceFilters & { page?: number }) =>
    data<MarketplacePage>(api.get(base(slug), { params: Object.fromEntries(Object.entries(f).filter(([, v]) => v !== undefined && v !== '')) })),
  get: (slug: string, uuid: string) => data<ListingDetail>(api.get(`${base(slug)}/${uuid}`)),

  create: (slug: string, input: ListingInput, images: File[]) => {
    const fd = new FormData()
    fd.append('title', input.title.trim())
    fd.append('description', input.description.trim())
    if (input.price.trim()) fd.append('price', input.price.replace(/\./g, '').replace(',', '.'))
    fd.append('category', input.category)
    fd.append('condition', input.condition)
    if (input.location.trim()) fd.append('location', input.location.trim())
    images.forEach((f) => fd.append('images[]', f))
    return data<ListingDetail>(api.post(base(slug), fd, { headers: { 'Content-Type': 'multipart/form-data' } }))
  },
  update: (slug: string, uuid: string, body: Partial<Omit<ListingInput, 'price'>> & { price?: number | null; status?: 'active' | 'sold' | 'hidden' }) =>
    data<ListingDetail>(api.patch(`${base(slug)}/${uuid}`, body)),
  remove: (slug: string, uuid: string) => api.delete(`${base(slug)}/${uuid}`),
  addImages: (slug: string, uuid: string, files: File[]) => {
    const fd = new FormData()
    files.forEach((f) => fd.append('images[]', f))
    return data<ListingDetail>(api.post(`${base(slug)}/${uuid}/images`, fd, { headers: { 'Content-Type': 'multipart/form-data' } }))
  },
  removeImage: (slug: string, uuid: string, img: string) => data<ListingDetail>(api.delete(`${base(slug)}/${uuid}/images/${img}`)),
  like: (slug: string, uuid: string) => data<{ liked: boolean; likes_count: number }>(api.post(`${base(slug)}/${uuid}/like`)),
  repost: (slug: string, uuid: string, comment?: string) => data<{ post_uuid: string; reposts_count: number }>(api.post(`${base(slug)}/${uuid}/repost`, { comment })),
}

export default marketplaceService
