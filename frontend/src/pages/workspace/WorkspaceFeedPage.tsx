import { useRef, useState } from 'react'
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ImagePlus, MessageCircle, Pin, Trash2, X } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { FeedPost } from '@/types/workspace.types'
import { Empty, PageHeader, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, timeAgo, useWs } from './ui'
import { MentionField, MentionText, applyMentions, type Mention } from './MentionField'
import PhotoGrid from './PhotoGrid'
import ReactionBar from './ReactionBar'
import { resizeImage } from './imageUtils'

const MAX_PHOTOS = 6

function Comments({ slug, post }: { slug: string; post: FeedPost }) {
  const qc = useQueryClient()
  const [text, setText] = useState('')
  const [mentions, setMentions] = useState<Mention[]>([])
  const key = ['ws', slug, 'comments', post.uuid]
  const { data, isLoading } = useQuery({ queryKey: key, queryFn: () => workspaceService.comments(slug, post.uuid) })
  const add = useMutation({
    mutationFn: () => workspaceService.addComment(slug, post.uuid, applyMentions(text.trim(), mentions)),
    onSuccess: () => { setText(''); setMentions([]); qc.invalidateQueries({ queryKey: key }); qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] }) },
  })
  const send = () => { if (text.trim() && !add.isPending) add.mutate() }

  return (
    <div className="mt-3 border-t border-gray-100 pt-3">
      {isLoading ? <p className="text-xs text-gray-400">Carregando…</p> : (
        <ul className="mb-3 space-y-2">
          {data?.map((c) => (
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

function PostCard({ slug, post, isAdmin }: { slug: string; post: FeedPost; isAdmin: boolean }) {
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)
  const refresh = () => qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] })
  const pin = useMutation({ mutationFn: () => workspaceService.pinPost(slug, post.uuid), onSuccess: refresh })
  const del = useMutation({ mutationFn: () => workspaceService.deletePost(slug, post.uuid), onSuccess: refresh })

  return (
    <Panel>
      <div className="flex items-start gap-3">
        <Avatar src={post.author?.avatar_url} name={post.author?.name ?? '?'} size="md" />
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-x-2">
            <span className="font-semibold text-gray-900">{post.author?.name ?? 'Ex-colaborador'}</span>
            {post.author?.job_title && <span className="text-xs text-gray-400">{post.author.job_title}</span>}
            <span className="text-xs text-gray-400">· {timeAgo(post.created_at)}</span>
            {post.pinned && <Pill tone="amber">Fixado</Pill>}
            {post.department && <Pill tone="blue">{post.department}</Pill>}
          </div>
          {post.body && <MentionText text={post.body} names={post.mentions} className="mt-1 whitespace-pre-wrap break-words text-sm text-gray-700" />}
          <PhotoGrid media={post.media ?? []} />
          <div className="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500">
            <ReactionBar slug={slug} post={post} />
            <button type="button" onClick={() => setOpen((o) => !o)} className="flex items-center gap-1 hover:text-gray-700">
              <MessageCircle className="h-4 w-4" /> {post.comments_count}
            </button>
            {isAdmin && (
              <button type="button" onClick={() => pin.mutate()} className="flex items-center gap-1 hover:text-gray-700"><Pin className="h-4 w-4" /> {post.pinned ? 'Desafixar' : 'Fixar'}</button>
            )}
            {post.can_delete && (
              <button type="button" onClick={() => { if (confirm('Remover esta publicação e suas fotos?')) del.mutate() }} className="ml-auto flex items-center gap-1 text-gray-400 hover:text-red-500" aria-label="Remover publicação"><Trash2 className="h-4 w-4" /></button>
            )}
          </div>
          {open && <Comments slug={slug} post={post} />}
        </div>
      </div>
    </Panel>
  )
}

type Draft = { id: number; blob: Blob; preview: string }

export default function WorkspaceFeedPage() {
  const { slug, info } = useWs()
  const qc = useQueryClient()
  const [body, setBody] = useState('')
  const [mentions, setMentions] = useState<Mention[]>([])
  const [dept, setDept] = useState('')
  const [pinned, setPinned] = useState(false)
  const [photos, setPhotos] = useState<Draft[]>([])
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
    setBody(''); setMentions([]); setPinned(false); setDept(''); setPhotos([]); setError('')
  }

  const publish = useMutation({
    mutationFn: () => workspaceService.createPost(slug, {
      body: applyMentions(body.trim(), mentions), pinned, department_uuid: dept || null, photos: photos.map((p) => p.blob),
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
  const canPublish = (body.trim() || photos.length > 0) && !publish.isPending && !processing

  return (
    <div>
      <PageHeader title="Mural" subtitle="Novidades, fotos e comunicados da empresa." />

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
            <select className="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900" value={dept} onChange={(e) => setDept(e.target.value)} aria-label="Público">
              <option value="">Toda a empresa</option>
              {departments?.map((d) => <option key={d.uuid} value={d.uuid}>{d.name}</option>)}
            </select>
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
