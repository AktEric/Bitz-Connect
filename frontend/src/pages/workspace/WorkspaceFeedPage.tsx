import { useRef, useState } from 'react'
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { BadgeCheck, Cake, ImagePlus, MessageCircle, Pin, Trash2, Video, X } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { FeedComment, FeedPost } from '@/types/workspace.types'
import { Empty, Modal, PageHeader, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, fmtDate, timeAgo, useWs } from './ui'
import { MentionField, MentionText, applyMentions, type Mention } from './MentionField'
import PhotoGrid from './PhotoGrid'
import KudosModal, { BADGES } from './KudosModal'
import { PollBlock, PollModal } from './PollParts'
import ReactionBar from './ReactionBar'
import { resizeImage } from './imageUtils'

const MAX_PHOTOS = 6

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
              <Avatar src={c.author?.avatar_url} name={c.author?.name ?? '?'} size="xs" />
              <div className="rounded-xl bg-gray-50 px-3 py-1.5 text-sm">
                <span className="font-medium text-gray-900">{c.author?.name}</span>
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
                <li key={p.uuid} className="flex items-center gap-2 py-1.5"><Avatar src={p.avatar_url} name={p.name} size="xs" /> <span className="text-gray-900">{p.name}</span> <span className="text-xs text-gray-400">{p.department ?? p.job_title}</span></li>
              ))}</ul>
            )}
          </div>
          <div>
            <h3 className="mb-1 font-semibold text-emerald-700">Leram ({data.readers.length})</h3>
            <ul className="divide-y divide-gray-50">{data.readers.map((r) => (
              <li key={r.person.uuid} className="flex items-center gap-2 py-1.5"><Avatar src={r.person.avatar_url} name={r.person.name} size="xs" /> <span className="text-gray-900">{r.person.name}</span> <span className="ml-auto text-xs text-gray-400">{fmtDate(r.acked_at)}</span></li>
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
    <Panel className={post.kind === 'birthday' ? 'border-pink-100 bg-gradient-to-br from-pink-50 via-white to-amber-50' : post.kind === 'kudos' ? 'border-amber-100 bg-gradient-to-br from-amber-50 via-white to-violet-50' : undefined}>
      {post.kind === 'kudos' && post.subject && (
        <div className="mb-3 flex items-center gap-3 rounded-2xl bg-white/70 p-3">
          <span className="text-3xl" aria-hidden>{BADGES[post.badge ?? '']?.emoji ?? '⭐'}</span>
          <div className="min-w-0">
            <p className="text-sm text-gray-900"><b>{post.author?.name}</b> reconheceu <b>{post.subject.name}</b></p>
            <p className="text-xs font-medium text-amber-700">{BADGES[post.badge ?? '']?.label ?? 'Reconhecimento'}</p>
          </div>
          <Avatar src={post.subject.avatar_url} name={post.subject.name ?? '?'} size="lg" className="ml-auto" />
        </div>
      )}
      {post.kind === 'birthday' && post.subject && (
        <div className="mb-3 flex flex-col items-center gap-2 rounded-2xl bg-white/70 py-5 text-center">
          <Cake className="h-5 w-5 text-pink-500" />
          <Avatar src={post.subject.avatar_url} name={post.subject.name ?? '?'} size="xl" />
          <p className="text-lg font-semibold text-gray-900">Feliz aniversário, {post.subject.name?.split(' ')[0]}!</p>
          <p className="text-xs text-gray-500">{[post.subject.job_title, post.department].filter(Boolean).join(' · ')}</p>
        </div>
      )}
      <div className="flex items-start gap-3">
        {post.kind !== 'birthday' && post.kind !== 'kudos' && <Avatar src={post.author?.avatar_url} name={post.author?.name ?? '?'} size="md" />}
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-x-2">
            <span className="font-semibold text-gray-900">{post.author?.name ?? 'Ex-colaborador'}</span>
            {post.author?.job_title && <span className="text-xs text-gray-400">{post.author.job_title}</span>}
            <span className="text-xs text-gray-400">· {timeAgo(post.created_at)}</span>
            {post.pinned && <Pill tone="amber">Fixado</Pill>}
            {post.department && <Pill tone="blue">{post.department}</Pill>}
          </div>
          {post.body && <MentionText text={post.body} names={post.mentions} className="mt-1 whitespace-pre-wrap break-words text-sm text-gray-700" />}
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
  const qc = useQueryClient()
  const [body, setBody] = useState('')
  const [mentions, setMentions] = useState<Mention[]>([])
  const [dept, setDept] = useState('')
  const [pinned, setPinned] = useState(false)
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
    queryKey: ['ws', slug, 'feed'],
    queryFn: ({ pageParam }) => workspaceService.feed(slug, pageParam),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.page < last.last_page ? last.page + 1 : undefined),
    // as URLs das fotos expiram em 30 min: recarrega o mural antes disso quando a aba volta ao foco
    staleTime: 10 * 60_000,
    refetchOnWindowFocus: true,
  })
  const { data: departments } = useQuery({
    queryKey: ['ws', slug, 'departments'],
    queryFn: () => workspaceService.departments(slug),
    enabled: info.me.can_post,
  })

  const reset = () => {
    photos.forEach((p) => URL.revokeObjectURL(p.preview))
    setBody(''); setMentions([]); setPinned(false); setRequiresAck(false); setDept(''); setPhotos([]); setVideo(null); setError('')
  }

  const publish = useMutation({
    mutationFn: () => workspaceService.createPost(slug, {
      body: applyMentions(body.trim(), mentions), pinned, requires_ack: requiresAck, department_uuid: dept || null, photos: photos.map((p) => p.blob), video,
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
  const canPublish = (body.trim() || photos.length > 0 || video) && !publish.isPending && !processing

  return (
    <div>
      {!compact && <PageHeader title="Mural" subtitle="Novidades, fotos e comunicados da empresa." />}

      <div className="mb-3 flex justify-end gap-2">
        {info.me.can_post && <button type="button" className={btnGhost} onClick={() => setPollOpen(true)}>📊 Criar enquete</button>}
        <button type="button" className={btnGhost} onClick={() => setKudos(true)}>🌟 Reconhecer um colega</button>
      </div>
      {kudos && <KudosModal slug={slug} onClose={() => setKudos(false)} />}
      {pollOpen && <PollModal slug={slug} onClose={() => setPollOpen(false)} />}

      {info.me.can_post && (
        <Panel className="mb-4">
          <MentionField
            slug={slug} value={body} onChange={setBody} mentions={mentions} onMentions={setMentions}
            rows={3} maxLength={3000} placeholder="Compartilhe uma novidade com a equipe… use @ para mencionar alguém"
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

          <div className="mt-3 flex flex-wrap items-center gap-3">
            <input ref={fileRef} type="file" accept="image/*" multiple className="hidden" onChange={(e) => void addFiles(e.target.files)} />
            <button type="button" className={btnGhost} disabled={photos.length >= MAX_PHOTOS || processing} onClick={() => fileRef.current?.click()}>
              <ImagePlus className="h-4 w-4" /> {processing ? 'Preparando…' : `Fotos${photos.length ? ` (${photos.length}/${MAX_PHOTOS})` : ''}`}
            </button>
            <input ref={videoRef} type="file" accept="video/mp4,video/webm,video/quicktime" className="hidden" onChange={(e) => {
              const f = e.target.files?.[0]
              if (f && f.size > 200 * 1024 * 1024) setError('O vídeo pode ter no máximo 200 MB.')
              else if (f) { setError(''); setVideo(f) }
              if (videoRef.current) videoRef.current.value = ''
            }} />
            <button type="button" className={btnGhost} onClick={() => videoRef.current?.click()}><Video className="h-4 w-4" /> {video ? 'Trocar vídeo' : 'Vídeo'}</button>
            {video && (
              <span className="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2.5 py-1 text-xs text-violet-700">{video.name.slice(0, 24)} <button type="button" aria-label="Remover vídeo" onClick={() => setVideo(null)}><X className="h-3 w-3" /></button></span>
            )}
            <select className="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900" value={dept} onChange={(e) => setDept(e.target.value)} aria-label="Público">
              <option value="">Toda a empresa</option>
              {departments?.map((d) => <option key={d.uuid} value={d.uuid}>{d.name}</option>)}
            </select>
            <label className="flex items-center gap-1.5 text-sm text-gray-600"><input type="checkbox" checked={requiresAck} onChange={(e) => setRequiresAck(e.target.checked)} /> Exigir confirmação de leitura</label>
            {info.me.is_admin && (
              <label className="flex items-center gap-1.5 text-sm text-gray-600"><input type="checkbox" checked={pinned} onChange={(e) => setPinned(e.target.checked)} /> Fixar no topo</label>
            )}
            <button className={`${btnPrimary} ml-auto`} disabled={!canPublish} onClick={() => publish.mutate()}>
              {publish.isPending ? 'Publicando…' : 'Publicar'}
            </button>
          </div>
          {error && <p className="mt-2 text-sm text-red-600" role="alert">{error}</p>}
        </Panel>
      )}

      {feed.isLoading ? <Spinner /> : !posts.length ? <Empty text="Ainda não há publicações." /> : (
        <div className="space-y-3">
          {posts.map((p) => <PostCard key={p.uuid} slug={slug} post={p} isAdmin={info.me.is_admin} />)}
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
