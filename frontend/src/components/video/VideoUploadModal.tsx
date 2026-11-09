import { useState } from 'react'
import { X, UploadCloud, Film } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { useCreateVideo } from '@/hooks/useVideos'
import { useToast } from '@/contexts/ToastContext'

const MAX_DURATION_SECONDS = 30

interface VideoUploadModalProps {
  open: boolean
  onClose: () => void
}

const CATEGORIES = [
  'Atendimento', 'Gestão', 'Gastronomia', 'Governança',
  'Eventos', 'Marketing', 'Carreira', 'Outros',
]

/** Lê a duração (segundos) de um arquivo de vídeo no browser. */
function readDuration(file: File): Promise<number | undefined> {
  return new Promise((resolve) => {
    const el = document.createElement('video')
    el.preload = 'metadata'
    el.onloadedmetadata = () => {
      URL.revokeObjectURL(el.src)
      resolve(Number.isFinite(el.duration) ? Math.round(el.duration) : undefined)
    }
    el.onerror = () => resolve(undefined)
    el.src = URL.createObjectURL(file)
  })
}

/**
 * Formulário de publicação de vídeo (Consultor PRO). Só é aberto quando o
 * usuário já passou pelo gate useCan('publish_videos').
 * Sprint: monetization-hotelconnect-pro (vertical de Vídeos)
 */
export function VideoUploadModal({ open, onClose }: VideoUploadModalProps) {
  const createVideo = useCreateVideo()
  const { error } = useToast()
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [category, setCategory] = useState('')
  const [tagsInput, setTagsInput] = useState('')
  const [videoFile, setVideoFile] = useState<File | null>(null)
  const [duration, setDuration] = useState<number | undefined>(undefined)
  const [thumbFile, setThumbFile] = useState<File | null>(null)

  function reset() {
    setTitle(''); setDescription(''); setCategory(''); setTagsInput('')
    setVideoFile(null); setDuration(undefined); setThumbFile(null)
  }

  async function handleVideoSelect(file: File | null) {
    if (!file) { setVideoFile(null); setDuration(undefined); return }
    const secs = await readDuration(file)
    if (secs != null && secs > MAX_DURATION_SECONDS) {
      error(`Os vídeos podem ter no máximo ${MAX_DURATION_SECONDS} segundos (este tem ${secs}s).`)
      setVideoFile(null); setDuration(undefined)
      return
    }
    setVideoFile(file)
    setDuration(secs)
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!videoFile || !title.trim()) return

    if (duration == null) {
      error('Não foi possível ler a duração do vídeo. Tente outro arquivo.')
      return
    }

    const tags = tagsInput.split(',').map((t) => t.trim()).filter(Boolean).slice(0, 10)

    createVideo.mutate(
      {
        title: title.trim(),
        description: description.trim() || undefined,
        category: category || undefined,
        tags,
        duration_seconds: duration,
        video: videoFile,
        thumbnail: thumbFile,
      },
      { onSuccess: () => { reset(); onClose() } },
    )
  }

  if (!open) return null

  return (
    <>
      <div className="fixed inset-0 z-50 bg-black/50" onClick={onClose} aria-hidden="true" />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          role="dialog"
          aria-modal="true"
          className="relative flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl"
          onClick={(e) => e.stopPropagation()}
        >
          <div className="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
            <h2 className="flex items-center gap-2 text-base font-semibold text-gray-900">
              <Film className="h-4 w-4 text-primary" /> Publicar vídeo
            </h2>
            <button onClick={onClose} aria-label="Fechar" className="rounded-full p-1 text-gray-400 hover:bg-gray-100">
              <X className="h-4 w-4" />
            </button>
          </div>

          <form onSubmit={handleSubmit} className="flex-1 space-y-4 overflow-y-auto p-5">
            {/* Arquivo de vídeo */}
            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">Arquivo de vídeo</label>
              <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-gray-300 px-3 py-3 text-sm text-gray-500 hover:border-primary">
                <UploadCloud className="h-5 w-5" />
                <span className="truncate">
                  {videoFile ? `${videoFile.name}${duration != null ? ` · ${duration}s` : ''}` : 'Selecionar MP4, WebM ou MOV — até 30 segundos'}
                </span>
                <input
                  type="file"
                  accept="video/mp4,video/webm,video/quicktime"
                  className="hidden"
                  onChange={(e) => handleVideoSelect(e.target.files?.[0] ?? null)}
                />
              </label>
            </div>

            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">Título</label>
              <input
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                maxLength={255}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                placeholder="Ex: Como encantar hóspedes na recepção"
              />
            </div>

            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">Descrição</label>
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                rows={3}
                maxLength={5000}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                placeholder="Sobre o que é este vídeo?"
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">Categoria</label>
                <select
                  value={category}
                  onChange={(e) => setCategory(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                >
                  <option value="">Selecionar…</option>
                  {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
                </select>
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-gray-700">Tags</label>
                <input
                  value={tagsInput}
                  onChange={(e) => setTagsInput(e.target.value)}
                  className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                  placeholder="separadas por vírgula"
                />
              </div>
            </div>

            <div>
              <label className="mb-1 block text-sm font-medium text-gray-700">
                Miniatura <span className="text-xs text-gray-400">(opcional)</span>
              </label>
              <input
                type="file"
                accept="image/*"
                onChange={(e) => setThumbFile(e.target.files?.[0] ?? null)}
                className="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary hover:file:bg-brand-100"
              />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
              <Button type="submit" loading={createVideo.isPending} disabled={!videoFile || !title.trim()}>
                Publicar
              </Button>
            </div>
          </form>
        </div>
      </div>
    </>
  )
}
