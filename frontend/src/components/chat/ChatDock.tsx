import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Lock, Maximize2, Minimize2, Minus, X } from 'lucide-react'
import { useMarkRead, useMessages, useSendMessage, chatKeys } from '@/hooks/useChat'
import { useChatGroup, useGroupMessages, useMarkGroupRead, useSendGroupMessage, groupKeys } from '@/hooks/useChatGroups'
import chatService from '@/services/chat.service'
import chatGroupService from '@/services/chatGroup.service'
import { useToast } from '@/contexts/ToastContext'
import { Avatar } from '@/components/ui/Avatar'
import { cn } from '@/utils/cn'
import { extractApiError } from '@/utils/api-error'
import ChatAttachment, { attachmentOf } from './ChatAttachment'
import ChatComposer from './ChatComposer'

export interface DockTarget {
  kind: 'direct' | 'group'
  uuid: string
  name: string
  avatar_url: string | null
}
interface DockWindow extends DockTarget { key: string; minimized: boolean; expanded: boolean }

interface DockApi { open: (t: DockTarget) => void }
const DockContext = createContext<DockApi>({ open: () => {} })

/** Abre conversas como janelas flutuantes no canto da tela (estilo Facebook). */
export const useChatDock = () => useContext(DockContext)

const MAX_WINDOWS = 3

export function ChatDockProvider({ children }: { children: ReactNode }) {
  const [windows, setWindows] = useState<DockWindow[]>([])

  const open = useCallback((t: DockTarget) => {
    const key = `${t.kind}:${t.uuid}`
    setWindows((all) => {
      const existing = all.find((w) => w.key === key)
      const rest = all.filter((w) => w.key !== key)
      // a janela aberta volta para a frente e já aparece restaurada
      return [{ ...(existing ?? { ...t, key, expanded: false }), minimized: false }, ...rest].slice(0, MAX_WINDOWS)
    })
  }, [])

  const patch = (key: string, p: Partial<DockWindow>) => setWindows((all) => all.map((w) => (w.key === key ? { ...w, ...p } : w)))
  const close = (key: string) => setWindows((all) => all.filter((w) => w.key !== key))
  const api = useMemo(() => ({ open }), [open])

  return (
    <DockContext.Provider value={api}>
      {children}
      {/* newest à direita (mais perto da borda), como no Facebook */}
      <div className="pointer-events-none fixed bottom-0 right-2 z-[56] flex flex-row-reverse items-end gap-3 sm:right-4 xl:right-[4.75rem]" aria-label="Conversas abertas">
        {windows.map((w, i) => (
          <ChatWindow
            key={w.key} win={w} className={i > 0 ? 'hidden sm:flex' : undefined}
            onMinimize={() => patch(w.key, { minimized: !w.minimized })}
            onExpand={() => patch(w.key, { expanded: !w.expanded, minimized: false })}
            onClose={() => close(w.key)}
          />
        ))}
      </div>
    </DockContext.Provider>
  )
}

