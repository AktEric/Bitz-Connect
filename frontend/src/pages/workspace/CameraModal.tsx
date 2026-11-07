import { useEffect, useRef, useState } from 'react'
import { Camera } from 'lucide-react'
import { Modal, btnGhost, btnPrimary } from './ui'

/** Reduz a imagem para no máximo 640px de largura e devolve um JPEG leve. */
async function toJpeg(source: CanvasImageSource, w: number, h: number): Promise<Blob | null> {
  const scale = Math.min(1, 640 / w)
  const canvas = document.createElement('canvas')
  canvas.width = Math.round(w * scale)
  canvas.height = Math.round(h * scale)
  canvas.getContext('2d')?.drawImage(source, 0, 0, canvas.width, canvas.height)
  return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.75))
}

/**
 * Captura uma foto no momento da marcação (câmera frontal). Sem câmera disponível,
 * oferece o seletor de arquivo do aparelho. Em modo opcional permite marcar sem foto.
 */
export default function CameraModal({
  required, onCapture, onSkip, onCancel,
}: {
  required: boolean
  onCapture: (photo: Blob) => void
  onSkip: () => void
  onCancel: () => void
}) {
  const videoRef = useRef<HTMLVideoElement>(null)
  const streamRef = useRef<MediaStream | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)
  const [ready, setReady] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    let cancelled = false
    if (!navigator.mediaDevices?.getUserMedia) {
      setError('Este navegador não permite usar a câmera. Escolha uma foto do aparelho.')
      return
    }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
      .then((stream) => {
        if (cancelled) { stream.getTracks().forEach((t) => t.stop()); return }
        streamRef.current = stream
        if (videoRef.current) {
          videoRef.current.srcObject = stream
          void videoRef.current.play().then(() => setReady(true)).catch(() => setReady(true))
        }
      })
      .catch(() => setError('Não foi possível acessar a câmera. Permita o acesso no navegador ou escolha uma foto do aparelho.'))
    return () => {
      cancelled = true
      streamRef.current?.getTracks().forEach((t) => t.stop())
    }
  }, [])

  const snap = async () => {
    const v = videoRef.current
    if (!v || !v.videoWidth) return
    const blob = await toJpeg(v, v.videoWidth, v.videoHeight)
    if (blob) onCapture(blob)
  }

  const pickFile = async (file: File | undefined) => {
    if (!file) return
    const bitmap = await createImageBitmap(file).catch(() => null)
    if (!bitmap) { setError('Não foi possível ler essa imagem.'); return }
    const blob = await toJpeg(bitmap, bitmap.width, bitmap.height)
    if (blob) onCapture(blob)
  }

  return (
    <Modal title="Foto para marcar o ponto" onClose={onCancel}>
      <div className="space-y-3">
        {!error && (
          <div className="relative aspect-[4/3] overflow-hidden rounded-2xl bg-gray-900">
            <video ref={videoRef} playsInline muted className="h-full w-full -scale-x-100 object-cover" />
            {!ready && <p className="absolute inset-0 flex items-center justify-center text-sm text-gray-300">Abrindo a câmera…</p>}
          </div>
        )}
        {error && <p className="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800" role="alert">{error}</p>}
        <input ref={fileRef} type="file" accept="image/*" capture="user" className="hidden" onChange={(e) => void pickFile(e.target.files?.[0])} />
        <div className="flex flex-wrap justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onCancel}>Cancelar</button>
          <button type="button" className={btnGhost} onClick={() => fileRef.current?.click()}>Escolher foto</button>
          {!required && <button type="button" className={btnGhost} onClick={onSkip}>Marcar sem foto</button>}
          {!error && <button type="button" className={btnPrimary} disabled={!ready} onClick={() => void snap()}><Camera className="h-4 w-4" /> Tirar foto e marcar</button>}
        </div>
        <p className="text-[11px] text-gray-400">A foto fica guardada de forma privada e só é vista por você, pelo seu gestor e pelo RH.</p>
      </div>
    </Modal>
  )
}
