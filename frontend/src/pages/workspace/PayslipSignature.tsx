import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Eraser, PenLine, Printer, Type } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { Payslip } from '@/types/workspace.types'
import { Modal, Spinner, btnGhost, btnPrimary, errMsg, inputCls, labelCls } from './ui'

const CURSIVE = '"Brush Script MT", "Segoe Script", "Lucida Handwriting", cursive'
const fmtDateTime = (iso: string) => new Date(iso).toLocaleString('pt-BR', { dateStyle: 'long', timeStyle: 'short', timeZone: 'America/Sao_Paulo' })

/** Quadro de assinatura à mão (mouse, caneta ou dedo). Devolve o PNG em data URL, ou null se estiver vazio. */
function SignaturePad({ onChange }: { onChange: (png: string | null) => void }) {
  const ref = useRef<HTMLCanvasElement>(null)
  const drawing = useRef(false)
  const strokes = useRef(0)
  const W = 480
  const H = 160

  useEffect(() => {
    const c = ref.current
    if (!c) return
    const dpr = Math.min(window.devicePixelRatio || 1, 3)
    c.width = W * dpr
    c.height = H * dpr
    const ctx = c.getContext('2d')!
    ctx.scale(dpr, dpr)
    ctx.lineWidth = 2.4
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
    ctx.strokeStyle = '#111827'
  }, [])

  const pos = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const r = e.currentTarget.getBoundingClientRect()
    return { x: ((e.clientX - r.left) / r.width) * W, y: ((e.clientY - r.top) / r.height) * H }
  }
  const down = (e: React.PointerEvent<HTMLCanvasElement>) => {
    e.currentTarget.setPointerCapture(e.pointerId)
    drawing.current = true
    const { x, y } = pos(e)
    const ctx = e.currentTarget.getContext('2d')!
    ctx.beginPath()
    ctx.moveTo(x, y)
    ctx.lineTo(x + 0.01, y + 0.01)
    ctx.stroke()
  }
  const move = (e: React.PointerEvent<HTMLCanvasElement>) => {
    if (!drawing.current) return
    const { x, y } = pos(e)
    const ctx = e.currentTarget.getContext('2d')!
    ctx.lineTo(x, y)
    ctx.stroke()
  }
  const up = () => {
    if (!drawing.current) return
    drawing.current = false
    strokes.current += 1
    onChange(ref.current && strokes.current > 0 ? ref.current.toDataURL('image/png') : null)
  }
  const clear = () => {
    const c = ref.current!
    c.getContext('2d')!.clearRect(0, 0, W, H)
    strokes.current = 0
    onChange(null)
  }

  return (
    <div>
      <canvas
        ref={ref} style={{ touchAction: 'none', width: '100%', aspectRatio: `${W} / ${H}` }}
        className="cursor-crosshair rounded-xl border border-dashed border-gray-300 bg-white"
        onPointerDown={down} onPointerMove={move} onPointerUp={up} onPointerCancel={up}
        aria-label="Área de assinatura: desenhe com o mouse, caneta ou dedo"
      />
      <div className="mt-1 flex items-center justify-between text-xs text-gray-400">
        <span>Desenhe sua assinatura acima.</span>
        <button type="button" className="inline-flex items-center gap-1 hover:text-gray-700" onClick={clear}><Eraser size={12} /> Limpar</button>
      </div>
    </div>
  )
}