function formatTime(iso: string): string {
  const d = new Date(iso)
  return d.toDateString() === new Date().toDateString()
    ? d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
    : d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

interface BubbleMessage {
  uuid: string; content: string; type: string; metadata?: unknown; is_mine: boolean; created_at: string
  sender?: { uuid?: string | null; name: string; avatar_url: string | null }
  is_announcement?: boolean
}

function Bubble({ m, group, slug }: { m: BubbleMessage; group: boolean; slug?: string }) {
  const att = attachmentOf(m.type, m.metadata)
  const showSender = group && !m.is_mine && m.sender
  const profile = slug && m.sender?.uuid ? `/w/${slug}/pessoas/${m.sender.uuid}` : null
  const caption = att && /^(📷 Foto|🎤 Mensagem de voz|📎 )/.test(m.content) ? '' : m.content

  return (
    <div className={cn('flex items-end gap-1.5', m.is_mine && 'justify-end')}>
      {showSender && (profile
        ? <Link to={profile} aria-label={`Ver perfil de ${m.sender!.name}`}><Avatar src={m.sender!.avatar_url} name={m.sender!.name} size="xs" /></Link>
        : <Avatar src={m.sender!.avatar_url} name={m.sender!.name} size="xs" />)}
      <div className={cn('max-w-[78%] rounded-2xl px-3 py-1.5 text-sm', m.is_mine ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-900', m.is_announcement && 'border border-amber-300 bg-amber-50 text-amber-900')}>
        {showSender && (profile
          ? <Link to={profile} className="mb-0.5 block text-xs font-semibold text-brand-700 hover:underline">{m.sender!.name}</Link>
          : <p className="mb-0.5 text-xs font-semibold text-brand-700">{m.sender!.name}</p>)}
        {att && <div className="py-0.5"><ChatAttachment a={att} mine={m.is_mine} /></div>}
        {(!att || caption) && <p className="whitespace-pre-wrap break-words">{att ? caption : m.content}</p>}
        <p className={cn('text-right text-[10px]', m.is_mine && !m.is_announcement ? 'text-white/70' : 'text-gray-400')}>{formatTime(m.created_at)}</p>
      </div>
    </div>
  )
}

function ChatWindow({ win, className, onMinimize, onExpand, onClose }: { win: DockWindow; className?: string; onMinimize: () => void; onExpand: () => void; onClose: () => void }) {
  const { slug } = useParams()
  const profile = win.kind === 'direct' && slug ? `/w/${slug}/pessoas/${win.uuid}` : null

  return (
    <section
      aria-label={`Conversa com ${win.name}`}
      className={cn(
        'pointer-events-auto flex flex-col overflow-hidden rounded-t-xl border border-gray-200 bg-white shadow-2xl',
        'w-[calc(100vw-1rem)] sm:w-[21rem]',
        win.minimized ? 'h-12 sm:w-60' : win.expanded ? 'h-[min(40rem,calc(100vh-4rem))] sm:w-[30rem]' : 'h-[min(27rem,calc(100vh-4rem))]',
        className,
      )}
    >
      <header className="flex h-12 flex-shrink-0 items-center gap-1 bg-brand-600 pl-2 pr-1 text-white">
        {profile ? (
          <Link to={profile} className="flex min-w-0 flex-1 items-center gap-2 rounded-lg py-1 pr-1 hover:bg-white/10" title="Ver perfil">
            <Avatar src={win.avatar_url} name={win.name} size="sm" />
            <span className="truncate text-sm font-semibold">{win.name}</span>
          </Link>
        ) : (
          <button type="button" onClick={onMinimize} className="flex min-w-0 flex-1 items-center gap-2 py-1 text-left">
            <Avatar src={win.avatar_url} name={win.name} size="sm" />
            <span className="truncate text-sm font-semibold">{win.name}</span>
          </button>
        )}
        <button type="button" onClick={onMinimize} className="rounded-full p-1.5 hover:bg-white/15" aria-label={win.minimized ? 'Restaurar conversa' : 'Minimizar conversa'} title={win.minimized ? 'Restaurar' : 'Minimizar'}><Minus className="h-4 w-4" /></button>
        <button type="button" onClick={onExpand} className="hidden rounded-full p-1.5 hover:bg-white/15 sm:block" aria-label={win.expanded ? 'Reduzir conversa' : 'Expandir conversa'} title={win.expanded ? 'Reduzir' : 'Expandir'}>
          {win.expanded ? <Minimize2 className="h-4 w-4" /> : <Maximize2 className="h-4 w-4" />}
        </button>
        <button type="button" onClick={onClose} className="rounded-full p-1.5 hover:bg-white/15" aria-label="Fechar conversa" title="Fechar"><X className="h-4 w-4" /></button>
      </header>
      {!win.minimized && (win.kind === 'direct' ? <DirectBody person={win} slug={slug} /> : <GroupBody group={win} slug={slug} />)}
    </section>
  )
}

function useStickToBottom(dep: unknown) {
  const ref = useRef<HTMLDivElement>(null)
  useEffect(() => { ref.current?.scrollIntoView({ block: 'end' }) }, [dep])
  return ref
}

function DirectBody({ person, slug }: { person: DockTarget; slug?: string }) {
  const { toast } = useToast()
  const qc = useQueryClient()
  const { data: raw = [] } = useMessages(person.uuid)
  const sendText = useSendMessage(person.uuid)
  const markRead = useMarkRead(person.uuid)
  const messages = useMemo(() => [...raw].reverse(), [raw])
  const latest = raw[0]?.uuid
  const bottom = useStickToBottom(messages.length)
  const sendFile = useMutation({
    mutationFn: ({ file, opts }: { file: File; opts: { voice?: boolean; duration?: number } }) => chatService.sendFile(person.uuid, file, opts),
    onSuccess: () => { qc.invalidateQueries({ queryKey: chatKeys.messages(person.uuid) }); qc.invalidateQueries({ queryKey: chatKeys.conversations }) },
  })

  useEffect(() => { if (latest && !latest.startsWith('optimistic-')) markRead.mutate() /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [latest])

  return (
    <>
      <div className="flex-1 space-y-1.5 overflow-y-auto px-3 py-3">
        {!messages.length && <p className="py-10 text-center text-sm text-gray-400">Diga olá para {person.name.split(' ')[0]}.</p>}
        {messages.map((m) => <Bubble key={m.uuid} m={m} group={false} slug={slug} />)}
        <div ref={bottom} />
      </div>
      <ChatComposer
        onError={(e) => toast(e, 'error')}
        onSendText={async (t) => { try { await sendText.mutateAsync(t) } catch (e) { throw new Error(extractApiError(e)) } }}
        onSendFile={async (file, opts) => { try { await sendFile.mutateAsync({ file, opts }) } catch (e) { throw new Error(extractApiError(e)) } }}
      />
    </>
  )
}

function GroupBody({ group: target, slug }: { group: DockTarget; slug?: string }) {
  const { toast } = useToast()
  const qc = useQueryClient()
  const { data: group } = useChatGroup(target.uuid)
  const { data: raw = [] } = useGroupMessages(target.uuid)
  const sendText = useSendGroupMessage(target.uuid)
  const markRead = useMarkGroupRead(target.uuid)
  const messages = useMemo(() => [...raw].reverse(), [raw])
  const latest = raw[0]?.uuid
  const bottom = useStickToBottom(messages.length)
  const sendFile = useMutation({
    mutationFn: ({ file, opts }: { file: File; opts: { voice?: boolean; duration?: number } }) => chatGroupService.sendFile(target.uuid, file, opts),
    onSuccess: () => { qc.invalidateQueries({ queryKey: groupKeys.messages(target.uuid) }); qc.invalidateQueries({ queryKey: groupKeys.list }) },
  })

  useEffect(() => { if (latest) markRead.mutate() /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [latest])

  return (
    <>
      <div className="flex-1 space-y-1.5 overflow-y-auto px-3 py-3">
        {!messages.length && <p className="py-10 text-center text-sm text-gray-400">Nenhuma mensagem ainda.</p>}
        {messages.map((m) => <Bubble key={m.uuid} m={m} group slug={slug} />)}
        <div ref={bottom} />
      </div>
      {group && !group.can_post ? (
        <p className="flex items-center justify-center gap-2 border-t border-gray-100 px-3 py-3 text-xs text-gray-500"><Lock className="h-3.5 w-3.5" /> Somente administradores enviam neste grupo.</p>
      ) : (
        <ChatComposer
          onError={(e) => toast(e, 'error')}
          onSendText={async (content) => { try { await sendText.mutateAsync({ content }) } catch (e) { throw new Error(extractApiError(e)) } }}
          onSendFile={async (file, opts) => { try { await sendFile.mutateAsync({ file, opts }) } catch (e) { throw new Error(extractApiError(e)) } }}
        />
      )}
    </>
  )
}
