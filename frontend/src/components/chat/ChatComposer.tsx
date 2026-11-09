import { useRef, useState } from 'react'
import { Mic, Paperclip, Send, Smile, Trash2, X } from 'lucide-react'
import { cn } from '@/utils/cn'
import { EMOJI_GROUPS } from './emojis'
import { useVoiceRecorder } from './useVoiceRecorder'

const MAX_FILE_MB = 10

interface Props {
  onSendText: (text: string) => Promise<void> | void
  onSendFile: (file: File, opts: { voice?: boolean; duration?: number }) => Promise<void> | void
  onError: (message: string) => void
  placeholder?: string
  /** classes extras para o contêiner (ex.: borda) */
  className?: string
}

/** Campo de mensagem com emojis, anexo de arquivo/foto e gravação de voz. */
export default function ChatComposer({ onSendText, onSendFile, onError, placeholder = 'Aa', className }: Props) {
  const [text, setText] = useState('')
  const [emojis, setEmojis] = useState(false)
  const [group, setGroup] = useState(0)
  const [busy, setBusy] = useState(false)
  const area = useRef<HTMLTextAreaElement>(null)
  const fileRef = useRef<HTMLInputElement>(null)
  const voice = useVoiceRecorder()

  const run = async (fn: () => Promise<void> | void) => {
    setBusy(true)
    try { await fn() } catch (e) { onError(e instanceof Error ? e.message : 'Não foi possível enviar.') } finally { setBusy(false) }
  }

  const sendText = () => {
    const t = text.trim()
    if (!t || busy) return
    setText('')
    void run(async () => { try { await onSendText(t) } catch (e) { setText(t); throw e } })
  }

  const insertEmoji = (emoji: string) => {
    const el = area.current
    const [s, e] = el ? [el.selectionStart, el.selectionEnd] : [text.length, text.length]
    setText((v) => v.slice(0, s) + emoji + v.slice(e))
    requestAnimationFrame(() => { el?.focus(); el?.setSelectionRange(s + emoji.length, s + emoji.length) })
  }

  const pickFile = (f?: File) => {
    if (!f) return
    if (f.size > MAX_FILE_MB * 1024 * 1024) return onError(`O arquivo passa de ${MAX_FILE_MB} MB.`)
    void run(() => onSendFile(f, {}))
  }

  const sendVoice = async () => {
    const clip = await voice.stop()
    if (clip) void run(() => onSendFile(clip.file, { voice: true, duration: clip.seconds }))
  }

  if (voice.recording) {
    const mm = `${Math.floor(voice.seconds / 60)}:${String(voice.seconds % 60).padStart(2, '0')}`
    return (
      <div className={cn('flex items-center gap-2 border-t border-gray-100 bg-white p-2', className)} role="status" aria-label="Gravando áudio">
        <button type="button" onClick={voice.cancel} className="rounded-full p-2 text-gray-500 hover:bg-gray-100" aria-label="Descartar gravação"><Trash2 className="h-5 w-5" /></button>
        <span className="flex flex-1 items-center gap-2 text-sm text-gray-700"><span className="h-2.5 w-2.5 animate-pulse rounded-full bg-red-500" /> Gravando… {mm}</span>
        <button type="button" onClick={sendVoice} className="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-white hover:bg-brand-700" aria-label="Enviar áudio"><Send className="h-4 w-4" /></button>
      </div>
    )
  }

  return (
    <div className={cn('relative border-t border-gray-100 bg-white p-2', className)}>
      {emojis && (
        <div className="absolute bottom-full left-2 z-10 mb-1 w-72 max-w-[calc(100%-1rem)] rounded-xl border border-gray-100 bg-white p-2 shadow-xl" role="dialog" aria-label="Emojis">
          <div className="mb-1 flex gap-1 border-b border-gray-100 pb-1">
            {EMOJI_GROUPS.map((g, i) => (
              <button key={g.label} type="button" title={g.label} onClick={() => setGroup(i)} className={cn('rounded-lg px-2 py-1 text-lg', i === group ? 'bg-brand-50' : 'hover:bg-gray-100')}>{g.icon}</button>
            ))}
            <button type="button" onClick={() => setEmojis(false)} className="ml-auto rounded-lg p-1 text-gray-400 hover:bg-gray-100" aria-label="Fechar emojis"><X className="h-4 w-4" /></button>
          </div>
          <div className="grid max-h-40 grid-cols-8 gap-0.5 overflow-y-auto">
            {EMOJI_GROUPS[group].items.map((em) => (
              <button key={em} type="button" onClick={() => insertEmoji(em)} className="rounded-md p-1 text-xl hover:bg-gray-100" aria-label={em}>{em}</button>
            ))}
          </div>
        </div>
      )}
      {voice.error && <p className="px-2 pb-1 text-xs text-red-600" role="alert">{voice.error}</p>}
      <div className="flex items-end gap-1">
        <button type="button" onClick={() => fileRef.current?.click()} disabled={busy} className="rounded-full p-2 text-brand-600 hover:bg-brand-50 disabled:opacity-40" aria-label="Anexar foto ou arquivo" title="Anexar foto ou arquivo"><Paperclip className="h-5 w-5" /></button>
        <input ref={fileRef} type="file" className="hidden" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.odt,.ods" onChange={(e) => { pickFile(e.target.files?.[0]); e.target.value = '' }} />
        <button type="button" onClick={() => void voice.start()} disabled={busy} className="rounded-full p-2 text-brand-600 hover:bg-brand-50 disabled:opacity-40" aria-label="Gravar mensagem de voz" title="Gravar mensagem de voz"><Mic className="h-5 w-5" /></button>
        <textarea
          ref={area} value={text} rows={1} maxLength={5000} placeholder={placeholder}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendText() } }}
          className="max-h-24 min-h-[36px] flex-1 resize-none rounded-2xl border-0 bg-gray-100 px-3 py-2 text-sm focus:ring-1 focus:ring-brand-300"
        />
        <button type="button" onClick={() => setEmojis((v) => !v)} className="rounded-full p-2 text-brand-600 hover:bg-brand-50" aria-label="Emojis" aria-expanded={emojis} title="Emojis"><Smile className="h-5 w-5" /></button>
        <button type="button" onClick={sendText} disabled={!text.trim() || busy} className="rounded-full p-2 text-brand-600 hover:bg-brand-50 disabled:opacity-30" aria-label="Enviar"><Send className="h-5 w-5" /></button>
      </div>
    </div>
  )
}
