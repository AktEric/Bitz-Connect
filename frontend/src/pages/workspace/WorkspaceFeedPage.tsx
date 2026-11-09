import { useCan } from './permissions'
import { useRef, useState } from 'react'
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { BadgeCheck, Cake, ImagePlus, MessageCircle, Pin, Trash2, Video, X } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { FeedComment, FeedPost } from '@/types/workspace.types'
import { Empty, Modal, PageHeader, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, fmtDate, fmtMoney, timeAgo, useWs } from './ui'
import { Link, useParams } from 'react-router-dom'
import { MentionField, MentionText, applyMentions, type Mention } from './MentionField'
import PhotoGrid from './PhotoGrid'
import KudosModal, { BADGES } from './KudosModal'
import { PollBlock, PollModal } from './PollParts'
import ReactionBar from './ReactionBar'
import PersonLink, { PersonAvatarLink } from './PersonLink'
import { resizeImage } from './imageUtils'

const MAX_PHOTOS = 6

/** Anúncio do marketplace repostado no mural. */
function ListingEmbed({ listing }: { listing: NonNullable<FeedPost['listing']> }) {
  const { slug } = useParams()
  return (
    <Link to={`/w/${slug}/marketplace?anuncio=${listing.uuid}`} className="mt-3 flex overflow-hidden rounded-2xl border border-gray-100 bg-white hover:shadow-md">
      <div className="h-28 w-28 flex-shrink-0 bg-gray-100">{listing.cover_url && <img src={listing.cover_url} alt="" loading="lazy" className="h-full w-full object-cover" />}</div>
      <div className="min-w-0 flex-1 p-3">
        <p className="text-lg font-bold text-gray-900">{listing.price_cents == null ? 'A combinar' : listing.price_cents === 0 ? 'Grátis' : fmtMoney(listing.price_cents)}{listing.status === 'sold' && <span className="ml-2 text-xs font-semibold text-gray-500">Vendido</span>}</p>
        <p className="truncate text-sm text-gray-800">{listing.title}</p>
        <p className="mt-1 truncate text-xs text-gray-400">Marketplace · {listing.seller?.name}</p>
      </div>
    </Link>
  )
}

/** Cartão com a foto da pessoa homenageada (aniversário) ou recém-chegada (boas-vindas); a foto é sempre a atual do perfil. */
function SubjectCard({ post }: { post: FeedPost }) {
  if ((post.kind !== 'birthday' && post.kind !== 'welcome') || !post.subject) return null
  const birthday = post.kind === 'birthday'
  const first = post.subject.name?.split(' ')[0]

  return (
    <div className="mt-3 flex flex-col items-center gap-2 rounded-2xl bg-white/80 py-5 text-center shadow-sm ring-1 ring-black/5">
      {birthday ? <Cake className="h-5 w-5 text-pink-500" /> : <span className="text-xl" aria-hidden>👋</span>}
      <PersonAvatarLink person={post.subject} size="2xl" />
      <PersonLink person={post.subject} className="text-lg font-semibold text-gray-900">{birthday ? `Feliz aniversário, ${first}!` : `Boas-vindas, ${first}!`}</PersonLink>
      <p className="text-xs text-gray-500">{[post.subject.job_title, birthday ? null : post.department].filter(Boolean).join(' · ')}</p>
    </div>
  )
}

