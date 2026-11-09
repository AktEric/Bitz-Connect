import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { BookmarkPlus, Check, CheckCheck, Lock, Maximize2, Minimize2, Minus, SmilePlus, X } from 'lucide-react'
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
import { stickerById } from './stickers'
import StickerImage from './StickerImage'
import { useMyStickers } from './useMyStickers'
import type { DeliveryStatus } from '@/types/chatGroup.types'
import type { RichPayload } from './StickerGifPanel'

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
  status?: DeliveryStatus | null; reactions?: { emoji: string; count: number; mine: boolean; people: string[] }[]
  sender?: { uuid?: string | null; name: string; avatar_url: string | null }
  is_announcement?: boolean
}

const REACTION_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏']

/** ✓ enviada · ✓✓ entregue · ✓✓ azul visualizada (só nas minhas mensagens). */
function Ticks({ status, onDark }: { status?: DeliveryStatus | null; onDark: boolean }) {
  if (!status) return null
  const label = status === 'read' ? 'Visualizada' : status === 'delivered' ? 'Entregue' : 'Enviada'
  const Icon = status === 'sent' ? Check : CheckCheck

  return (
    <span title={label} aria-label={label} className={cn('inline-flex', status === 'read' ? (onDark ? 'text-sky-300' : 'text-sky-500') : onDark ? 'text-white/70' : 'text-gray-400')}>
      <Icon className="h-3.5 w-3.5" />
    </span>
  )
}

