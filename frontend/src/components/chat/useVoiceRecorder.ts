import { useCallback, useEffect, useRef, useState } from 'react'

export const MAX_VOICE_SECONDS = 300

const MIME_CANDIDATES = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4']

export interface VoiceClip { file: File; seconds: number }

/**
 * Gravação de mensagem de voz (MediaRecorder). `start()` pede o microfone; `stop()` entrega o arquivo;
 * `cancel()` descarta. O microfone é sempre liberado ao parar, cancelar ou desmontar.
 */
export function useVoiceRecorder() {
  const [recording, setRecording] = useState(false)
  const [seconds, setSeconds] = useState(0)
  const [error, setError] = useState('')
  const rec = useRef<MediaRecorder | null>(null)
  const stream = useRef<MediaStream | null>(null)
  const chunks = useRef<Blob[]>([])
  const timer = useRef<ReturnType<typeof setInterval> | null>(null)
  const startedAt = useRef(0)
  const resolver = useRef<((clip: VoiceClip | null) => void) | null>(null)
  const discard = useRef(false)

  const release = useCallback(() => {
    if (timer.current) clearInterval(timer.current)
    timer.current = null
    stream.current?.getTracks().forEach((t) => t.stop())
    stream.current = null
    setRecording(false)
  }, [])

  const finish = useCallback(() => {
    const mime = rec.current?.mimeType || 'audio/webm'
    const ext = mime.includes('ogg') ? 'ogg' : mime.includes('mp4') ? 'm4a' : 'webm'
    const secs = Math.max(1, Math.round((Date.now() - startedAt.current) / 1000))
    const clip = discard.current || !chunks.current.length ? null : { file: new File(chunks.current, `voz.${ext}`, { type: mime }), seconds: secs }
    release()
    resolver.current?.(clip)
    resolver.current = null
  }, [release])

  const start = useCallback(async () => {
    setError('')
    if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
      setError('Seu navegador não permite gravar áudio.')
      return false
    }
    try {
      stream.current = await navigator.mediaDevices.getUserMedia({ audio: true })
    } catch {
      setError('Permita o uso do microfone no navegador para gravar.')
      return false
    }
    const mimeType = MIME_CANDIDATES.find((m) => MediaRecorder.isTypeSupported(m))
    chunks.current = []
    discard.current = false
    rec.current = new MediaRecorder(stream.current, mimeType ? { mimeType } : undefined)
    rec.current.ondataavailable = (e) => { if (e.data.size) chunks.current.push(e.data) }
    rec.current.onstop = finish
    rec.current.start()
    startedAt.current = Date.now()
    setSeconds(0)
    setRecording(true)
    timer.current = setInterval(() => {
      const s = Math.round((Date.now() - startedAt.current) / 1000)
      setSeconds(s)
      if (s >= MAX_VOICE_SECONDS) rec.current?.state === 'recording' && rec.current.stop()
    }, 250)
    return true
  }, [finish])

  const stop = useCallback(() => new Promise<VoiceClip | null>((resolve) => {
    if (!rec.current || rec.current.state !== 'recording') return resolve(null)
    resolver.current = resolve
    rec.current.stop()
  }), [])

  const cancel = useCallback(() => {
    discard.current = true
    if (rec.current?.state === 'recording') rec.current.stop()
    else release()
  }, [release])

  useEffect(() => () => { discard.current = true; if (rec.current?.state === 'recording') rec.current.stop(); else release() }, [release])

  return { recording, seconds, error, start, stop, cancel }
}
