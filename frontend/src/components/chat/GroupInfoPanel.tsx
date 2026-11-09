import { useMemo, useRef, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import chatGroupService from '@/services/chatGroup.service'
import { ArrowLeft, BellOff, Bell, Camera, Crown, Trash2, LogOut, Megaphone, UserMinus, UserPlus } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import {
  useAddGroupMembers,
  useChatGroup,
  useCompanyMembers,
  useDepartments,
  useLeaveGroup,
  useMuteGroup,
  useRemoveGroupMember,
  useSetGroupMemberRole,
  useUpdateChatGroup,
} from '@/hooks/useChatGroups'
import { useToast } from '@/contexts/ToastContext'
import { Avatar } from '@/components/ui/Avatar'
import { Button } from '@/components/ui/Button'
import { extractApiError } from '@/utils/api-error'

interface Props {
  groupUuid: string
  onBack: () => void
  onLeft: () => void
}

/** Detalhes do grupo: configurações (modo aviso), silenciar, membros e permissões. */
export default function GroupInfoPanel({ groupUuid, onBack, onLeft }: Props) {
  const { user } = useAuth()
  const { toast } = useToast()
  const { data: group } = useChatGroup(groupUuid)

  const update = useUpdateChatGroup(groupUuid)
  const mute = useMuteGroup(groupUuid)
  const leave = useLeaveGroup(groupUuid)
  const addMembers = useAddGroupMembers(groupUuid)
  const removeMember = useRemoveGroupMember(groupUuid)
  const setRole = useSetGroupMemberRole(groupUuid)

  const hotelSlug = group?.hotel?.slug ?? null
  const canManage = !!group?.can_manage
  const { data: companyMembers = [] } = useCompanyMembers(canManage ? hotelSlug : null)
  const { data: departments = [] } = useDepartments(canManage ? hotelSlug : null)

  const qc = useQueryClient()
  const photoRef = useRef<HTMLInputElement>(null)
  const refreshGroups = () => { qc.invalidateQueries({ queryKey: ['chat-groups'] }) }
  const setPhoto = useMutation({ mutationFn: (f: File) => chatGroupService.setAvatar(groupUuid, f), onSuccess: () => { refreshGroups(); toast('Foto do grupo atualizada.', 'success') }, onError: (e) => toast(extractApiError(e), 'error') })
  const clearPhoto = useMutation({ mutationFn: () => chatGroupService.removeAvatar(groupUuid), onSuccess: () => { refreshGroups(); toast('Foto removida.', 'success') }, onError: (e) => toast(extractApiError(e), 'error') })
  const pickPhoto = (f?: File) => {
    if (!f) return
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) return toast('Use uma foto JPG, PNG ou WebP.', 'error')
    if (f.size > 5 * 1024 * 1024) return toast('A foto pode ter até 5 MB.', 'error')
    setPhoto.mutate(f)
  }
  const [adding, setAdding] = useState(false)
  const [picked, setPicked] = useState<string[]>([])
  const [pickedDepts, setPickedDepts] = useState<string[]>([])

  const candidates = useMemo(() => {
    const inGroup = new Set((group?.members ?? []).map((m) => m.uuid))
    return companyMembers.filter((m) => !inGroup.has(m.uuid))
  }, [companyMembers, group?.members])

  async function run<T>(p: Promise<T>, okMsg: string) {
    try {
      await p
      toast(okMsg, 'success')
    } catch (err) {
      toast(extractApiError(err), 'error')
    }
  }

  if (!group) {
    return <div className="p-6 text-center text-sm text-gray-400">Carregando…</div>
  }

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center gap-2 border-b border-gray-100 px-3 py-3">
        <button onClick={onBack} className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100" aria-label="Voltar">
          <ArrowLeft className="h-5 w-5" />
        </button>
        <h3 className="text-sm font-semibold text-gray-900">Informações do grupo</h3>
      </div>

      <div className="flex-1 space-y-5 overflow-y-auto px-4 py-4">
        <div className="flex items-center gap-4">
          <div className="relative flex-shrink-0">
            <Avatar src={group.avatar_url ?? group.hotel?.avatar_url} name={group.name} size="2xl" />
            {canManage && (
              <>
                <button type="button" onClick={() => photoRef.current?.click()} disabled={setPhoto.isPending} aria-label="Trocar foto do grupo" title="Trocar foto do grupo" className="absolute -bottom-1 -right-1 rounded-full bg-brand-600 p-2 text-white shadow hover:bg-brand-700 disabled:opacity-60"><Camera className="h-4 w-4" /></button>
                <input ref={photoRef} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(e) => { pickPhoto(e.target.files?.[0]); e.target.value = '' }} />
              </>
            )}
          </div>
          <div className="min-w-0">
            <p className="text-base font-semibold text-gray-900">{group.name}</p>
            {group.description && <p className="mt-0.5 text-sm text-gray-500">{group.description}</p>}
            {group.hotel && <p className="mt-1 text-xs text-gray-400">{group.hotel.name} · {group.members_count} participantes</p>}
            {canManage && group.avatar_url && <button type="button" onClick={() => clearPhoto.mutate()} disabled={clearPhoto.isPending} className="mt-1 inline-flex items-center gap-1 text-xs text-gray-400 hover:text-red-500"><Trash2 className="h-3 w-3" /> Remover foto</button>}
          </div>
        </div>

        {canManage && (
          <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3 hover:bg-gray-50">
            <input
              type="checkbox"
              checked={group.only_admins_can_post}
              onChange={(e) =>
                run(update.mutateAsync({ only_admins_can_post: e.target.checked }),
                  e.target.checked ? 'Modo aviso ativado.' : 'Todos podem enviar mensagens.')
              }
              className="mt-0.5 rounded border-gray-300 text-brand-600"
            />
            <span className="text-sm">
              <span className="flex items-center gap-1.5 font-medium text-gray-800"><Megaphone className="h-4 w-4 text-amber-500" />Modo aviso</span>
              <span className="text-gray-500">Somente administradores enviam mensagens.</span>
            </span>
          </label>
        )}

        {!canManage && group.only_admins_can_post && (
          <p className="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
            <Megaphone className="h-4 w-4" />Grupo em modo aviso: somente administradores enviam mensagens.
          </p>
        )}

        {group.is_member && (
          <Button
            variant="outline"
            size="sm"
            fullWidth
            icon={group.notifications_muted ? <Bell className="h-4 w-4" /> : <BellOff className="h-4 w-4" />}
            onClick={() =>
              run(mute.mutateAsync(!group.notifications_muted),
                group.notifications_muted ? 'Notificações ativadas.' : 'Grupo silenciado.')
            }
          >
            {group.notifications_muted ? 'Reativar notificações' : 'Silenciar notificações'}
          </Button>
        )}

        <div>
          <div className="mb-2 flex items-center justify-between">
            <p className="text-xs font-medium uppercase tracking-wide text-gray-400">Participantes ({group.members.length})</p>
            {canManage && (
              <button onClick={() => setAdding((v) => !v)} className="flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline">
                <UserPlus className="h-3.5 w-3.5" />Adicionar
              </button>
            )}
          </div>

          {adding && (
            <div className="mb-3 space-y-3 rounded-xl border border-brand-100 bg-brand-50/40 p-3">
              {departments.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                  {departments.map((d) => {
                    const on = pickedDepts.includes(d.uuid)
                    return (
                      <button
                        key={d.uuid}
                        type="button"
                        onClick={() => setPickedDepts(on ? pickedDepts.filter((x) => x !== d.uuid) : [...pickedDepts, d.uuid])}
                        className={`rounded-full border px-2.5 py-0.5 text-xs ${on ? 'border-brand-500 bg-white text-brand-700' : 'border-gray-200 bg-white text-gray-600'}`}
                      >
                        Setor {d.name}
                      </button>
                    )
                  })}
                </div>
              )}
              <ul className="max-h-40 divide-y divide-gray-100 overflow-y-auto rounded-lg bg-white">
                {candidates.length === 0 && <li className="px-3 py-2 text-center text-xs text-gray-400">Todos já estão no grupo.</li>}
                {candidates.map((m) => (
                  <li key={m.uuid}>
                    <label className="flex cursor-pointer items-center gap-2 px-3 py-1.5 hover:bg-gray-50">
                      <input
                        type="checkbox"
                        checked={picked.includes(m.uuid)}
                        onChange={() => setPicked(picked.includes(m.uuid) ? picked.filter((x) => x !== m.uuid) : [...picked, m.uuid])}
                        className="rounded border-gray-300 text-brand-600"
                      />
                      <Avatar src={m.avatar_url} name={m.name} size="xs" />
                      <span className="truncate text-sm text-gray-800">{m.name}</span>
                    </label>
                  </li>
                ))}
              </ul>
              <Button
                size="sm"
                fullWidth
                loading={addMembers.isPending}
                disabled={picked.length === 0 && pickedDepts.length === 0}
                onClick={async () => {
                  await run(addMembers.mutateAsync({ user_uuids: picked, department_uuids: pickedDepts }), 'Participantes adicionados.')
                  setPicked([])
                  setPickedDepts([])
                  setAdding(false)
                }}
              >
                Adicionar selecionados
              </Button>
            </div>
          )}

          <ul className="space-y-1">
            {group.members.map((m) => {
              const isMe = m.uuid === user?.uuid
              return (
                <li key={m.uuid} className="flex items-center gap-2.5 rounded-lg px-1 py-1.5">
                  <Avatar src={m.avatar_url} name={m.name} size="sm" />
                  <div className="min-w-0 flex-1">
                    <p className="flex items-center gap-1 truncate text-sm font-medium text-gray-800">
                      <span className="truncate">{m.name}{isMe && ' (você)'}</span>
                      {m.role === 'admin' && <Crown className="h-3.5 w-3.5 flex-shrink-0 text-amber-500" aria-label="Administrador" />}
                    </p>
                    {m.department && <p className="truncate text-xs text-gray-400">{m.department}</p>}
                  </div>
                  {canManage && !isMe && (
                    <div className="flex items-center gap-1">
                      <button
                        title={m.role === 'admin' ? 'Remover como administrador' : 'Tornar administrador'}
                        onClick={() => run(setRole.mutateAsync({ userUuid: m.uuid, role: m.role === 'admin' ? 'member' : 'admin' }), 'Permissão atualizada.')}
                        className={`rounded-lg p-1.5 hover:bg-gray-100 ${m.role === 'admin' ? 'text-amber-500' : 'text-gray-400'}`}
                      >
                        <Crown className="h-4 w-4" />
                      </button>
                      <button
                        title="Remover do grupo"
                        onClick={() => run(removeMember.mutateAsync(m.uuid), 'Participante removido.')}
                        className="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500"
                      >
                        <UserMinus className="h-4 w-4" />
                      </button>
                    </div>
                  )}
                </li>
              )
            })}
          </ul>
        </div>

        {group.is_member && (
          <Button
            variant="danger"
            size="sm"
            fullWidth
            icon={<LogOut className="h-4 w-4" />}
            loading={leave.isPending}
            onClick={async () => {
              try {
                await leave.mutateAsync()
                toast('Você saiu do grupo.', 'success')
                onLeft()
              } catch (err) {
                toast(extractApiError(err), 'error')
              }
            }}
          >
            Sair do grupo
          </Button>
        )}
      </div>
    </div>
  )
}
