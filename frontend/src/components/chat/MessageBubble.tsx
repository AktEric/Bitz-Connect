import { differenceInHours, formatDistanceToNow, format } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { ImageIcon, FileIcon } from 'lucide-react'
import { Avatar } from '@/components/ui/Avatar'
import { JobShareCard } from '@/components/chat/JobShareCard'
import type { Message, JobMetadata } from '@/types/api.types'

interface MessageBubbleProps {
  msg: Message
}

function formatMessageTime(iso: string): string {
  const msgDate = new Date(iso)
  const hoursDiff = differenceInHours(new Date(), msgDate)

  if (hoursDiff < 24) {
    return formatDistanceToNow(msgDate, { addSuffix: true, locale: ptBR })
  }
  return format(msgDate, 'dd/MM/yyyy', { locale: ptBR })
}

export function MessageBubble({ msg }: MessageBubbleProps) {
  const isJob      = msg.type === 'job'
  const isImgStub  = msg.type === 'img-stub'
  const isFileStub = msg.type === 'file-stub'
  const isText     = msg.type === 'text' || !msg.type

  // Unread highlight: received, not yet read
  const receivedBg = !msg.is_mine && !msg.read_at ? 'bg-blue-50' : 'bg-gray-100'

  const bubbleClass = msg.is_mine
    ? 'bg-primary text-white rounded-br-sm'
    : `${receivedBg} text-gray-900 rounded-bl-sm`

  // Job cards get wider treatment; text/stub use the standard max-width
  const maxWidthClass = isJob ? 'max-w-[320px] w-full' : 'max-w-[70%]'

  const timeColor = msg.is_mine ? 'text-white/70' : 'text-gray-400'

  return (
    <div className={`flex items-end gap-2 ${msg.is_mine ? 'justify-end' : 'justify-start'}`}>
      {/* Sender avatar for received messages */}
      {!msg.is_mine && (
        <Avatar
          src={msg.sender?.avatar_url ?? null}
          name={msg.sender?.name ?? '?'}
          size="sm"
          className="mb-0.5 flex-shrink-0"
        />
      )}

      <div className={`${maxWidthClass} rounded-2xl px-3 py-2 break-words ${bubbleClass}`}>
        {/* ── Job card ───────────────────────────────────────────── */}
        {isJob && (
          <JobShareCard
            metadata={msg.metadata as JobMetadata}
            isMine={msg.is_mine}
          />
        )}

        {/* ── Image stub ─────────────────────────────────────────── */}
        {isImgStub && (
          <div
            className={`flex items-center gap-2 text-sm py-1 ${
              msg.is_mine ? 'text-white/80' : 'text-gray-500'
            }`}
          >
            <ImageIcon className="h-4 w-4 flex-shrink-0" />
            <span>Imagem em breve</span>
          </div>
        )}

        {/* ── File stub ──────────────────────────────────────────── */}
        {isFileStub && (
          <div
            className={`flex items-center gap-2 text-sm py-1 ${
              msg.is_mine ? 'text-white/80' : 'text-gray-500'
            }`}
          >
            <FileIcon className="h-4 w-4 flex-shrink-0" />
            <span>Arquivo em breve</span>
          </div>
        )}

        {/* ── Plain text ─────────────────────────────────────────── */}
        {isText && (
          <p className="text-sm whitespace-pre-wrap">{msg.content}</p>
        )}

        {/* ── Timestamp ──────────────────────────────────────────── */}
        <p className={`text-[10px] mt-1 text-right ${timeColor}`}>
          {formatMessageTime(msg.created_at)}
        </p>
      </div>
    </div>
  )
}
