import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { BookOpen, Film, MapPin, Smile, Utensils } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { PersonRecord } from '@/types/workspace.types'
import { Spinner, btnPrimary, errMsg, fmtDate, inputCls, labelCls } from './ui'

export function ProfileFacts({ profile }: { profile: Record<string, string | null> }) {
  const rows = [
    { Icon: BookOpen, label: 'Livro favorito', v: profile.favorite_book },
    { Icon: Utensils, label: 'Comida favorita', v: profile.favorite_food },
    { Icon: Film, label: 'Filme/série favorito', v: profile.favorite_movie },
    { Icon: Smile, label: 'Hobbies', v: profile.hobbies },
    { Icon: MapPin, label: 'Cidade natal', v: profile.hometown },
    { Icon: Smile, label: 'Curiosidade', v: profile.fun_fact },
  ].filter((r) => r.v)
  if (!rows.length && !profile.bio) return <p className="text-sm text-gray-400">Esta pessoa ainda não preencheu o perfil.</p>

  return (
    <div className="space-y-2">
      {profile.bio && <p className="whitespace-pre-wrap text-sm text-gray-700">{profile.bio}</p>}
      <ul className="space-y-1.5">
        {rows.map(({ Icon, label, v }) => (
          <li key={label} className="flex items-start gap-2 text-sm">
            <Icon className="mt-0.5 h-4 w-4 flex-shrink-0 text-brand-500" />
            <span><span className="text-gray-500">{label}: </span><span className="text-gray-900">{v}</span></span>
          </li>
        ))}
      </ul>
    </div>
  )
}

const RECORD_SECTIONS: { title: string; fields: { key: keyof PersonRecord; label: string; type?: string }[] }[] = [
  { title: 'Dados pessoais', fields: [{ key: 'birth_date', label: 'Data de nascimento', type: 'date' }, { key: 'phone', label: 'Telefone' }] },
  { title: 'Endereço', fields: [{ key: 'zip', label: 'CEP' }, { key: 'street', label: 'Rua' }, { key: 'number', label: 'Número' }, { key: 'complement', label: 'Complemento' }, { key: 'district', label: 'Bairro' }, { key: 'city', label: 'Cidade' }, { key: 'state', label: 'UF' }] },
  { title: 'Contato de emergência', fields: [{ key: 'emergency_name', label: 'Nome' }, { key: 'emergency_relation', label: 'Parentesco' }, { key: 'emergency_phone', label: 'Telefone' }] },
  { title: 'Dados bancários', fields: [{ key: 'bank', label: 'Banco' }, { key: 'agency', label: 'Agência' }, { key: 'account', label: 'Conta' }, { key: 'account_type', label: 'Tipo de conta' }, { key: 'pix', label: 'Chave Pix' }] },
]

/** Ficha cadastral: só lê. Os campos de edição aparecem apenas na tela de Cadastro de pessoas (`editable`), nunca no perfil. */
export function RecordSection({ slug, uuid, editable = false }: { slug: string; uuid: string; editable?: boolean }) {
  const qc = useQueryClient()
  const key = ['ws', slug, 'record', uuid]
  const { data, isLoading, error } = useQuery({ queryKey: key, queryFn: () => workspaceService.personRecord(slug, uuid) })
  const [draft, setDraft] = useState<Partial<PersonRecord>>({})
  const [msg, setMsg] = useState('')
  const save = useMutation({
    mutationFn: () => workspaceService.savePersonRecord(slug, uuid, draft),
    onSuccess: (r) => { qc.setQueryData(key, r); setDraft({}); setMsg('Ficha salva.') },
    onError: (e) => setMsg(errMsg(e)),
  })
  if (isLoading) return <Spinner />
  if (error || !data) return <p className="text-sm text-red-600">{errMsg(error)}</p>
  const val = (k: keyof PersonRecord) => (k in draft ? draft[k] : data[k]) as string | null
  const canEdit = editable && data.can_edit

  return (
    <div className="space-y-4">
      {RECORD_SECTIONS.map((sec) => (
        <fieldset key={sec.title}>
          <legend className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">{sec.title}</legend>
          <div className="grid grid-cols-2 gap-2">
            {sec.fields.map((f) => (
              <div key={f.key} className={f.key === 'street' || f.key === 'pix' || f.key === 'emergency_name' ? 'col-span-2' : ''}>
                <label className={labelCls} htmlFor={`rec-${f.key}`}>{f.label}</label>
                {canEdit ? (
                  <input id={`rec-${f.key}`} type={f.type ?? 'text'} className={inputCls} value={val(f.key) ?? ''} onChange={(e) => setDraft((d) => ({ ...d, [f.key]: e.target.value }))} />
                ) : (
                  <p className="rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-900">{f.key === 'birth_date' && val(f.key) ? fmtDate(val(f.key) as string) : val(f.key) || '—'}</p>
                )}
              </div>
            ))}
          </div>
        </fieldset>
      ))}
      {canEdit ? (
        <div className="flex items-center justify-end gap-3">
          {msg && <span className="text-sm text-gray-500" role="status">{msg}</span>}
          <button type="button" className={btnPrimary} disabled={save.isPending || !Object.keys(draft).length} onClick={() => save.mutate()}>Salvar ficha</button>
        </div>
      ) : <p className="text-xs text-gray-400">Estes dados são cadastrados pelo RH. Para corrigir algo, fale com o RH.</p>}
    </div>
  )
}
