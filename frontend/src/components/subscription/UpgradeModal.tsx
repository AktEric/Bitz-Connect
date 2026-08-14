import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Sparkles, Check, X } from 'lucide-react'
import { Button } from '@/components/ui/Button'

interface UpgradeModalProps {
  open: boolean
  onClose: () => void
  /** Headline describing the locked feature, e.g. "Publique vídeos profissionais". */
  title?: string
  description?: string
  /** Bullet list of what the PRO plan unlocks. */
  highlights?: string[]
}

const DEFAULT_HIGHLIGHTS = [
  'Publicação de vídeos profissionais',
  'Página exclusiva de criador',
  'Analytics e estatísticas de conteúdo',
  'Selo PRO e destaque na busca',
]

/**
 * Elegant upgrade prompt shown when a free user hits a PRO feature (§13).
 * Never merely blocks — it explains the benefits and routes to the plans page.
 */
export function UpgradeModal({
  open,
  onClose,
  title = 'Este recurso é do ConnectHotel PRO',
  description = 'Desbloqueie ferramentas profissionais para crescer na plataforma.',
  highlights = DEFAULT_HIGHLIGHTS,
}: UpgradeModalProps) {
  const navigate = useNavigate()

  useEffect(() => {
    if (!open) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [open, onClose])

  if (!open) return null

  return (
    <>
      <div className="fixed inset-0 z-50 bg-black/50" aria-hidden="true" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby="upgrade-modal-title"
          className="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl"
          onClick={(e) => e.stopPropagation()}
        >
          <button
            onClick={onClose}
            aria-label="Fechar"
            className="absolute right-3 top-3 z-10 rounded-full p-1 text-white/80 hover:bg-white/20 hover:text-white"
          >
            <X className="h-4 w-4" />
          </button>

          {/* Gradient header */}
          <div className="bg-gradient-to-br from-amber-400 to-amber-600 px-6 py-7 text-white">
            <span className="inline-flex items-center gap-1 rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-semibold">
              <Sparkles className="h-3 w-3" /> ConnectHotel PRO
            </span>
            <h2 id="upgrade-modal-title" className="mt-3 text-xl font-semibold">
              {title}
            </h2>
            <p className="mt-1 text-sm text-white/90">{description}</p>
          </div>

          <div className="px-6 py-5">
            <ul className="space-y-2.5">
              {highlights.map((h) => (
                <li key={h} className="flex items-start gap-2 text-sm text-gray-700">
                  <Check className="mt-0.5 h-4 w-4 flex-shrink-0 text-amber-500" />
                  {h}
                </li>
              ))}
            </ul>

            <div className="mt-6 flex flex-col gap-2">
              <Button
                fullWidth
                icon={<Sparkles className="h-4 w-4" />}
                onClick={() => {
                  onClose()
                  navigate('/plans')
                }}
              >
                Conhecer ConnectHotel PRO
              </Button>
              <Button variant="ghost" fullWidth onClick={onClose}>
                Agora não
              </Button>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}