function Comments({ slug, post, expanded, onExpand }: { slug: string; post: FeedPost; expanded: boolean; onExpand: () => void }) {
  const qc = useQueryClient()
  const [text, setText] = useState('')
  const [mentions, setMentions] = useState<Mention[]>([])
  const key = ['ws', slug, 'comments', post.uuid]
  const { data: full, isLoading } = useQuery({ queryKey: key, queryFn: () => workspaceService.comments(slug, post.uuid), enabled: expanded })
  const data = expanded ? full : post.comments_preview
  const add = useMutation({
    mutationFn: () => workspaceService.addComment(slug, post.uuid, applyMentions(text.trim(), mentions)),
    onSuccess: () => { setText(''); setMentions([]); qc.invalidateQueries({ queryKey: key }); qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] }) },
  })
  const send = () => { if (text.trim() && !add.isPending) add.mutate() }

  return (
    <div className="mt-3 border-t border-gray-100 pt-3">
      {!expanded && post.comments_count > post.comments_preview.length && (
        <button type="button" onClick={onExpand} className="mb-2 text-xs font-medium text-gray-500 hover:text-gray-700">Ver todos os {post.comments_count} comentários</button>
      )}
      {expanded && isLoading ? <p className="text-xs text-gray-400">Carregando…</p> : (
        <ul className="mb-3 space-y-2">
          {data?.map((c: FeedComment) => (
            <li key={c.uuid} className="flex gap-2">
              <PersonAvatarLink person={c.author} size="xs" />
              <div className="rounded-xl bg-gray-50 px-3 py-1.5 text-sm">
                <PersonLink person={c.author} className="font-medium text-gray-900" />
                <MentionText text={c.body} names={c.mentions} className="whitespace-pre-wrap break-words text-gray-600" />
              </div>
            </li>
          ))}
        </ul>
      )}
      <div className="flex items-start gap-2">
        <div className="min-w-0 flex-1">
          <MentionField
            slug={slug} value={text} onChange={setText} mentions={mentions} onMentions={setMentions}
            rows={1} maxLength={1000} placeholder="Escreva um comentário… use @ para mencionar" onEnter={send}
          />
        </div>
        <button type="button" className={btnPrimary} disabled={!text.trim() || add.isPending} onClick={send}>Enviar</button>
      </div>
    </div>
  )
}

function AckPanelModal({ slug, post, onClose }: { slug: string; post: FeedPost; onClose: () => void }) {
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'ack-panel', post.uuid], queryFn: () => workspaceService.ackPanel(slug, post.uuid) })
  return (
    <Modal title="Confirmações de leitura" onClose={onClose}>
      {isLoading || !data ? <Spinner /> : (
        <div className="space-y-4 text-sm">
          <p className="text-gray-600"><b className="text-gray-900">{data.readers.length}</b> de {data.total} confirmaram.</p>
          <div>
            <h3 className="mb-1 font-semibold text-amber-700">Faltam ({data.pending.length})</h3>
            {data.pending.length === 0 ? <p className="text-gray-400">Todos confirmaram.</p> : (
              <ul className="divide-y divide-gray-50">{data.pending.map((p) => (
                <li key={p.uuid} className="flex items-center gap-2 py-1.5"><PersonAvatarLink person={p} size="xs" /> <PersonLink person={p} className="text-gray-900" /> <span className="text-xs text-gray-400">{p.department ?? p.job_title}</span></li>
              ))}</ul>
            )}
          </div>
          <div>
            <h3 className="mb-1 font-semibold text-emerald-700">Leram ({data.readers.length})</h3>
            <ul className="divide-y divide-gray-50">{data.readers.map((r) => (
              <li key={r.person.uuid} className="flex items-center gap-2 py-1.5"><PersonAvatarLink person={r.person} size="xs" /> <PersonLink person={r.person} className="text-gray-900" /> <span className="ml-auto text-xs text-gray-400">{fmtDate(r.acked_at)}</span></li>
            ))}</ul>
          </div>
        </div>
      )}
    </Modal>
  )
}

