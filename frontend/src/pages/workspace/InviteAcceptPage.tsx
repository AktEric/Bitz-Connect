import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import workspaceService from '@/services/workspace.service'
import { useAuth } from '@/hooks/useAuth'
import { Avatar } from '@/components/ui/Avatar'
import { Spinner, btnGhost, btnPrimary, errMsg } from './ui'

/** Página pública do convite: mostra a empresa e deixa a pessoa entrar. */
export default function InviteAcceptPage() {
  const { token = '' } = useParams()
  const { isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const { data, isLoading, isError } = useQuery({ queryKey: ['invite', token], queryFn: () => workspaceService.invitePreview(token), retry: false })

  const accept = async () => {
    setBusy(true)
    setError('')
    try {
      const res = await workspaceService.acceptInvite(token)
      await qc.invalidateQueries({ queryKey: ['workspaces'] })
      navigate(`/w/${res.hotel.slug}`, { replace: true })
    } catch (e) {
      setError(errMsg(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4">
      <div className="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-6 text-center shadow-sm">
        {isLoading ? <Spinner /> : isError || !data ? (
          <>
            <h1 className="text-lg font-semibold text-gray-900">Convite inválido ou expirado</h1>
            <p className="mt-1 text-sm text-gray-500">Peça um novo link ao RH da sua empresa.</p>
          </>
        ) : (
          <>
            <div className="mb-3 flex justify-center"><Avatar src={data.hotel.avatar_url} name={data.hotel.name} size="xl" /></div>
            <h1 className="text-xl font-bold text-gray-900">Você foi convidado(a) para {data.hotel.name}</h1>
            {(data.job_title || data.department) && (
              <p className="mt-1 text-sm text-gray-500">{[data.job_title, data.department].filter(Boolean).join(' · ')}</p>
            )}
            {!data.usable ? (
              <p className="mt-4 text-sm text-red-600">Este convite não está mais disponível.</p>
            ) : isAuthenticated ? (
              <>
                <button className={`${btnPrimary} mt-5 w-full`} disabled={busy} onClick={accept}>Entrar na empresa</button>
                {error && <p className="mt-3 text-sm text-red-600">{error}</p>}
              </>
            ) : (
              <>
                <p className="mt-4 text-sm text-gray-500">Entre com e-mail e senha ou com o Google — você volta aqui para aceitar o convite.</p>
                <div className="mt-4 flex gap-2">
                  <Link to={`/entrar?redirect=${encodeURIComponent(`/convite/${token}`)}`} className={`${btnPrimary} flex-1`}>Entrar</Link>
                  <Link to={`/register/pessoa?redirect=${encodeURIComponent(`/convite/${token}`)}`} className={`${btnGhost} flex-1`}>Criar conta</Link>
                </div>
              </>
            )}
          </>
        )}
      </div>
    </div>
  )
}
