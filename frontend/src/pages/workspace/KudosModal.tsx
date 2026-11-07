import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import workspaceService from '@/services/workspace.service'
import { useAuth } from '@/hooks/useAuth'
import { Modal, btnGhost, btnPrimary, errMsg, inputCls, labelCls } from './ui'

export const BADGES: Record<string, { label: string; emoji: string }> = {
  teamwork: { label: 'Trabalho em equipe', emoji: '🤝' },
  innovation: { label: 'Inovação', emoji: '💡' },
  service: { label: 'Atendimento', emoji: '🌟' },
  leadership: { label: 'Liderança', emoji: '🧭' },
  dedication: { label: 'Dedicação', emoji: '💪' },
  thanks: { label: 'Gratidão', emoji: '🙏' },
}

/** Reconhecimento entre colegas: vira uma publicação no mural e avisa a pessoa. */
export default function KudosModal({ slug, onClose }: { slug: string; onClose: () => void }) {
  const qc = useQueryClient()
  const { user } = useAuth()
  const { data: members } = useQuery({ queryKey: ['ws', slug, 'members'], queryFn: () => workspaceService.members(slug) })
  const [to, setTo] = useState('')
  const [badge, setBadge] = useState('teamwork')
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const send = useMutation({
    mutationFn: () => workspaceService.giveKudos(slug, { to_uuid: to, badge, message: message.trim() }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ws', slug, 'feed'] }); onClose() },
    onError: (e) => setError(errMsg(e)),
  })

  return (
    <Modal title="Reconhecer um colega" onClose={onClose}>
      <div className="space-y-3">
        <div>
          <label className={labelCls} htmlFor="k-to">Quem você quer reconhecer?</label>
          <select id="k-to" className={inputCls} value={to} onChange={(e) => setTo(e.target.value)}>
            <option value="">Escolha uma pessoa…</option>
            {members?.filter((m) => m.uuid !== user?.uuid).map((m) => <option key={m.uuid} value={m.uuid}>{m.name}{m.department ? ` — ${m.department.name}` : ''}</option>)}
          </select>
        </div>
        <div>
          <p className={labelCls}>Pelo quê?</p>
          <div className="flex flex-wrap gap-2">
            {Object.entries(BADGES).map(([k, b]) => (
              <button key={k} type="button" aria-pressed={badge === k} onClick={() => setBadge(k)}
                className={`rounded-full border px-3 py-1.5 text-sm ${badge === k ? 'border-violet-500 bg-violet-50 text-violet-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50'}`}>{b.emoji} {b.label}</button>
            ))}
          </div>
        </div>
        <div>
          <label className={labelCls} htmlFor="k-msg">Mensagem</label>
          <textarea id="k-msg" rows={3} maxLength={500} className={inputCls} placeholder="Conte o que a pessoa fez de legal…" value={message} onChange={(e) => setMessage(e.target.value)} />
        </div>
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>Cancelar</button>
          <button type="button" className={btnPrimary} disabled={!to || message.trim().length < 5 || send.isPending} onClick={() => send.mutate()}>Publicar reconhecimento</button>
        </div>
      </div>
    </Modal>
  )
}
