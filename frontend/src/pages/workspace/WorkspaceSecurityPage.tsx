import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import QRCode from 'qrcode'
import { Check, Copy, Download, KeyRound, Laptop, LogOut, ShieldCheck, ShieldOff } from 'lucide-react'
import authService from '@/services/auth.service'
import workspaceService from '@/services/workspace.service'
import { useAuth } from '@/hooks/useAuth'
import { Modal, PageHeader, Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, fmtDate, inputCls, labelCls, useWs } from './ui'

function RecoveryCodes({ codes, onDone }: { codes: string[]; onDone: () => void }) {
  const [copied, setCopied] = useState(false)
  const text = codes.join('\n')
  const copy = async () => { try { await navigator.clipboard.writeText(text); setCopied(true) } catch { /* selecione manualmente */ } }
  const download = () => {
    const url = URL.createObjectURL(new Blob([`Códigos de recuperação (cada um vale uma vez)\n\n${text}\n`], { type: 'text/plain' }))
    const a = document.createElement('a')
    a.href = url
    a.download = 'codigos-de-recuperacao.txt'
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 5000)
  }
  return (
    <div className="space-y-3">
      <p className="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">
        Guarde estes códigos num lugar seguro. Se você perder o celular, eles são a única forma de entrar. <b>Eles não aparecerão de novo.</b>
      </p>
      <ul className="grid grid-cols-2 gap-2 rounded-xl bg-gray-50 p-3 font-mono text-sm">{codes.map((c) => <li key={c}>{c}</li>)}</ul>
      <div className="flex flex-wrap gap-2">
        <button type="button" className={btnGhost} onClick={() => void copy()}>{copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />} {copied ? 'Copiados' : 'Copiar'}</button>
        <button type="button" className={btnGhost} onClick={download}><Download className="h-4 w-4" /> Baixar .txt</button>
        <button type="button" className={`${btnPrimary} ml-auto`} onClick={onDone}>Já guardei</button>
      </div>
    </div>
  )
}

