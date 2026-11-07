import { useEffect, useRef, useState } from 'react'
import { ShieldCheck } from 'lucide-react'
import { registerTwoFactorRequester, type TwoFactorAnswer } from '@/services/twoFactorLogin'

interface Pending { message?: string; resolve: (a: TwoFactorAnswer | null) => void }

/**
 * Caixa de diálogo global da segunda etapa do login. O serviço de login pede o código quando o servidor responde
 * "two_factor_required"; assim todas as telas de login (pessoa, funcionário, empresa) funcionam sem alteração.
 */
export default function TwoFactorPrompt() {
  const [pending, setPending] = useState<Pending | null>(null)
  const [code, setCode] = useState('')
  const [recovery, setRecovery] = useState(false)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    registerTwoFactorRequester((message) => new Promise((resolve) => {
      setCode('')
      setPending({ message, resolve })
    }))
    return () => registerTwoFactorRequester(null)
  }, [])

  useEffect(() => { if (pending) setTimeout(() => inputRef.current?.focus(), 50) }, [pending, recovery])

  if (!pending) return null

  const finish = (answer: TwoFactorAnswer | null) => { pending.resolve(answer); setPending(null) }
  const clean = recovery ? code.trim() : code.replace(/\s/g, '')
  const ok = recovery ? clean.length >= 8 : /^\d{6}$/.test(clean)

  return (
    <div className="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="tf-title">
      <form
        className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl"
        onSubmit={(e) => { e.preventDefault(); if (ok) finish(recovery ? { recovery_code: clean } : { code: clean }) }}
      >
        <div className="mb-3 flex items-center gap-2">
          <span className="rounded-full bg-violet-50 p-2 text-violet-600"><ShieldCheck className="h-5 w-5" /></span>
          <h2 id="tf-title" className="text-lg font-semibold text-gray-900">Verificação em duas etapas</h2>
        </div>
        <p className="mb-3 text-sm text-gray-600">
          {recovery ? 'Digite um dos seus códigos de recuperação. Cada código vale uma única vez.' : 'Digite o código de 6 dígitos do seu aplicativo autenticador.'}
        </p>
        <input
          ref={inputRef}
          value={code}
          onChange={(e) => setCode(e.target.value)}
          inputMode={recovery ? 'text' : 'numeric'}
          autoComplete="one-time-code"
          maxLength={recovery ? 20 : 7}
          placeholder={recovery ? 'xxxxx-xxxxx' : '000000'}
          aria-label={recovery ? 'Código de recuperação' : 'Código de 6 dígitos'}
          className="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-center text-lg tracking-widest focus:border-violet-400 focus:outline-none focus:ring-2 focus:ring-violet-100"
        />
        {pending.message && <p className="mt-2 text-sm text-red-600" role="alert">{pending.message}</p>}
        <button type="submit" disabled={!ok} className="mt-4 w-full rounded-xl bg-violet-600 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50">Entrar</button>
        <div className="mt-3 flex items-center justify-between text-xs">
          <button type="button" className="text-violet-700 hover:underline" onClick={() => { setRecovery((r) => !r); setCode('') }}>
            {recovery ? 'Usar o aplicativo' : 'Usar código de recuperação'}
          </button>
          <button type="button" className="text-gray-500 hover:underline" onClick={() => finish(null)}>Cancelar</button>
        </div>
      </form>
    </div>
  )
}
