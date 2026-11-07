import { useMemo, useState } from 'react'
import { X, Megaphone, Users, Search } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import { useMyHotels } from '@/hooks/useHotel'
import { useCompanyMembers, useCreateChatGroup, useDepartments } from '@/hooks/useChatGroups'
import { useToast } from '@/contexts/ToastContext'
import { Avatar } from '@/components/ui/Avatar'
import { Button } from '@/components/ui/Button'
import { extractApiError } from '@/utils/api-error'
import type { ChatGroup } from '@/types/chatGroup.types'

interface Props {
  onClose: () => void
  onCreated: (group: ChatGroup) => void
}

/** Modal de criação de grupo da empresa: nome, modo aviso e participantes (pessoas, setores ou todos). */
export default function CreateGroupModal({ onClose, onCreated }: Props) {
  const { user } = useAuth()
  const { toast } = useToast()
  const { data: hotelsData } = useMyHotels()

  const adminHotels = useMemo(() => (hotelsData?.data ?? []), [hotelsData])

  const [hotelSlug, setHotelSlug] = useState<string>('')
  const effectiveSlug = hotelSlug || adminHotels[0]?.slug || ''

  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [announceMode, setAnnounceMode] = useState(false)
  const [allEmployees, setAllEmployees] = useState(false)
  const [deptIds, setDeptIds] = useState<string[]>([])
  const [memberIds, setMemberIds] = useState<string[]>([])
  const [search, setSearch] = useState('')

  const { data: departments = [] } = useDepartments(effectiveSlug || null)
  const { data: members = [] } = useCompanyMembers(effectiveSlug || null)
  const create = useCreateChatGroup(effectiveSlug)

  const people = useMemo(
    () =>
      members
        .filter((m) => m.uuid !== user?.uuid)
        .filter((m) => m.name.toLowerCase().includes(search.trim().toLowerCase())),
    [members, user?.uuid, search],
  )

  function toggle(list: string[], setList: (v: string[]) => void, id: string) {
    setList(list.includes(id) ? list.filter((x) => x !== id) : [...list, id])
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!effectiveSlug || !name.trim()) return
    try {
      const group = await create.mutateAsync({
        name: name.trim(),
        description: description.trim() || undefined,
        only_admins_can_post: announceMode,
        all_employees: allEmployees,
        department_uuids: allEmployees ? [] : deptIds,
        member_uuids: allEmployees ? [] : memberIds,
      })
      toast('Grupo criado com sucesso.', 'success')
      onCreated(group)
    } catch (err) {
      toast(extractApiError(err), 'error')
    }
  }

  return (
    <div className="fixed inset-0 z-[60] flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="Novo grupo">
      <form onSubmit={handleSubmit} className="flex max-h-[92vh] w-full max-w-lg flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl">
        <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
          <h2 className="text-base font-semibold text-gray-900">Novo grupo</h2>
          <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100" aria-label="Fechar">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="flex-1 space-y-4 overflow-y-auto px-5 py-4">
          {adminHotels.length === 0 ? (
            <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
              Você precisa ser administrador de uma empresa para criar grupos.
            </p>
          ) : (
            <>
              {adminHotels.length > 1 && (
                <label className="block text-sm">
                  <span className="mb-1 block font-medium text-gray-700">Empresa</span>
                  <select
                    value={effectiveSlug}
                    onChange={(e) => {
                      setHotelSlug(e.target.value)
                      setDeptIds([])
                      setMemberIds([])
                    }}
                    className="w-full rounded-lg border-gray-200 text-sm"
                  >
                    {adminHotels.map((h) => (
                      <option key={h.slug} value={h.slug}>{h.name}</option>
                    ))}
                  </select>
                </label>
              )}

              <label className="block text-sm">
                <span className="mb-1 block font-medium text-gray-700">Nome do grupo</span>
                <input
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  maxLength={120}
                  required
                  placeholder="Ex.: Avisos da Recepção"
                  className="w-full rounded-lg border-gray-200 text-sm"
                />
              </label>

              <label className="block text-sm">
                <span className="mb-1 block font-medium text-gray-700">Descrição <span className="font-normal text-gray-400">(opcional)</span></span>
                <input
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  maxLength={500}
                  className="w-full rounded-lg border-gray-200 text-sm"
                />
              </label>

              <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3 hover:bg-gray-50">
                <input type="checkbox" checked={announceMode} onChange={(e) => setAnnounceMode(e.target.checked)} className="mt-0.5 rounded border-gray-300 text-violet-600" />
                <span className="text-sm">
                  <span className="flex items-center gap-1.5 font-medium text-gray-800"><Megaphone className="h-4 w-4 text-amber-500" />Modo aviso</span>
                  <span className="text-gray-500">Somente administradores enviam mensagens; os demais apenas leem.</span>
                </span>
              </label>

              <div>
                <p className="mb-2 flex items-center gap-1.5 text-sm font-medium text-gray-700"><Users className="h-4 w-4" />Participantes</p>

                <label className="mb-3 flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                  <input type="checkbox" checked={allEmployees} onChange={(e) => setAllEmployees(e.target.checked)} className="rounded border-gray-300 text-violet-600" />
                  Todos da empresa
                </label>

                {!allEmployees && (
                  <>
                    {departments.length > 0 && (
                      <div className="mb-3">
                        <p className="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-400">Setores inteiros</p>
                        <div className="flex flex-wrap gap-2">
                          {departments.map((d) => {
                            const on = deptIds.includes(d.uuid)
                            return (
                              <button
                                type="button"
                                key={d.uuid}
                                onClick={() => toggle(deptIds, setDeptIds, d.uuid)}
                                className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors ${on ? 'border-violet-500 bg-violet-50 text-violet-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50'}`}
                              >
                                {d.name} <span className="text-gray-400">({d.members_count})</span>
                              </button>
                            )
                          })}
                        </div>
                      </div>
                    )}

                    <p className="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-400">Pessoas</p>
                    <div className="relative mb-2">
                      <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                      <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar pessoa" className="w-full rounded-lg border-gray-200 pl-8 text-sm" />
                    </div>
                    <ul className="max-h-44 divide-y divide-gray-50 overflow-y-auto rounded-lg border border-gray-100">
                      {people.length === 0 && <li className="px-3 py-3 text-center text-xs text-gray-400">Nenhuma pessoa encontrada.</li>}
                      {people.map((m) => (
                        <li key={m.uuid}>
                          <label className="flex cursor-pointer items-center gap-2.5 px-3 py-2 hover:bg-gray-50">
                            <input type="checkbox" checked={memberIds.includes(m.uuid)} onChange={() => toggle(memberIds, setMemberIds, m.uuid)} className="rounded border-gray-300 text-violet-600" />
                            <Avatar src={m.avatar_url} name={m.name} size="xs" />
                            <span className="flex-1 truncate text-sm text-gray-800">{m.name}</span>
                            {m.department && <span className="text-xs text-gray-400">{m.department.name}</span>}
                          </label>
                        </li>
                      ))}
                    </ul>
                  </>
                )}
              </div>
            </>
          )}
        </div>

        <div className="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
          <Button type="button" variant="ghost" onClick={onClose}>Cancelar</Button>
          <Button type="submit" loading={create.isPending} disabled={!effectiveSlug || !name.trim()}>Criar grupo</Button>
        </div>
      </form>
    </div>
  )
}