/** Figurinha (sem balão), GIF ou o balão normal; com reações e confirmação de envio/leitura. */
function Bubble({ m, group, slug, showAvatar, onReact, savedStickers }: { m: BubbleMessage; group: boolean; slug?: string; showAvatar: boolean; onReact: (emoji: string | null) => void; savedStickers: Set<string> }) {
  const att = attachmentOf(m.type, m.metadata)
  const builtin = m.type === 'sticker' ? stickerById(String((m.metadata as { id?: string } | null)?.id ?? '')) : null
  const customUuid = m.type === 'sticker' ? ((m.metadata as { sticker_uuid?: string } | null)?.sticker_uuid ?? null) : null
  const gif = m.type === 'gif' ? (m.metadata as { url?: string; title?: string } | null) : null
  const listing = m.type === 'listing' ? (m.metadata as { listing_uuid?: string; title?: string; price_cents?: number | null; hotel_slug?: string; status?: string } | null) : null
  const showSender = group && !m.is_mine && m.sender
  const profile = slug && m.sender?.uuid ? `/w/${slug}/pessoas/${m.sender.uuid}` : null
  const caption = (att && /^(📷 Foto|🎤 Mensagem de voz|📎 )/.test(m.content)) || (m.type === 'listing' && m.content === '🛍️ Anúncio do marketplace') ? '' : m.content
  const bare = !!builtin || !!customUuid || !!gif
  const [picker, setPicker] = useState(false)
  const reactions = m.reactions ?? []

  // foto de quem fala, na última mensagem de cada sequência (como nas redes sociais); as demais deixam o espaço
  const avatar = !m.is_mine && m.sender && (
    showAvatar
      ? (profile
        ? <Link to={profile} aria-label={`Ver perfil de ${m.sender.name}`} className="flex-shrink-0 rounded-full"><Avatar src={m.sender.avatar_url} name={m.sender.name} size="xs" /></Link>
        : <Avatar src={m.sender.avatar_url} name={m.sender.name} size="xs" className="flex-shrink-0" />)
      : <span className="w-6 flex-shrink-0" aria-hidden />
  )

  const meta = (onDark: boolean) => (
    <p className={cn('mt-0.5 flex items-center gap-1 text-[10px]', m.is_mine && 'justify-end', onDark ? 'text-white/70' : 'text-gray-400')}>
      {formatTime(m.created_at)} {m.is_mine && <Ticks status={m.status} onDark={onDark} />}
    </p>
  )

  const react = (
    <div className={cn('relative flex-shrink-0 self-center', m.is_mine && 'order-first')}>
      <button type="button" onClick={() => setPicker((v) => !v)} aria-label="Reagir à mensagem" aria-expanded={picker}
        className="rounded-full p-1 text-gray-400 opacity-0 transition hover:bg-gray-100 hover:text-gray-700 focus:opacity-100 group-hover:opacity-100"><SmilePlus className="h-4 w-4" /></button>
      {picker && (
        <div role="menu" aria-label="Reações" className={cn('absolute -top-10 z-20 flex gap-0.5 rounded-full border border-gray-100 bg-white px-1.5 py-1 shadow-lg', m.is_mine ? 'right-0' : 'left-0')}>
          {REACTION_EMOJIS.map((e) => {
            const mine = reactions.some((r) => r.mine && r.emoji === e)
            return <button key={e} type="button" role="menuitem" aria-label={`Reagir com ${e}`} onClick={() => { setPicker(false); onReact(mine ? null : e) }} className={cn('rounded-full px-1 text-lg transition-transform hover:scale-125', mine && 'bg-brand-50')}>{e}</button>
          })}
        </div>
      )}
    </div>
  )

  return (
    <div className={cn('group flex items-end gap-1.5', m.is_mine && 'justify-end')}>
      {avatar}
      {m.is_mine && react}
      <div className={cn('flex min-w-0 max-w-[78%] flex-col', m.is_mine ? 'items-end' : 'items-start')}>
        {bare ? (
          <div className="max-w-full">
            {builtin && (
              <div className={cn('flex h-28 w-28 flex-col items-center justify-center rounded-3xl bg-gradient-to-br shadow-sm', builtin.bg)} title={builtin.label}>
                <span className="text-5xl leading-none">{builtin.emoji}</span>
                <span className="mt-1.5 text-xs font-bold text-gray-700">{builtin.label}</span>
              </div>
            )}
            {customUuid && (
              <div>
                <StickerImage uuid={customUuid} className="h-28 w-28" />
                {!m.is_mine && !savedStickers.has(customUuid) && <SaveStickerButton uuid={customUuid} />}
              </div>
            )}
            {gif?.url && <img src={gif.url} alt={gif.title ?? 'GIF'} className="max-h-44 max-w-full rounded-2xl" loading="lazy" />}
            {m.type === 'sticker' && !builtin && !customUuid && <p className="text-xs text-gray-400">Figurinha</p>}
            {meta(false)}
          </div>
        ) : (
          <div className={cn('rounded-2xl px-3 py-1.5 text-sm', m.is_mine ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-900', m.is_announcement && 'border border-amber-300 bg-amber-50 text-amber-900')}>
            {showSender && (profile
              ? <Link to={profile} className="mb-0.5 block text-xs font-semibold text-brand-700 hover:underline">{m.sender!.name}</Link>
              : <p className="mb-0.5 text-xs font-semibold text-brand-700">{m.sender!.name}</p>)}
            {att && <div className="py-0.5"><ChatAttachment a={att} mine={m.is_mine} /></div>}
            {listing?.listing_uuid && (
              <Link to={`/w/${listing.hotel_slug ?? slug}/marketplace?anuncio=${listing.listing_uuid}`} className={cn('mb-1 flex items-center gap-2 rounded-xl p-2', m.is_mine ? 'bg-white/15' : 'bg-white')}>
                <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-brand-100 text-lg" aria-hidden>🛍️</span>
                <span className="min-w-0"><span className="block truncate text-sm font-semibold">{listing.title}</span><span className="block text-xs opacity-80">{listing.price_cents == null ? 'A combinar' : (listing.price_cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}{listing.status === 'sold' ? ' · Vendido' : ''} · ver anúncio</span></span>
              </Link>
            )}
            {(!att || caption) && (caption || !listing) && <p className="whitespace-pre-wrap break-words">{att || listing ? caption : m.content}</p>}
            {meta(m.is_mine && !m.is_announcement)}
          </div>
        )}

        {!!reactions.length && (
          <div className={cn('-mt-1 flex flex-wrap gap-1', m.is_mine ? 'justify-end' : 'justify-start')}>
            {reactions.map((r) => (
              <button key={r.emoji} type="button" onClick={() => onReact(r.mine ? null : r.emoji)} aria-pressed={r.mine} title={r.people.join(', ')} aria-label={`${r.emoji} ${r.count}: ${r.people.join(', ')}`}
                className={cn('inline-flex items-center gap-0.5 rounded-full border px-1.5 py-0.5 text-xs shadow-sm', r.mine ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50')}>
                <span>{r.emoji}</span>{r.count > 1 && <span className="font-medium">{r.count}</span>}
              </button>
            ))}
          </div>
        )}
      </div>
      {!m.is_mine && react}
    </div>
  )
}

/** "Salvar figurinha": guarda na minha coleção a figurinha que recebi. */
function SaveStickerButton({ uuid }: { uuid: string }) {
  const qc = useQueryClient()
  const { toast } = useToast()
  const save = useMutation({
    mutationFn: () => chatService.saveSticker(uuid),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['chat', 'stickers'] }); toast('Figurinha salva na sua coleção.', 'success') },
    onError: (e) => toast(extractApiError(e), 'error'),
  })

  return <button type="button" onClick={() => save.mutate()} disabled={save.isPending} className="mt-1 inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 hover:bg-gray-200"><BookmarkPlus className="h-3 w-3" /> Salvar figurinha</button>
}

/** Mostra a foto só quando a próxima mensagem é de outra pessoa (ou não há próxima). */
const lastOfRun = (list: BubbleMessage[], i: number) => {
  const next = list[i + 1]
  return !next || next.is_mine !== list[i].is_mine || (next.sender?.uuid ?? '') !== (list[i].sender?.uuid ?? '')
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

  const saved = useMyStickers()
  const react = useMutation({
    mutationFn: ({ uuid, emoji }: { uuid: string; emoji: string | null }) => chatService.react(uuid, emoji),
    onSuccess: () => qc.invalidateQueries({ queryKey: chatKeys.messages(person.uuid) }),
  })
  const sendRich = useMutation({
    mutationFn: (p: RichPayload) => chatService.sendRich(person.uuid, p),
    onSuccess: () => { qc.invalidateQueries({ queryKey: chatKeys.messages(person.uuid) }); qc.invalidateQueries({ queryKey: chatKeys.conversations }) },
  })

  useEffect(() => { if (latest && !latest.startsWith('optimistic-')) markRead.mutate() /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [latest])

  return (
    <>
      <div className="flex-1 space-y-1.5 overflow-y-auto px-3 py-3">
        {!messages.length && <p className="py-10 text-center text-sm text-gray-400">Diga olá para {person.name.split(' ')[0]}.</p>}
        {messages.map((m, i) => <Bubble key={m.uuid} m={m} group={false} slug={slug} showAvatar={lastOfRun(messages, i)} onReact={(emoji) => react.mutate({ uuid: m.uuid, emoji })} savedStickers={saved} />)}
        <div ref={bottom} />
      </div>
      <ChatComposer
        onError={(e) => toast(e, 'error')}
        onSendText={async (t) => { try { await sendText.mutateAsync(t) } catch (e) { throw new Error(extractApiError(e)) } }}
        onSendFile={async (file, opts) => { try { await sendFile.mutateAsync({ file, opts }) } catch (e) { throw new Error(extractApiError(e)) } }}
        onSendRich={async (p) => { try { await sendRich.mutateAsync(p) } catch (e) { throw new Error(extractApiError(e)) } }}
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

  const saved = useMyStickers()
  const react = useMutation({
    mutationFn: ({ uuid, emoji }: { uuid: string; emoji: string | null }) => chatService.react(uuid, emoji),
    onSuccess: () => qc.invalidateQueries({ queryKey: groupKeys.messages(target.uuid) }),
  })
  const sendRich = useMutation({
    mutationFn: (p: RichPayload) => chatGroupService.sendRich(target.uuid, p),
    onSuccess: () => { qc.invalidateQueries({ queryKey: groupKeys.messages(target.uuid) }); qc.invalidateQueries({ queryKey: groupKeys.list }) },
  })

  useEffect(() => { if (latest) markRead.mutate() /* eslint-disable-next-line react-hooks/exhaustive-deps */ }, [latest])

  return (
    <>
      <div className="flex-1 space-y-1.5 overflow-y-auto px-3 py-3">
        {!messages.length && <p className="py-10 text-center text-sm text-gray-400">Nenhuma mensagem ainda.</p>}
        {messages.map((m, i) => <Bubble key={m.uuid} m={m} group slug={slug} showAvatar={lastOfRun(messages, i)} onReact={(emoji) => react.mutate({ uuid: m.uuid, emoji })} savedStickers={saved} />)}
        <div ref={bottom} />
      </div>
      {group && !group.can_post ? (
        <p className="flex items-center justify-center gap-2 border-t border-gray-100 px-3 py-3 text-xs text-gray-500"><Lock className="h-3.5 w-3.5" /> Somente administradores enviam neste grupo.</p>
      ) : (
        <ChatComposer
          onError={(e) => toast(e, 'error')}
          onSendText={async (content) => { try { await sendText.mutateAsync({ content }) } catch (e) { throw new Error(extractApiError(e)) } }}
          onSendFile={async (file, opts) => { try { await sendFile.mutateAsync({ file, opts }) } catch (e) { throw new Error(extractApiError(e)) } }}
          onSendRich={async (p) => { try { await sendRich.mutateAsync(p) } catch (e) { throw new Error(extractApiError(e)) } }}
        />
      )}
    </>
  )
}
