import { useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Camera } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import userService from '@/services/user.service'
import { useAuth } from '@/hooks/useAuth'
import { Avatar } from '@/components/ui/Avatar'
import type { PersonProfileFields } from '@/types/workspace.types'
import { RecordSection } from './PersonCardModal'
import { PageHeader, Panel, Spinner, btnPrimary, errMsg, inputCls, labelCls, useWs } from './ui'

const FIELDS: { key: keyof PersonProfileFields; label: string; placeholder: string; area?: boolean }[] = [
  { key: 'bio', label: 'Sobre mim', placeholder: 'Conte um pouco sobre você, o que faz, o que gosta…', area: true },
  { key: 'favorite_book', label: 'Livro favorito', placeholder: 'Ex.: Dom Casmurro' },
  { key: 'favorite_food', label: 'Comida favorita', placeholder: 'Ex.: Feijoada' },
  { key: 'favorite_movie', label: 'Filme ou série favorito', placeholder: 'Ex.: Cidade de Deus' },
  { key: 'hobbies', label: 'Hobbies', placeholder: 'Ex.: corrida, violão, jardinagem' },
  { key: 'hometown', label: 'Cidade natal', placeholder: 'Ex.: Santos' },
  { key: 'fun_fact', label: 'Uma curiosidade sobre mim', placeholder: 'Algo que quase ninguém sabe' },
]

/** Meu perfil: a pessoa cuida da foto e do perfil social; os dados cadastrais (nascimento, endereço, banco) são do RH e aparecem só para leitura. */
export default function WorkspaceProfilePage() {
  const { slug } = useWs()
  const { user, setUser } = useAuth()
  const qc = useQueryClient()
  const fileRef = useRef<HTMLInputElement>(null)
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'myprofile'], queryFn: () => workspaceService.myProfile(slug) })
  const [draft, setDraft] = useState<Partial<PersonProfileFields>>({})
  const [visible, setVisible] = useState<boolean | null>(null)
  const [msg, setMsg] = useState('')

  const save = useMutation({
    mutationFn: () => workspaceService.saveMyProfile(slug, { ...draft, ...(visible !== null ? { birthday_visible: visible } : {}) }),
    onSuccess: (r) => { qc.setQueryData(['ws', slug, 'myprofile'], r); qc.invalidateQueries({ queryKey: ['ws', slug, 'card'] }); setDraft({}); setVisible(null); setMsg('Perfil salvo.') },
    onError: (e) => setMsg(errMsg(e)),
  })
  const avatar = useMutation({
    mutationFn: (f: File) => userService.uploadAvatar(f),
    onSuccess: (r) => { if (r.data) setUser(r.data); qc.invalidateQueries({ queryKey: ['workspace', slug] }); setMsg('Foto atualizada.') },
    onError: (e) => setMsg(errMsg(e)),
  })

  if (isLoading || !data) return <Spinner />
  const val = (k: keyof PersonProfileFields) => (k in draft ? draft[k] : data.profile[k]) ?? ''
  const dirty = Object.keys(draft).length > 0 || visible !== null

  return (
    <div className="space-y-5">
      <PageHeader title="Meu perfil" subtitle="Como os colegas veem você na empresa." />

      <Panel className="flex items-center gap-4">
        <div className="relative">
          <Avatar src={user?.avatar_url ?? data.person?.avatar_url} name={data.person?.name ?? '?'} size="2xl" />
          <button type="button" onClick={() => fileRef.current?.click()} disabled={avatar.isPending} aria-label="Trocar foto"
            className="absolute -bottom-1 -right-1 rounded-full bg-violet-600 p-2 text-white shadow hover:bg-violet-700"><Camera className="h-4 w-4" /></button>
          <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={(e) => { const f = e.target.files?.[0]; if (f) avatar.mutate(f); e.target.value = '' }} />
        </div>
        <div className="min-w-0">
          <p className="text-lg font-semibold text-gray-900">{data.person?.name}</p>
          <p className="text-sm text-gray-600">{[data.person?.job_title, data.department].filter(Boolean).join(' · ') || 'Cargo não informado'}</p>
          <p className="text-xs text-gray-400">{data.email}</p>
        </div>
      </Panel>

      <Panel>
        <h2 className="mb-3 font-semibold text-gray-900">Perfil social</h2>
        <div className="grid gap-3 sm:grid-cols-2">
          {FIELDS.map((f) => (
            <div key={f.key} className={f.area ? 'sm:col-span-2' : ''}>
              <label className={labelCls} htmlFor={`pf-${f.key}`}>{f.label}</label>
              {f.area
                ? <textarea id={`pf-${f.key}`} rows={3} maxLength={400} className={inputCls} placeholder={f.placeholder} value={val(f.key)} onChange={(e) => setDraft((d) => ({ ...d, [f.key]: e.target.value }))} />
                : <input id={`pf-${f.key}`} className={inputCls} placeholder={f.placeholder} value={val(f.key)} onChange={(e) => setDraft((d) => ({ ...d, [f.key]: e.target.value }))} />}
            </div>
          ))}
        </div>
        <label className="mt-4 flex cursor-pointer items-start gap-2 text-sm text-gray-700">
          <input type="checkbox" className="mt-0.5 rounded border-gray-300 text-violet-600" checked={visible ?? data.birthday_visible} onChange={(e) => setVisible(e.target.checked)} />
          <span>Mostrar meu aniversário para a equipe e receber os parabéns no mural<br />
            <span className="text-xs text-gray-400">{data.has_birth_date ? 'Só o dia e o mês aparecem; o ano fica visível apenas para você e para o RH.' : 'Sua data de nascimento ainda não foi cadastrada pelo RH.'}</span>
          </span>
        </label>
        <div className="mt-4 flex items-center justify-end gap-3">
          {msg && <span className="text-sm text-gray-500" role="status">{msg}</span>}
          <button type="button" className={btnPrimary} disabled={!dirty || save.isPending} onClick={() => save.mutate()}>Salvar perfil</button>
        </div>
      </Panel>

      {data.person && (
        <Panel>
          <h2 className="mb-3 font-semibold text-gray-900">Meus dados cadastrais</h2>
          <RecordSection slug={slug} uuid={data.person.uuid} />
        </Panel>
      )}
    </div>
  )
}