function SetupModal({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const [step, setStep] = useState<'qr' | 'codes'>('qr')
  const [secret, setSecret] = useState('')
  const [qr, setQr] = useState('')
  const [code, setCode] = useState('')
  const [codes, setCodes] = useState<string[]>([])
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let alive = true
    authService.setup2fa().then(async (r) => {
      if (!alive) return
      setSecret(r.secret)
      setQr(await QRCode.toDataURL(r.uri, { margin: 1, width: 224 }))
    }).catch((e) => alive && setError(errMsg(e)))
    return () => { alive = false }
  }, [])

  const confirm = async () => {
    setBusy(true)
    setError('')
    try {
      const r = await authService.enable2fa(code.replace(/\s/g, ''))
      setCodes(r.recovery_codes)
      setStep('codes')
    } catch (e) {
      setError(errMsg(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal title={step === 'qr' ? 'Ativar verificação em duas etapas' : 'Códigos de recuperação'} onClose={step === 'codes' ? onDone : onClose}>
      {step === 'qr' ? (
        <div className="space-y-3 text-sm text-gray-600">
          <ol className="list-decimal space-y-1 pl-5">
            <li>Instale um aplicativo autenticador (Google Authenticator, Microsoft Authenticator, Authy…).</li>
            <li>Escaneie o QR code abaixo ou digite a chave manualmente.</li>
            <li>Digite o código de 6 dígitos que o aplicativo mostrar.</li>
          </ol>
          <div className="flex flex-col items-center gap-2">
            {qr ? <img src={qr} alt="QR code para o aplicativo autenticador" width={224} height={224} className="rounded-xl border border-gray-100" /> : <Spinner />}
            {secret && <code className="select-all break-all rounded-lg bg-gray-50 px-2 py-1 text-xs tracking-wider">{secret}</code>}
          </div>
          <div>
            <label className={labelCls}>Código do aplicativo</label>
            <input className={`${inputCls} text-center text-lg tracking-widest`} inputMode="numeric" autoComplete="one-time-code" maxLength={7} value={code} onChange={(e) => setCode(e.target.value)} placeholder="000000" />
          </div>
          {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
          <div className="flex justify-end gap-2">
            <button type="button" className={btnGhost} onClick={onClose}>Cancelar</button>
            <button type="button" className={btnPrimary} disabled={busy || !/^\d{6}$/.test(code.replace(/\s/g, ''))} onClick={() => void confirm()}>{busy ? 'Verificando…' : 'Ativar'}</button>
          </div>
        </div>
      ) : <RecoveryCodes codes={codes} onDone={onDone} />}
    </Modal>
  )
}

function ConfirmModal({ title, action, onClose, onSuccess }: {
  title: string
  action: (body: { password: string; code?: string; recovery_code?: string }) => Promise<unknown>
  onClose: () => void
  onSuccess: (result: unknown) => void
}) {
  const [password, setPassword] = useState('')
  const [code, setCode] = useState('')
  const [recovery, setRecovery] = useState(false)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const ok = password.length > 0 && (recovery ? code.trim().length >= 8 : /^\d{6}$/.test(code.replace(/\s/g, '')))

  const submit = async () => {
    setBusy(true)
    setError('')
    try {
      onSuccess(await action(recovery ? { password, recovery_code: code.trim() } : { password, code: code.replace(/\s/g, '') }))
    } catch (e) {
      setError(errMsg(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal title={title} onClose={onClose}>
      <div className="space-y-3">
        <div><label className={labelCls}>Sua senha</label><input type="password" autoComplete="current-password" className={inputCls} value={password} onChange={(e) => setPassword(e.target.value)} /></div>
        <div>
          <label className={labelCls}>{recovery ? 'Código de recuperação' : 'Código do aplicativo'}</label>
          <input className={inputCls} inputMode={recovery ? 'text' : 'numeric'} autoComplete="one-time-code" maxLength={recovery ? 20 : 7} value={code} onChange={(e) => setCode(e.target.value)} placeholder={recovery ? 'xxxxx-xxxxx' : '000000'} />
          <button type="button" className="mt-1 text-xs text-violet-700 hover:underline" onClick={() => { setRecovery((r) => !r); setCode('') }}>{recovery ? 'Usar o aplicativo' : 'Usar código de recuperação'}</button>
        </div>
        {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
        <div className="flex justify-end gap-2">
          <button type="button" className={btnGhost} onClick={onClose}>Cancelar</button>
          <button type="button" className={btnPrimary} disabled={!ok || busy} onClick={() => void submit()}>{busy ? 'Confirmando…' : 'Confirmar'}</button>
        </div>
      </div>
    </Modal>
  )
}

function TwoFactorCard() {
  const qc = useQueryClient()
  const { slug } = useWs()
  const { data } = useQuery({ queryKey: ['account', 'security'], queryFn: authService.security })
  const [modal, setModal] = useState<'setup' | 'disable' | 'regen' | 'codes' | null>(null)
  const [newCodes, setNewCodes] = useState<string[]>([])
  const refresh = () => { void qc.invalidateQueries({ queryKey: ['account', 'security'] }); void qc.invalidateQueries({ queryKey: ['workspace', slug] }); void qc.invalidateQueries({ queryKey: ['ws', slug, 'security'] }) }

  if (!data) return <Spinner />
  const tf = data.two_factor

  return (
    <Panel>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex items-start gap-3">
          <span className={`rounded-full p-2 ${tf.enabled ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-500'}`}>{tf.enabled ? <ShieldCheck className="h-5 w-5" /> : <ShieldOff className="h-5 w-5" />}</span>
          <div>
            <h2 className="font-semibold text-gray-900">Verificação em duas etapas {tf.enabled ? <Pill tone="green">Ativa</Pill> : <Pill tone="gray">Desativada</Pill>}</h2>
            <p className="mt-0.5 text-sm text-gray-500">
              {tf.enabled ? `Ativada em ${fmtDate(tf.confirmed_at)}. ${tf.recovery_left} códigos de recuperação restantes.` : 'Além da senha, pede um código do celular ao entrar. Protege sua conta mesmo se a senha vazar.'}
            </p>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          {tf.enabled ? (
            <>
              <button type="button" className={btnGhost} onClick={() => setModal('regen')}><KeyRound className="h-4 w-4" /> Novos códigos</button>
              <button type="button" className={btnGhost} onClick={() => setModal('disable')}>Desativar</button>
            </>
          ) : <button type="button" className={btnPrimary} onClick={() => setModal('setup')}>Ativar</button>}
        </div>
      </div>
      {tf.enabled && tf.recovery_left <= 2 && <p className="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">Poucos códigos de recuperação restantes. Gere novos.</p>}

      {modal === 'setup' && <SetupModal onClose={() => setModal(null)} onDone={() => { setModal(null); refresh() }} />}
      {modal === 'disable' && <ConfirmModal title="Desativar verificação em duas etapas" action={authService.disable2fa} onClose={() => setModal(null)} onSuccess={() => { setModal(null); refresh() }} />}
      {modal === 'regen' && <ConfirmModal title="Gerar novos códigos de recuperação" action={authService.regenerateCodes} onClose={() => setModal(null)} onSuccess={(r) => { setNewCodes((r as { recovery_codes: string[] }).recovery_codes); setModal('codes'); refresh() }} />}
      {modal === 'codes' && <Modal title="Novos códigos de recuperação" onClose={() => setModal(null)}><RecoveryCodes codes={newCodes} onDone={() => setModal(null)} /></Modal>}
    </Panel>
  )
}

function SessionsCard() {
  const qc = useQueryClient()
  const [error, setError] = useState('')
  const { data } = useQuery({ queryKey: ['account', 'security'], queryFn: authService.security })
  const refresh = () => qc.invalidateQueries({ queryKey: ['account', 'security'] })
  const revoke = useMutation({ mutationFn: (id: number) => authService.revokeSession(id), onSuccess: () => { setError(''); void refresh() }, onError: (e) => setError(errMsg(e)) })
  const others = useMutation({ mutationFn: authService.revokeOtherSessions, onSuccess: () => { setError(''); void refresh() }, onError: (e) => setError(errMsg(e)) })

  if (!data) return null
  return (
    <Panel>
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 className="flex items-center gap-2 font-semibold text-gray-900"><Laptop className="h-4 w-4 text-violet-500" /> Onde você está conectado</h2>
        {data.sessions.length > 1 && (
          <button type="button" className={btnGhost} disabled={others.isPending} onClick={() => { if (window.confirm('Encerrar todas as outras sessões?')) others.mutate() }}><LogOut className="h-4 w-4" /> Encerrar as outras</button>
        )}
      </div>
      {error && <p className="mb-2 text-sm text-red-600" role="alert">{error}</p>}
      <ul className="divide-y divide-gray-50">
        {data.sessions.map((s) => (
          <li key={s.id} className="flex flex-wrap items-center gap-3 py-2.5 text-sm">
            <div className="min-w-0 flex-1">
              <p className="font-medium text-gray-900">{s.label} {s.current && <Pill tone="green">Este aparelho</Pill>}</p>
              <p className="text-xs text-gray-500">{s.ip ? `IP ${s.ip} · ` : ''}entrou em {fmtDate(s.created_at)}{s.last_used_at ? ` · usado em ${fmtDate(s.last_used_at)}` : ''}</p>
            </div>
            {!s.current && <button type="button" className="text-xs font-medium text-red-600 hover:underline" disabled={revoke.isPending} onClick={() => revoke.mutate(s.id)}>Encerrar</button>}
          </li>
        ))}
      </ul>
    </Panel>
  )
}

function PasswordCard() {
  const { logout } = useAuth()
  const navigate = useNavigate()
  const [current, setCurrent] = useState('')
  const [next, setNext] = useState('')
  const [confirm, setConfirm] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const strong = next.length >= 10 && /[a-z]/.test(next) && /[A-Z]/.test(next) && /\d/.test(next)

  const submit = async () => {
    setBusy(true)
    setError('')
    try {
      await authService.changePassword({ current_password: current, password: next, password_confirmation: confirm })
      await logout().catch(() => undefined)
      navigate('/entrar', { replace: true })
    } catch (e) {
      setError(errMsg(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Panel>
      <h2 className="mb-3 font-semibold text-gray-900">Alterar senha</h2>
      <div className="grid gap-3 sm:grid-cols-3">
        <div><label className={labelCls}>Senha atual</label><input type="password" autoComplete="current-password" className={inputCls} value={current} onChange={(e) => setCurrent(e.target.value)} /></div>
        <div><label className={labelCls}>Nova senha</label><input type="password" autoComplete="new-password" className={inputCls} value={next} onChange={(e) => setNext(e.target.value)} /></div>
        <div><label className={labelCls}>Confirmar nova senha</label><input type="password" autoComplete="new-password" className={inputCls} value={confirm} onChange={(e) => setConfirm(e.target.value)} /></div>
      </div>
      <p className={`mt-2 text-xs ${next && !strong ? 'text-amber-700' : 'text-gray-500'}`}>Mínimo de 10 caracteres, com letra maiúscula, minúscula e número. Ao trocar, você sai de todos os aparelhos.</p>
      {error && <p className="mt-2 text-sm text-red-600" role="alert">{error}</p>}
      <button type="button" className={`${btnPrimary} mt-3`} disabled={busy || !current || !strong || next !== confirm} onClick={() => void submit()}>{busy ? 'Salvando…' : 'Alterar senha'}</button>
    </Panel>
  )
}

function CompanyPolicyCard() {
  const { slug, info } = useWs()
  const qc = useQueryClient()
  const [error, setError] = useState('')
  const { data } = useQuery({ queryKey: ['ws', slug, 'security'], queryFn: () => workspaceService.security(slug), enabled: info.me.is_admin })
  const save = useMutation({
    mutationFn: (v: boolean) => workspaceService.saveSecurity(slug, v),
    onSuccess: () => { setError(''); void qc.invalidateQueries({ queryKey: ['ws', slug, 'security'] }) },
    onError: (e) => setError(errMsg(e)),
  })
  if (!info.me.is_admin || !data) return null

  return (
    <Panel>
      <h2 className="mb-1 font-semibold text-gray-900">Política da empresa</h2>
      <p className="mb-3 text-sm text-gray-500">Exigir verificação em duas etapas de quem tem acesso a dados sensíveis: dono, RH e financeiro.</p>
      <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" className="rounded border-gray-300 text-violet-600" checked={data.require_2fa} disabled={!data.can_edit || save.isPending} onChange={(e) => save.mutate(e.target.checked)} />
        Exigir para dono, RH e financeiro {!data.can_edit && <span className="text-xs text-gray-400">(somente o dono altera)</span>}
      </label>
      {data.require_2fa && (
        <p className="mt-2 text-sm text-gray-600">
          {data.privileged_without_2fa.length === 0 ? 'Todos os perfis privilegiados já ativaram.' : <>Ainda sem 2FA: <b>{data.privileged_without_2fa.map((p) => p?.name).join(', ')}</b>. Eles só acessam esta tela até ativar.</>}
        </p>
      )}
      {error && <p className="mt-2 text-sm text-red-600" role="alert">{error}</p>}
    </Panel>
  )
}

export default function WorkspaceSecurityPage() {
  return (
    <div className="space-y-5">
      <PageHeader title="Segurança" subtitle="Proteja sua conta e veja onde ela está conectada." />
      <TwoFactorCard />
      <SessionsCard />
      <PasswordCard />
      <CompanyPolicyCard />
    </div>
  )
}
