import { useEffect, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Eye, EyeOff, Lock, Wallet } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Panel, Spinner, btnGhost, btnPrimary, errMsg, inputCls, labelCls } from './ui'

const AUTO_HIDE_MS = 30_000
const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

/**
 * Meu salário (só na própria página). Vem OCULTO: o valor nem é pedido ao servidor até a pessoa clicar no olho,
 * some de novo ao clicar outra vez, ao trocar de aba e após 30 s.
 */
export default function ProfileSalary({ slug }: { slug: string }) {
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'salary', 'status'], queryFn: () => workspaceService.salaryStatus(slug) })
  const [value, setValue] = useState<number | null | undefined>(undefined) // undefined = oculto
  const reveal = useMutation({ mutationFn: () => workspaceService.revealSalary(slug), onSuccess: (r) => setValue(r.salary) })
  const shown = value !== undefined

  useEffect(() => {
    if (!shown) return
    const t = setTimeout(() => setValue(undefined), AUTO_HIDE_MS)
    return () => clearTimeout(t)
  }, [shown])

  if (isLoading || !data) return <Spinner />

  return (
    <Panel>
      <h2 className="mb-1 flex items-center gap-2 font-semibold text-gray-900"><Wallet className="h-4 w-4 text-brand-500" /> Meu salário</h2>
      <p className="mb-4 flex items-center gap-1.5 text-xs text-gray-500"><Lock className="h-3 w-3" /> Só você vê esta informação. Ela fica oculta até você pedir.</p>

      <div className="flex items-center justify-between gap-3 rounded-2xl bg-gray-50 px-4 py-4">
        <div>
          <p className="text-xs text-gray-500">Salário mensal bruto</p>
          <p className="mt-1 text-2xl font-bold tabular-nums text-gray-900" aria-live="polite">
            {!shown ? 'R$ ••••••' : value === null ? 'Ainda não cadastrado' : brl(value)}
          </p>
        </div>
        {data.configured ? (
          <button
            type="button" className={btnGhost} disabled={reveal.isPending}
            aria-pressed={shown} aria-label={shown ? 'Ocultar salário' : 'Mostrar salário'}
            onClick={() => (shown ? setValue(undefined) : reveal.mutate())}
          >
            {shown ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />} {shown ? 'Ocultar' : 'Mostrar'}
          </button>
        ) : <span className="text-xs text-gray-400">O RH ainda não cadastrou</span>}
      </div>
      {reveal.error && <p className="mt-2 text-sm text-red-600" role="alert">{errMsg(reveal.error)}</p>}
      {shown && <p className="mt-2 text-[11px] text-gray-400">O valor some sozinho em 30 segundos.</p>}
    </Panel>
  )
}

/** RH/dono: define o salário de alguém. Só grava; o valor atual nunca é mostrado aqui. */
export function SalaryAdminField({ slug, uuid }: { slug: string; uuid: string }) {
  const [text, setText] = useState('')
  const [msg, setMsg] = useState('')
  const save = useMutation({
    mutationFn: () => workspaceService.setSalary(slug, uuid, text.trim() === '' ? null : Number(text.replace(/\./g, '').replace(',', '.'))),
    onSuccess: (r) => { setText(''); setMsg(r.configured ? 'Salário salvo.' : 'Salário removido.') },
    onError: (e) => setMsg(errMsg(e)),
  })

  return (
    <fieldset>
      <legend className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">Salário</legend>
      <p className="mb-2 text-xs text-gray-400">Somente a própria pessoa consegue ver. Aqui você só define o valor (vazio remove).</p>
      <div className="flex items-end gap-2">
        <div className="flex-1">
          <label className={labelCls} htmlFor="salary-set">Novo valor mensal (R$)</label>
          <input id="salary-set" inputMode="decimal" autoComplete="off" className={inputCls} placeholder="Ex.: 4500,00" value={text} onChange={(e) => setText(e.target.value)} />
        </div>
        <button type="button" className={btnPrimary} disabled={save.isPending || Number.isNaN(Number(text.replace(/\./g, '').replace(',', '.')))} onClick={() => save.mutate()}>Salvar</button>
      </div>
      {msg && <p className="mt-1 text-sm text-gray-500" role="status">{msg}</p>}
    </fieldset>
  )
}