/** A própria pessoa assina o documento: desenhando ou digitando o nome completo. */
export function SignModal({ slug, payslip, userName, onClose }: { slug: string; payslip: Payslip; userName: string; onClose: () => void }) {
  const qc = useQueryClient()
  const [mode, setMode] = useState<'drawn' | 'typed'>('drawn')
  const [png, setPng] = useState<string | null>(null)
  const [typed, setTyped] = useState('')
  const [accept, setAccept] = useState(false)
  const [error, setError] = useState('')

  const sign = useMutation({
    mutationFn: () => workspaceService.signPayslip(slug, payslip.uuid, mode === 'drawn' ? { kind: 'drawn', image: png! } : { kind: 'typed', name: typed.trim() }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'payslips'] }); onClose() },
    onError: (e) => setError(errMsg(e)),
  })
  const ready = accept && (mode === 'drawn' ? !!png : typed.trim().length >= 3)

  return (
    <Modal title={`Assinar: ${payslip.title}`} onClose={onClose}>
      <div className="space-y-3">
        <div role="tablist" aria-label="Forma de assinar" className="inline-flex rounded-xl bg-gray-100 p-1">
          {([['drawn', 'Desenhar', PenLine], ['typed', 'Digitar o nome', Type]] as const).map(([v, label, Icon]) => (
            <button key={v} type="button" role="tab" aria-selected={mode === v} onClick={() => setMode(v)}
              className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium ${mode === v ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'}`}>
              <Icon size={14} /> {label}
            </button>
          ))}
        </div>

        {mode === 'drawn' ? <SignaturePad onChange={setPng} /> : (
          <div>
            <label className={labelCls} htmlFor="sig-name">Digite seu nome completo, como está cadastrado ({userName})</label>
            <input id="sig-name" className={inputCls} autoComplete="off" value={typed} onChange={(e) => setTyped(e.target.value)} maxLength={160} />
            <div className="mt-2 flex h-20 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white px-3 text-3xl text-gray-900" style={{ fontFamily: CURSIVE }} aria-hidden>
              {typed || <span className="text-base text-gray-300" style={{ fontFamily: 'inherit' }}>Prévia da assinatura</span>}
            </div>
          </div>
        )}

        <label className="flex cursor-pointer items-start gap-2 text-sm text-gray-700">
          <input type="checkbox" className="mt-0.5 rounded border-gray-300 text-brand-600" checked={accept} onChange={(e) => setAccept(e.target.checked)} />
          <span>Declaro que recebi e conferi este documento.<br /><span className="text-xs text-gray-400">A assinatura registra data, hora e uma impressão digital do arquivo, e não pode ser alterada depois.</span></span>
        </label>
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>Cancelar</button>
          <button type="button" className={btnPrimary} disabled={!ready || sign.isPending} onClick={() => sign.mutate()}>Assinar documento</button>
        </div>
      </div>
    </Modal>
  )
}

/** Comprovante da assinatura (dono do documento e RH), pronto para imprimir. */
export function SignatureProofModal({ slug, payslip, onClose }: { slug: string; payslip: Payslip; onClose: () => void }) {
  const { data, isLoading, error } = useQuery({ queryKey: ['ws', slug, 'payslips', payslip.uuid, 'signature'], queryFn: () => workspaceService.payslipSignature(slug, payslip.uuid) })

  return (
    <Modal title="Comprovante de assinatura" onClose={onClose}>
      {isLoading ? <Spinner /> : error || !data ? <p className="text-sm text-red-600">{errMsg(error)}</p> : (
        <div id="signature-proof" className="space-y-3">
          <p className="text-sm text-gray-500">Documento</p>
          <p className="-mt-2 font-semibold text-gray-900">{data.title}</p>
          <div className="flex min-h-24 items-center justify-center rounded-xl border border-gray-200 bg-white p-3">
            {data.image
              ? <img src={data.image} alt={`Assinatura de ${data.name}`} className="max-h-28" />
              : <span className="text-3xl text-gray-900" style={{ fontFamily: CURSIVE }}>{data.name}</span>}
          </div>
          <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
            <dt className="text-gray-500">Assinado por</dt><dd className="text-gray-900">{data.name}</dd>
            <dt className="text-gray-500">Em</dt><dd className="text-gray-900">{fmtDateTime(data.signed_at)}</dd>
            <dt className="text-gray-500">Forma</dt><dd className="text-gray-900">{data.kind === 'drawn' ? 'Desenhada' : 'Nome digitado'}</dd>
            <dt className="text-gray-500">Impressão digital</dt><dd className="break-all font-mono text-[11px] text-gray-600">{data.hash}</dd>
          </dl>
          <div className="flex justify-end">
            <button type="button" className={btnGhost} onClick={() => window.print()}><Printer size={14} /> Imprimir</button>
          </div>
        </div>
      )}
    </Modal>
  )
}
