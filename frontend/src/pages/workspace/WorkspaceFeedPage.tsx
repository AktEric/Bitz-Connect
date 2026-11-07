import { useState } from 'react'
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Heart, MessageCircle, Pin, Trash2 } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import type { FeedPost } from '@/types/workspace.types'
import { Empty, PageHeader, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, inputCls, timeAgo, useWs } from './ui'

function Comments({ slug, post }: { slug: string; post: FeedPost }) {
  const qc = useQueryClient()
  const [text, setText] = useState('')
  const key = ['ws', slug, 'comments', post.uuid]
  const { data, isLoading } = useQuery({ queryKey: key, queryFn: () => workspaceService.comments(slug, post.uuid) })
  const add = useMutation({
    mutationFn: () => workspaceService.addComment(slug, post.uuid, text.trim()),
    onSuccess: () => { setText(''); qc.invalidateQueries({ queryKey: key }); qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] }) },
  })

  return (
    <div className="mt-3 border-t border-gray-100 pt-3">
      {isLoading ? <p className="text-xs text-gray-400">Carregando…</p> : (
        <ul className="mb-3 space-y-2">
          {data?.map((c) => (
            <li key={c.uuid} className="flex gap-2">
              <Avatar src={c.author?.avatar_url} name={c.author?.name ?? '?'} size="xs" />
              <p className="rounded-xl bg-gray-50 px-3 py-1.5 text-sm"><span className="font-medium text-gray-900">{c.author?.name}</span> <span className="text-gray-600">{c.body}</span></p>
            </li>
          ))}
        </ul>
      )}
      <form onSubmit={(e) => { e.preventDefault(); if (text.trim()) add.mutate() }} className="flex gap-2">
        <input className={inputCls} value={text} onChange={(e) => setText(e.target.value)} placeholder="Escreva um comentário…" maxLength={1000} />
        <button className={btnPrimary} disabled={!text.trim() || add.isPending}>Enviar</button>
      </form>
    </div>
  )
}

function PostCard({ slug, post, isAdmin }: { slug: string; post: FeedPost; isAdmin: boolean }) {
  const qc = useQueryClient()
  const [open, setOpen] = useState(false)
  const refresh = () => qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] })
  const like = useMutation({ mutationFn: () => workspaceService.likePost(slug, post.uuid), onSuccess: refresh })
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
          <p className="mt-1 whitespace-pre-wrap break-words text-sm text-gray-700">{post.body}</p>
          <div className="mt-3 flex items-center gap-4 text-sm text-gray-500">
            <button type="button" onClick={() => like.mutate()} className={`flex items-center gap-1 ${post.liked_by_me ? 'text-rose-500' : 'hover:text-gray-700'}`}>
              <Heart className={`h-4 w-4 ${post.liked_by_me ? 'fill-current' : ''}`} /> {post.likes_count}
            </button>
            <button type="button" onClick={() => setOpen((o) => !o)} className="flex items-center gap-1 hover:text-gray-700">
              <MessageCircle className="h-4 w-4" /> {post.comments_count}
            </button>
            {isAdmin && (
              <button type="button" onClick={() => pin.mutate()} className="flex items-center gap-1 hover:text-gray-700"><Pin className="h-4 w-4" /> {post.pinned ? 'Desafixar' : 'Fixar'}</button>
            )}
            {post.can_delete && (
              <button type="button" onClick={() => { if (confirm('Remover esta publicação?')) del.mutate() }} className="ml-auto flex items-center gap-1 text-gray-400 hover:text-red-500"><Trash2 className="h-4 w-4" /></button>
            )}
          </div>
          {open && <Comments slug={slug} post={post} />}
        </div>
      </div>
    </Panel>
  )
}

export default function WorkspaceFeedPage() {
  const { slug, info } = useWs()
  const qc = useQueryClient()
  const [body, setBody] = useState('')
  const [dept, setDept] = useState('')
  const [pinned, setPinned] = useState(false)
  const [error, setError] = useState('')

  const feed = useInfiniteQuery({
    queryKey: ['ws', slug, 'feed'],
    queryFn: ({ pageParam }) => workspaceService.feed(slug, pageParam),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.page < last.last_page ? last.page + 1 : undefined),
  })
  const { data: departments } = useQuery({
    queryKey: ['ws', slug, 'departments'],
    queryFn: () => workspaceService.departments(slug),
    enabled: info.me.can_post,
  })

  const publish = useMutation({
    mutationFn: () => workspaceService.createPost(slug, { body: body.trim(), pinned, department_uuid: dept || null }),
    onSuccess: () => { setBody(''); setPinned(false); setDept(''); setError(''); qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] }) },
    onError: (e) => setError(errMsg(e)),
  })

  const posts = feed.data?.pages.flatMap((p) => p.items) ?? []

  return (
    <div>
      <PageHeader title="Mural" subtitle="Novidades e comunicados da empresa." />

      {info.me.can_post && (
        <Panel className="mb-4">
          <textarea className={inputCls} rows={3} maxLength={3000} value={body} onChange={(e) => setBody(e.target.value)} placeholder="Compartilhe uma novidade com a equipe…" />
          <div className="mt-3 flex flex-wrap items-center gap-3">
            <select className={`${inputCls} !w-auto`} value={dept} onChange={(e) => setDept(e.target.value)} aria-label="Público">
              <option value="">Toda a empresa</option>
              {departments?.map((d) => <option key={d.uuid} value={d.uuid}>{d.name}</option>)}
            </select>
            {info.me.is_admin && (
              <label className="flex items-center gap-1.5 text-sm text-gray-600"><input type="checkbox" checked={pinned} onChange={(e) => setPinned(e.target.checked)} /> Fixar no topo</label>
            )}
            <button className={`${btnPrimary} ml-auto`} disabled={!body.trim() || publish.isPending} onClick={() => publish.mutate()}>Publicar</button>
          </div>
          {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
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
