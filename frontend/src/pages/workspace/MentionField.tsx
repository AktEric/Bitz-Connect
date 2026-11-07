import { useMemo, useRef, useState, type KeyboardEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import workspaceService from '@/services/workspace.service'
import { Avatar } from '@/components/ui/Avatar'
import { cn } from '@/utils/cn'
import { inputCls } from './ui'

export interface Mention { name: string; uuid: string }

const MENTION_RE = /@\[([^\]\n]{1,80})\]\(([0-9a-fA-F-]{36})\)/g

const plain = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/** Troca "@Nome" (como a pessoa vê) pela marcação @[Nome](uuid) que o servidor entende. Maiores nomes primeiro. */
export function applyMentions(text: string, mentions: Mention[]): string {
  let out = text
  for (const m of [...mentions].sort((a, b) => b.name.length - a.name.length)) {
    out = out.split(`@${m.name}`).join(`@[${m.name}](${m.uuid})`)
  }
  return out
}

/** Exibe o texto com as menções destacadas; o nome mostrado vem do servidor (mapa uuid → nome). */
export function MentionText({ text, names, className }: { text: string; names?: Record<string, string>; className?: string }) {
  const parts: (string | { uuid: string; label: string; known: boolean })[] = []
  let last = 0
  for (const m of text.matchAll(MENTION_RE)) {
    const idx = m.index ?? 0
    if (idx > last) parts.push(text.slice(last, idx))
    const real = names?.[m[2].toLowerCase()]
    parts.push({ uuid: m[2], label: real ?? m[1], known: !!real })
    last = idx + m[0].length
  }
  if (last < text.length) parts.push(text.slice(last))

  return (
    <p className={className}>
      {parts.map((p, i) => typeof p === 'string'
        ? <span key={i}>{p}</span>
        : <span key={i} className={p.known ? 'font-medium text-violet-700' : 'text-gray-500'}>@{p.label}</span>)}
    </p>
  )
}

/**
 * Campo de texto com sugestão de pessoas ao digitar "@". `onChange` devolve o texto como a pessoa vê e a lista
 * de pessoas escolhidas; use applyMentions() ao enviar.
 */
export function MentionField({
  slug, value, onChange, mentions, onMentions, placeholder, rows = 3, maxLength = 3000, onEnter, className,
}: {
  slug: string
  value: string
  onChange: (v: string) => void
  mentions: Mention[]
  onMentions: (m: Mention[]) => void
  placeholder?: string
  rows?: number
  maxLength?: number
  onEnter?: () => void
  className?: string
}) {
  const ref = useRef<HTMLTextAreaElement>(null)
  const [query, setQuery] = useState<string | null>(null)
  const [active, setActive] = useState(0)
  const { data: members } = useQuery({ queryKey: ['ws', slug, 'members'], queryFn: () => workspaceService.members(slug), staleTime: 300_000 })

  const options = useMemo(() => {
    if (query === null || !members) return []
    const q = plain(query)
    return members.filter((m) => plain(m.name).includes(q)).slice(0, 6)
  }, [query, members])

  const detect = (text: string, caret: number) => {
    const before = text.slice(0, caret)
    const m = /(?:^|\s)@([\p{L}\p{M}'\- ]{0,30})$/u.exec(before)
    setQuery(m ? m[1] : null)
    setActive(0)
  }

  const pick = (name: string, uuid: string) => {
    const el = ref.current
    const caret = el?.selectionStart ?? value.length
    const before = value.slice(0, caret)
    const start = before.lastIndexOf('@')
    const next = `${value.slice(0, start)}@${name} ${value.slice(caret)}`
    onChange(next)
    if (!mentions.some((m) => m.uuid === uuid)) onMentions([...mentions, { name, uuid }])
    setQuery(null)
    requestAnimationFrame(() => { el?.focus(); const pos = start + name.length + 2; el?.setSelectionRange(pos, pos) })
  }

  const onKey = (e: KeyboardEvent<HTMLTextAreaElement>) => {
    if (options.length) {
      if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => (a + 1) % options.length); return }
      if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => (a - 1 + options.length) % options.length); return }
      if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); pick(options[active].name, options[active].uuid); return }
      if (e.key === 'Escape') { setQuery(null); return }
    }
    if (onEnter && e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); onEnter() }
  }

  return (
    <div className="relative">
      <textarea
        ref={ref}
        className={cn(inputCls, className)}
        rows={rows}
        maxLength={maxLength}
        value={value}
        placeholder={placeholder}
        onChange={(e) => { onChange(e.target.value); detect(e.target.value, e.target.selectionStart) }}
        onKeyDown={onKey}
        onBlur={() => setTimeout(() => setQuery(null), 150)}
      />
      {options.length > 0 && (
        <ul className="absolute left-0 right-0 top-full z-20 mt-1 max-h-60 overflow-auto rounded-xl border border-gray-100 bg-white p-1 shadow-lg" role="listbox" aria-label="Mencionar pessoa">
          {options.map((m, i) => (
            <li key={m.uuid} role="option" aria-selected={i === active}>
              <button
                type="button"
                onMouseDown={(e) => { e.preventDefault(); pick(m.name, m.uuid) }}
                className={cn('flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm', i === active ? 'bg-violet-50' : 'hover:bg-gray-50')}
              >
                <Avatar src={m.avatar_url} name={m.name} size="xs" />
                <span className="font-medium text-gray-900">{m.name}</span>
                {m.job_title && <span className="truncate text-xs text-gray-400">{m.job_title}</span>}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