function PostCard({ slug, post, isAdmin }: { slug: string; post: FeedPost; isAdmin: boolean }) {
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)
  const [panel, setPanel] = useState(false)
  const refresh = () => { void qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] }); void qc.invalidateQueries({ queryKey: ['workspace', slug] }) }
  const ack = useMutation({ mutationFn: () => workspaceService.ackPost(slug, post.uuid), onSuccess: refresh })
  const pin = useMutation({ mutationFn: () => workspaceService.pinPost(slug, post.uuid), onSuccess: refresh })
  const del = useMutation({ mutationFn: () => workspaceService.deletePost(slug, post.uuid), onSuccess: refresh })

  return (
    <Panel className={post.kind === 'birthday' ? 'border-pink-100 bg-gradient-to-br from-pink-50 via-white to-amber-50' : post.kind === 'welcome' ? 'border-brand-100 bg-gradient-to-br from-brand-50 via-white to-emerald-50' : post.kind === 'kudos' ? 'border-amber-100 bg-gradient-to-br from-amber-50 via-white to-brand-50' : undefined}>
      {post.kind === 'kudos' && post.subject && (
        <div className="mb-3 flex items-center gap-3 rounded-2xl bg-white/70 p-3">
          <span className="text-3xl" aria-hidden>{BADGES[post.badge ?? '']?.emoji ?? '⭐'}</span>
          <div className="min-w-0">
            <p className="text-sm text-gray-900"><PersonLink person={post.author} className="font-bold" /> reconheceu <PersonLink person={post.subject} className="font-bold" /></p>
            <p className="text-xs font-medium text-amber-700">{BADGES[post.badge ?? '']?.label ?? 'Reconhecimento'}</p>
          </div>
          <PersonAvatarLink person={post.subject} size="lg" className="ml-auto" />
        </div>
      )}
      <div className="flex items-start gap-3">
        {post.kind !== 'kudos' && <PersonAvatarLink person={post.author} size="md" />}
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-x-2">
            <PersonLink person={post.author} className="font-semibold text-gray-900" />
            {post.author?.is_company && <Pill tone="brand">Perfil oficial</Pill>}
            {post.author?.job_title && !post.author.is_company && <span className="text-xs text-gray-400">{post.author.job_title}</span>}
            <span className="text-xs text-gray-400">· {timeAgo(post.created_at)}</span>
            {post.pinned && <Pill tone="amber">Fixado</Pill>}
            {post.department && <Pill tone="blue">{post.department}</Pill>}
          </div>
          {post.body && <MentionText text={post.body} names={post.mentions} className="mt-1 whitespace-pre-wrap break-words text-sm text-gray-700" />}
          <SubjectCard post={post} />
          {post.listing && <ListingEmbed listing={post.listing} />}
          <PollBlock slug={slug} post={post} />
          <PhotoGrid media={(post.media ?? []).filter((m) => m.kind !== 'video')} />
          {(post.media ?? []).filter((m) => m.kind === 'video').map((m) => (
            <video key={m.uuid} src={m.url} controls preload="metadata" playsInline className="mt-3 max-h-[28rem] w-full rounded-2xl bg-black" />
          ))}
          {post.requires_ack && (
            <div className={`mt-3 flex flex-wrap items-center gap-3 rounded-xl px-3 py-2 text-sm ${post.acked_at || post.ack_total !== null ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900'}`}>
              <BadgeCheck className="h-4 w-4 flex-shrink-0" />
              {post.ack_total !== null ? (
                <>
                  <span className="min-w-0 flex-1">Comunicado com confirmação de leitura: <b>{post.ack_count} de {post.ack_total}</b> confirmaram.</span>
                  <button type="button" className="font-semibold underline" onClick={() => setPanel(true)}>Ver quem falta</button>
                </>
              ) : post.acked_at ? (
                <span>Leitura confirmada em {fmtDate(post.acked_at)}.</span>
              ) : (
                <>
                  <span className="min-w-0 flex-1">Comunicado oficial: confirme que leu.</span>
                  <button type="button" className={btnPrimary} disabled={ack.isPending} onClick={() => ack.mutate()}>{ack.isPending ? 'Confirmando…' : 'Li e estou ciente'}</button>
                </>
              )}
            </div>
          )}
          {panel && <AckPanelModal slug={slug} post={post} onClose={() => setPanel(false)} />}
          <div className="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500">
            <ReactionBar slug={slug} post={post} />
            <span className="flex items-center gap-1"><MessageCircle className="h-4 w-4" /> {post.comments_count}</span>
            {isAdmin && (
              <button type="button" onClick={() => pin.mutate()} className="flex items-center gap-1 hover:text-gray-700"><Pin className="h-4 w-4" /> {post.pinned ? 'Desafixar' : 'Fixar'}</button>
            )}
            {post.can_delete && (
              <button type="button" onClick={() => { if (confirm('Remover esta publicação e suas fotos?')) del.mutate() }} className="ml-auto flex items-center gap-1 text-gray-400 hover:text-red-500" aria-label="Remover publicação"><Trash2 className="h-4 w-4" /></button>
            )}
          </div>
          <Comments slug={slug} post={post} expanded={open} onExpand={() => setOpen(true)} />
        </div>
      </div>
    </Panel>
  )
}

type Draft = { id: number; blob: Blob; preview: string }

