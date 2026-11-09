import { useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Bell, Cake, Camera } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import userService from '@/services/user.service'
import { useNotifications } from '@/hooks/useNotifications'
import { useAuth } from '@/hooks/useAuth'
import { Avatar } from '@/components/ui/Avatar'
import type { PersonProfileFields } from '@/types/workspace.types'
import { ProfileFacts, RecordSection } from './PersonCardModal'
import ProfileCareer from './ProfileCareer'
import ProfileOrg from './ProfileOrg'
import { useCan } from './permissions'
import { Empty, Panel, Spinner, btnPrimary, errMsg, fmtDate, inputCls, labelCls, timeAgo, useWs } from './ui'

const FIELDS: { key: keyof PersonProfileFields; label: string; placeholder: string; area?: boolean }[] = [
  { key: 'bio', label: 'Sobre mim', placeholder: 'Conte um pouco sobre você, o que faz, o que gosta…', area: true },
  { key: 'favorite_book', label: 'Livro favorito', placeholder: 'Ex.: Dom Casmurro' },
  { key: 'favorite_food', label: 'Comida favorita', placeholder: 'Ex.: Feijoada' },
  { key: 'favorite_movie', label: 'Filme ou série favorito', placeholder: 'Ex.: Cidade de Deus' },
  { key: 'hobbies', label: 'Hobbies', placeholder: 'Ex.: corrida, violão, jardinagem' },
  { key: 'hometown', label: 'Cidade natal', placeholder: 'Ex.: Santos' },
  { key: 'fun_fact', label: 'Uma curiosidade sobre mim', placeholder: 'Algo que quase ninguém sabe' },
]
const MONTHS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro']

type Tab = 'social' | 'career' | 'record' | 'notices' | 'team'

