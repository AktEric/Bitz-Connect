import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { useLocation } from 'react-router-dom'
import { MessageCircleQuestion, Send, X, RotateCcw } from 'lucide-react'
import { askVitoria, type HelpMessage } from '@/services/help.service'
import { cn } from '@/utils/cn'

const GREETING = 'Oi! Eu sou a Vitória, a assistente da central de ajuda. Pode me perguntar como fazer qualquer coisa no sistema — ou só bater um papo. 😊'

const SUGGESTIONS = [
  'Como monto o organograma?',
  'Como vejo as pessoas da empresa?',
  'Como edito meu perfil?',
  'O que eu posso postar no mural?',
]

/** Negrito (**texto**) e destaque (*texto*, caminhos de menu) nas respostas; o resto vai como texto puro. */
function Rich({ text }: { text: string }) {
  return (
    <>
      {text.split(/(\*\*[^*]+\*\*|\*[^*\n]+\*)/g).map((part, i) => {
        if (part.startsWith('**') && part.endsWith('**') && part.length > 4) return <strong key={i}>{part.slice(2, -2)}</strong>
        if (part.startsWith('*') && part.endsWith('*') && part.length > 2) return <em key={i} className="font-medium not-italic text-brand-700">{part.slice(1, -1)}</em>
        return <span key={i}>{part}</span>
      })}
    </>
  )
}

interface Props {
  /** slug do Workspace atual, quando estiver dentro de uma empresa (a Vitória adapta a resposta ao papel da pessoa). */
  workspace?: string
  /** classes extras para posicionar o botão flutuante. */
  className?: string
}

export default function VitoriaChat({ workspace, className }: Props) {
  const location = useLocation()
  const [open, setOpen] = useState(false)
  const [messages, setMessages] = useState<HelpMessage[]>([])
  const [text, setText] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const endRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLTextAreaElement>(null)

  useEffect(() => {
    if (open) inputRef.current?.focus()
  }, [open])

  useEffect(() => {
    endRef.current?.scrollIntoView({ block: 'end' })
  }, [messages, loading, open])

  useEffect(() => {
    if (!open) return
    const onKey = (e: globalThis.KeyboardEvent) => { if (e.key === 'Escape') setOpen(false) }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open])

  async function send(raw: string) {
    const content = raw.trim()
    if (!content || loading) return
    const next: HelpMessage[] = [...messages, { role: 'user', content }]
    setMessages(next)
    setText('')
    setError(null)
    setLoading(true)
    try {
      const reply = await askVitoria(next, { workspace, page: location.pathname })
      setMessages([...next, { role: 'assistant', content: reply }])
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
      setError(msg ?? 'Não consegui responder agora. Tente de novo em instantes.')
    } finally {
      setLoading(false)
    }
  }

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    void send(text)
  }

  function onKeyDown(e: KeyboardEvent<HTMLTextAreaElement>) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      void send(text)
    }
  }

  function reset() {
    setMessages([])
    setError(null)
    setText('')
  }

  return (
    <>
      {!open && (
        <button
          type="button"
          onClick={() => setOpen(true)}
          aria-label="Abrir a central de ajuda (Vitória)"
          title="Precisa de ajuda? Fale com a Vitória"
          className={cn(
            'fixed bottom-20 right-4 z-40 flex items-center gap-2 rounded-full bg-brand-600 px-4 py-3 text-sm font-medium text-white shadow-lg transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 lg:bottom-5 lg:right-5',
            className,
          )}
        >
          <MessageCircleQuestion className="h-5 w-5" /> <span className="hidden sm:inline">Ajuda</span>
        </button>
      )}

      {open && (
        <div
          role="dialog"
          aria-label="Central de ajuda — Vitória"
          className="fixed inset-x-3 bottom-20 z-50 flex h-[min(34rem,calc(100vh-7rem))] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl sm:inset-x-auto sm:right-4 sm:w-[24rem] lg:bottom-5"
        >
          <header className="flex items-center gap-3 bg-brand-600 px-4 py-3 text-white">
            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-white/20 text-base font-semibold" aria-hidden>V</span>
            <div className="min-w-0 flex-1">
              <p className="font-semibold leading-tight">Vitória</p>
              <p className="text-xs text-brand-100">Central de ajuda</p>
            </div>
            <button type="button" onClick={reset} aria-label="Nova conversa" title="Nova conversa" className="rounded-lg p-1.5 hover:bg-white/15">
              <RotateCcw className="h-4 w-4" />
            </button>
            <button type="button" onClick={() => setOpen(false)} aria-label="Fechar" title="Fechar" className="rounded-lg p-1.5 hover:bg-white/15">
              <X className="h-5 w-5" />
            </button>
          </header>

          <div className="flex-1 space-y-3 overflow-y-auto bg-slate-50 px-3 py-4" aria-live="polite">
            <Bubble role="assistant" text={GREETING} />
            {messages.length === 0 && (
              <div className="flex flex-wrap gap-2 pt-1">
                {SUGGESTIONS.map((s) => (
                  <button
                    key={s}
                    type="button"
                    onClick={() => void send(s)}
                    className="rounded-full border border-brand-200 bg-white px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-50"
                  >
                    {s}
                  </button>
                ))}
              </div>
            )}
            {messages.map((m, i) => <Bubble key={i} role={m.role} text={m.content} />)}
            {loading && (
              <div className="flex items-center gap-1 pl-1" aria-label="Vitória está digitando">
                {[0, 150, 300].map((d) => (
                  <span key={d} className="h-2 w-2 animate-bounce rounded-full bg-gray-400" style={{ animationDelay: `${d}ms` }} />
                ))}
              </div>
            )}
            {error && <p role="alert" className="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
            <div ref={endRef} />
          </div>

          <form onSubmit={onSubmit} className="flex items-end gap-2 border-t border-gray-200 bg-white p-3">
            <textarea
              ref={inputRef}
              value={text}
              onChange={(e) => setText(e.target.value)}
              onKeyDown={onKeyDown}
              rows={1}
              maxLength={2000}
              placeholder="Escreva sua dúvida…"
              aria-label="Mensagem para a Vitória"
              className="max-h-28 min-h-[2.5rem] flex-1 resize-none rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
            />
            <button
              type="submit"
              disabled={loading || !text.trim()}
              aria-label="Enviar"
              className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white hover:bg-brand-700 disabled:opacity-40"
            >
              <Send className="h-4 w-4" />
            </button>
          </form>
        </div>
      )}
    </>
  )
}

function Bubble({ role, text }: { role: HelpMessage['role']; text: string }) {
  const mine = role === 'user'
  return (
    <div className={cn('flex', mine ? 'justify-end' : 'justify-start')}>
      <div
        className={cn(
          'max-w-[85%] whitespace-pre-wrap break-words rounded-2xl px-3.5 py-2 text-sm leading-relaxed',
          mine ? 'rounded-br-md bg-brand-600 text-white' : 'rounded-bl-md bg-white text-gray-800 shadow-sm ring-1 ring-gray-200',
        )}
      >
        <Rich text={text} />
      </div>
    </div>
  )
}
