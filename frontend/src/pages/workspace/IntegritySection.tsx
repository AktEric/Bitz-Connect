import { useMutation } from '@tanstack/react-query'
import { ShieldCheck } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import { Panel, Pill, btnGhost, errMsg } from './ui'

/** Confere a cadeia de marcações: acusa se algum registro foi alterado ou apagado no banco. */
export default function IntegritySection({ slug }: { slug: string }) {
  const check = useMutation({ mutationFn: () => workspaceService.timeIntegrity(slug) })
  const r = check.data

  return (
    <section className="mb-6">
      <h2 className="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-900"><ShieldCheck size={16} /> Integridade do ponto</h2>
      <Panel>
        <p className="text-sm text-gray-600">Cada marcação recebe um número sequencial (NSR) e um código encadeado. Se alguém alterar ou apagar um registro direto no banco, a verificação aponta onde.</p>
        <div className="mt-3 flex flex-wrap items-center gap-3">
          <button type="button" className={btnGhost} disabled={check.isPending} onClick={() => check.mutate()}>{check.isPending ? 'Verificando…' : 'Verificar agora'}</button>
          {r && (r.ok
            ? <Pill tone="green">Íntegro — {r.checked} marcações conferidas</Pill>
            : <Pill tone="red">Problema no NSR {r.first_broken_nsr}: {r.reason}</Pill>)}
          {check.error && <span className="text-sm text-red-600">{errMsg(check.error)}</span>}
        </div>
      </Panel>
    </section>
  )
}