function SocialEditor({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const { data } = useQuery({ queryKey: ['ws', slug, 'myprofile'], queryFn: () => workspaceService.myProfile(slug) })
  const [draft, setDraft] = useState<Partial<PersonProfileFields>>({})
  const [visible, setVisible] = useState<boolean | null>(null)
  const [msg, setMsg] = useState('')
  const save = useMutation({
    mutationFn: () => workspaceService.saveMyProfile(slug, { ...draft, ...(visible !== null ? { birthday_visible: visible } : {}) }),
    onSuccess: (r) => { qc.setQueryData(['ws', slug, 'myprofile'], r); qc.invalidateQueries({ queryKey: ['ws', slug, 'card'] }); setDraft({}); setVisible(null); setMsg('Perfil salvo.') },
    onError: (e) => setMsg(errMsg(e)),
  })
  if (!data) return <Spinner />
  const val = (k: keyof PersonProfileFields) => (k in draft ? draft[k] : data.profile[k]) ?? ''
  const dirty = Object.keys(draft).length > 0 || visible !== null

  return (
    <Panel>
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
        <input type="checkbox" className="mt-0.5 rounded border-gray-300 text-brand-600" checked={visible ?? data.birthday_visible} onChange={(e) => setVisible(e.target.checked)} />
        <span>Mostrar meu aniversário para a equipe e receber os parabéns no mural<br />
          <span className="text-xs text-gray-400">{data.has_birth_date ? 'Só o dia e o mês aparecem; o ano fica visível apenas para você e para o RH.' : 'Sua data de nascimento ainda não foi cadastrada pelo RH.'}</span>
        </span>
      </label>
      <div className="mt-4 flex items-center justify-end gap-3">
        {msg && <span className="text-sm text-gray-500" role="status">{msg}</span>}
        <button type="button" className={btnPrimary} disabled={!dirty || save.isPending} onClick={() => save.mutate()}>Salvar perfil</button>
      </div>
    </Panel>
  )
}

function Notices() {
  const { data, isLoading } = useNotifications({ per_page: 20 })
  const items = data?.data ?? []
  if (isLoading) return <Spinner />
  if (!items.length) return <Empty text="Nenhum aviso por enquanto." />

  return (
    <Panel className="divide-y divide-gray-100 p-0">
      {items.map((n) => {
        const body = (
          <div className="flex items-start gap-3 px-4 py-3">
            <Bell className={`mt-0.5 h-4 w-4 flex-shrink-0 ${n.read ? 'text-gray-300' : 'text-brand-600'}`} />
            <div className="min-w-0 flex-1">
              <p className={`text-sm ${n.read ? 'text-gray-600' : 'font-semibold text-gray-900'}`}>{n.title}</p>
              {n.description && <p className="truncate text-xs text-gray-500">{n.description}</p>}
            </div>
            <span className="flex-shrink-0 text-xs text-gray-400">{timeAgo(n.created_at)}</span>
          </div>
        )
        return n.link?.startsWith('/') ? <Link key={n.uuid} to={n.link} className="block hover:bg-gray-50">{body}</Link> : <div key={n.uuid}>{body}</div>
      })}
    </Panel>
  )
}

/** Perfil em abas. Na própria página a pessoa edita só o perfil social; a ficha cadastral é do RH (leitura para a pessoa, edição para o RH). */
export default function ProfileView({ uuid, self }: { uuid: string; self: boolean }) {
  const { slug } = useWs()
  const canManagePeople = useCan('people.manage')
  const { user, setUser } = useAuth()
  const qc = useQueryClient()
  const fileRef = useRef<HTMLInputElement>(null)
  const [tab, setTab] = useState<Tab>('social')
  const [msg, setMsg] = useState('')
  const { data: card, isLoading } = useQuery({ queryKey: ['ws', slug, 'card', uuid], queryFn: () => workspaceService.personCard(slug, uuid) })
  const avatar = useMutation({
    mutationFn: (f: File) => userService.uploadAvatar(f),
    onSuccess: (r) => { if (r.data) setUser(r.data); qc.invalidateQueries({ queryKey: ['ws', slug] }); qc.invalidateQueries({ queryKey: ['workspace', slug] }); setMsg('Foto atualizada.') },
    onError: (e) => setMsg(errMsg(e)),
  })
  if (isLoading || !card) return <Spinner />

  const tabs: { key: Tab; label: string }[] = [{ key: 'social', label: 'Perfil social' }, { key: 'career', label: 'Carreira' }]
  if (self || canManagePeople) tabs.push({ key: 'record', label: 'Dados cadastrais' })
  if (self) tabs.push({ key: 'notices', label: 'Avisos' })
  tabs.push({ key: 'team', label: 'Equipe e liderança' })
  const b = card.birthday

  return (
    <div className="space-y-5">
      <Panel className="flex items-center gap-4">
        <div className="relative flex-shrink-0">
          <Avatar src={self ? (user?.avatar_url ?? card.person?.avatar_url) : card.person?.avatar_url} name={card.person?.name ?? '?'} size="2xl" />
          {self && (
            <>
              <button type="button" onClick={() => fileRef.current?.click()} disabled={avatar.isPending} aria-label="Trocar foto"
                className="absolute -bottom-1 -right-1 rounded-full bg-brand-600 p-2 text-white shadow hover:bg-brand-700"><Camera className="h-4 w-4" /></button>
              <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={(e) => { const f = e.target.files?.[0]; if (f) avatar.mutate(f); e.target.value = '' }} />
            </>
          )}
        </div>
        <div className="min-w-0">
          <h1 className="truncate text-xl font-bold text-gray-900">{card.person?.name}</h1>
          <p className="text-sm text-gray-600">{[card.person?.job_title, card.department].filter(Boolean).join(' · ') || 'Cargo não informado'}</p>
          <p className="mt-0.5 flex flex-wrap gap-x-3 text-xs text-gray-400">
            {card.hired_at && <span>Desde {fmtDate(card.hired_at)}</span>}
            {b && <span className="inline-flex items-center gap-1"><Cake className="h-3 w-3" /> {b.day} de {MONTHS[b.month - 1]}</span>}
            {msg && <span role="status" className="text-brand-600">{msg}</span>}
          </p>
        </div>
      </Panel>

      <div role="tablist" aria-label="Seções do perfil" className="flex gap-1 overflow-x-auto border-b border-gray-200">
        {tabs.map((t) => (
          <button key={t.key} role="tab" aria-selected={tab === t.key} type="button" onClick={() => setTab(t.key)}
            className={`whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium transition ${tab === t.key ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-800'}`}>
            {t.label}
          </button>
        ))}
      </div>

      <div role="tabpanel">
        {tab === 'social' && (self ? <SocialEditor slug={slug} /> : <Panel><ProfileFacts profile={card.profile as unknown as Record<string, string | null>} /></Panel>)}
        {tab === 'record' && <Panel><RecordSection slug={slug} uuid={uuid} /></Panel>}
        {tab === 'notices' && <Notices />}
        {tab === 'career' && <ProfileCareer slug={slug} uuid={uuid} />}
        {tab === 'team' && <ProfileOrg slug={slug} uuid={uuid} />}
      </div>
    </div>
  )
}
