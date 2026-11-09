import { useState } from 'react'
import { Laptop } from 'lucide-react'
import { Modal, btnGhost, btnPrimary, inputCls, labelCls } from './ui'

/** Primeira marcação num aparelho novo: pede um nome para o RH reconhecer e aprovar o dispositivo. */
export default function DeviceNameModal({ onConfirm, onCancel }: { onConfirm: (name: string) => void; onCancel: () => void }) {
  const [name, setName] = useState('')
  const ok = name.trim().length >= 2

  return (
    <Modal title="Cadastrar este dispositivo" onClose={onCancel}>
      <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); if (ok) onConfirm(name.trim()) }}>
        <p className="flex items-start gap-2 text-sm text-gray-600">
          <Laptop className="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-600" />
          Sua empresa só aceita marcações de dispositivos aprovados pelo RH. Dê um nome a este aparelho para o RH reconhecê-lo.
        </p>
        <div>
          <label className={labelCls}>Nome do dispositivo</label>
          <input autoFocus className={inputCls} value={name} maxLength={80} onChange={(e) => setName(e.target.value)} placeholder="Ex.: Recepção — PC 01 ou Celular do João" />
        </div>
        <p className="text-[11px] text-gray-400">O RH será avisado. Depois de aprovado, qualquer pessoa autorizada pode marcar o ponto por ele.</p>
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onCancel}>Cancelar</button>
          <button type="submit" className={btnPrimary} disabled={!ok}>Cadastrar e marcar</button>
        </div>
      </form>
    </Modal>
  )
}