export function FeedStream({ compact = false }: { compact?: boolean }) {
  const { slug, info } = useWs()
  const canPost = useCan('feed.post')
  const canModerate = useCan('feed.moderate')
  const qc = useQueryClient()
  const [body, setBody] = useState('')
  const [mentions, setMentions] = useState<Mention[]>([])
  const [dept, setDept] = useState('')
  const [group, setGroup] = useState('')   // '' = tudo · 'company' = só empresa toda · uuid = grupo do setor
  const [pinned, setPinned] = useState(false)
  const [asCompany, setAsCompany] = useState(false)
  const [requiresAck, setRequiresAck] = useState(false)
  const [photos, setPhotos] = useState<Draft[]>([])
  const [video, setVideo] = useState<File | null>(null)
  const [kudos, setKudos] = useState(false)
  const [pollOpen, setPollOpen] = useState(false)
  const videoRef = useRef<HTMLInputElement>(null)
  const [processing, setProcessing] = useState(false)
  const [error, setError] = useState('')
  const fileRef = useRef<HTMLInputElement>(null)
  const nextId = useRef(1)

  const feed = useInfiniteQuery({
    queryKey: ['ws', slug, 'feed', group],
    queryFn: ({ pageParam }) => workspaceService.feed(slug, pageParam, group),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.page < last.last_page ? last.page + 1 : undefined),
    // as URLs das fotos expiram em 30 min: recarrega o mural antes disso quando a aba volta ao foco
    staleTime: 10 * 60_000,
    refetchOnWindowFocus: true,
  })
  const { data: departments } = useQuery({
    queryKey: ['ws', slug, 'departments'],
    queryFn: () => workspaceService.departments(slug),
    enabled: canPost,
  })

  const reset = () => {
    photos.forEach((p) => URL.revokeObjectURL(p.preview))
    setBody(''); setMentions([]); setPinned(false); setAsCompany(false); setRequiresAck(false); setDept(''); setPhotos([]); setVideo(null); setError('')
  }

  const publish = useMutation({
    mutationFn: () => workspaceService.createPost(slug, {
      body: applyMentions(body.trim(), mentions), pinned, as_company: asCompany, requires_ack: requiresAck, department_uuid: dept || null, photos: photos.map((p) => p.blob), video,
    }),
    onSuccess: () => { reset(); qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] }) },
    onError: (e) => setError(errMsg(e)),
  })

  const addFiles = async (files: FileList | null) => {
    if (!files?.length) return
    setError('')
    setProcessing(true)
    try {
      const room = MAX_PHOTOS - photos.length
      const picked = Array.from(files).filter((f) => f.type.startsWith('image/')).slice(0, room)
      const drafts: Draft[] = []
      for (const f of picked) {
        const blob = await resizeImage(f)
        drafts.push({ id: nextId.current++, blob, preview: URL.createObjectURL(blob) })
      }
      setPhotos((cur) => [...cur, ...drafts])
      if (files.length > room) setError(`Máximo de ${MAX_PHOTOS} fotos por publicação.`)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Não foi possível processar a foto.')
    } finally {
      setProcessing(false)
      if (fileRef.current) fileRef.current.value = ''
    }
  }

  const removePhoto = (id: number) => setPhotos((cur) => {
    const gone = cur.find((p) => p.id === id)
    if (gone) URL.revokeObjectURL(gone.preview)
    return cur.filter((p) => p.id !== id)
  })

  const posts = feed.data?.pages.flatMap((p) => p.items) ?? []
  const groups = feed.data?.pages[0]?.groups ?? []
  const chip = (active: boolean) => `whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium transition ${active ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-800'}`
  const tool = 'inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 disabled:opacity-50'
  const pick = (g: string) => { setGroup(g); setDept(g && g !== 'company' ? g : '') }
  const canPublish = (body.trim() || photos.length > 0 || video) && !publish.isPending && !processing

  return (
    <div>
      {!compact && <PageHeader title="Mural" subtitle="Novidades, fotos e comunicados da empresa." />}

      {!canPost && (
        <div className="mb-3 flex justify-end">
          <button type="button" className={btnGhost} onClick={() => setKudos(true)}>🌟 Reconhecer um colega</button>
        </div>
      )}
      {kudos && <KudosModal slug={slug} onClose={() => setKudos(false)} />}
      {pollOpen && <PollModal slug={slug} defaultDept={group !== 'company' ? group : ''} onClose={() => setPollOpen(false)} />}

      {canPost && (
        <Panel className="mb-4">
          <MentionField
            slug={slug} value={body} onChange={setBody} mentions={mentions} onMentions={setMentions}
            rows={2} maxLength={3000} placeholder="Compartilhe uma novidade com a equipe… use @ para mencionar alguém"
          />

          {photos.length > 0 && (
            <ul className="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-6">
              {photos.map((p, i) => (
                <li key={p.id} className="group relative aspect-square overflow-hidden rounded-xl bg-gray-100">
                  <img src={p.preview} alt={`Foto ${i + 1} a publicar`} className="h-full w-full object-cover" />
                  <button
                    type="button" onClick={() => removePhoto(p.id)} aria-label={`Remover foto ${i + 1}`}
                    className="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white hover:bg-black/80"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                </li>
              ))}
            </ul>
          )}

          <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3">
            <input ref={fileRef} type="file" accept="image/*" multiple className="hidden" onChange={(e) => void addFiles(e.target.files)} />
            <button type="button" className={tool} disabled={photos.length >= MAX_PHOTOS || processing} onClick={() => fileRef.current?.click()}>
              <ImagePlus className="h-4 w-4" /> {processing ? 'Preparando…' : `Foto${photos.length ? ` (${photos.length}/${MAX_PHOTOS})` : ''}`}
            </button>
            <input ref={videoRef} type="file" accept="video/mp4,video/webm,video/quicktime" className="hidden" onChange={(e) => {
              const f = e.target.files?.[0]
              if (f && f.size > 200 * 1024 * 1024) setError('O vídeo pode ter no máximo 200 MB.')
              else if (f) { setError(''); setVideo(f) }
              if (videoRef.current) videoRef.current.value = ''
            }} />
            <button type="button" className={tool} onClick={() => videoRef.current?.click()}><Video className="h-4 w-4" /> {video ? 'Trocar vídeo' : 'Vídeo'}</button>
            {video && (
              <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 text-xs text-brand-700">{video.name.slice(0, 24)} <button type="button" aria-label="Remover vídeo" onClick={() => setVideo(null)}><X className="h-3 w-3" /></button></span>
            )}
            <button type="button" className={tool} onClick={() => setPollOpen(true)}>📊 Enquete</button>
            <button type="button" className={tool} onClick={() => setKudos(true)}>🌟 Reconhecer</button>
            <span className="mx-1 hidden h-5 w-px bg-gray-200 sm:block" aria-hidden="true" />
            <select className="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm text-gray-900" value={dept} onChange={(e) => setDept(e.target.value)} aria-label="Público">
              <option value="">Toda a empresa</option>
              {departments?.map((d) => <option key={d.uuid} value={d.uuid}>{d.name}</option>)}
            </select>
            <details className="relative">
              <summary className={`${tool} list-none`}>⚙ Opções</summary>
              <div className="absolute left-0 z-10 mt-1 w-64 space-y-2 rounded-xl border border-gray-200 bg-white p-3 shadow-lg">
                <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={requiresAck} onChange={(e) => setRequiresAck(e.target.checked)} /> Exigir confirmação de leitura</label>
                {canModerate && (
                  <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={pinned} onChange={(e) => setPinned(e.target.checked)} /> Fixar no topo</label>
                )}
                {canModerate && (
                  <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={asCompany} onChange={(e) => setAsCompany(e.target.checked)} /> Publicar como {info.hotel.name}</label>
                )}
              </div>
            </details>
            <button className={`${btnPrimary} ml-auto`} disabled={!canPublish} onClick={() => publish.mutate()}>
              {publish.isPending ? 'Publicando…' : 'Publicar'}
            </button>
          </div>
          {error && <p className="mt-2 text-sm text-red-600" role="alert">{error}</p>}
        </Panel>
      )}

      {(groups.length > 0 || group !== '') && (
        <div className="mb-3 flex gap-1 overflow-x-auto border-b border-gray-200" role="group" aria-label="Filtrar mural por grupo">
          <button type="button" aria-pressed={group === ''} className={chip(group === '')} onClick={() => pick('')}>Tudo</button>
          <button type="button" aria-pressed={group === 'company'} className={chip(group === 'company')} onClick={() => pick('company')}>Empresa toda</button>
          {groups.map((g) => <button key={g.uuid} type="button" aria-pressed={group === g.uuid} className={chip(group === g.uuid)} onClick={() => pick(g.uuid)}>{g.name}</button>)}
        </div>
      )}

      {feed.isLoading ? <Spinner /> : !posts.length ? <Empty text="Ainda não há publicações." /> : (
        <div className="space-y-3">
          {posts.map((p) => <PostCard key={p.uuid} slug={slug} post={p} isAdmin={canModerate} />)}
          {feed.hasNextPage && (
            <button className={`${btnGhost} w-full`} onClick={() => feed.fetchNextPage()} disabled={feed.isFetchingNextPage}>Carregar mais</button>
          )}
        </div>
      )}
    </div>
  )
}

export default function WorkspaceFeedPage() {
  return <FeedStream />
}
