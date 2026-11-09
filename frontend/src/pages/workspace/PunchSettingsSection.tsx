import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, Crosshair, Laptop, MapPin, Plus, Trash2, Wifi } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { PunchSettings, TimeDeviceItem } from '@/types/workspace.types'
import { Panel, Pill, Spinner, btnGhost, btnPrimary, errMsg, fmtDate, inputCls, labelCls } from './ui'

const MODE_LABEL = { off: 'Desligado', optional: 'Opcional', required: 'Obrigatório' } as const
type Mode = keyof typeof MODE_LABEL
type DeviceMode = 'off' | 'log' | 'required'
const DEVICE_MODE_LABEL: Record<DeviceMode, string> = {
  off: 'Só registrar navegador e IP',
  log: 'Cadastrar aparelhos automaticamente',
  required: 'Exigir aprovação do RH',
}
type Net = { name: string; cidr: string }

const STATUS_PILL = {
  pending: { tone: 'amber', label: 'Aguardando' },
  approved: { tone: 'green', label: 'Aprovado' },
  blocked: { tone: 'red', label: 'Bloqueado' },
} as const

function DevicesList({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const [error, setError] = useState('')
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'time', 'devices'], queryFn: () => workspaceService.timeDevices(slug) })
  const refresh = () => qc.invalidateQueries({ queryKey: ['ws', slug, 'time', 'devices'] })
  const update = useMutation({
    mutationFn: ({ uuid, body }: { uuid: string; body: { name?: string; status?: TimeDeviceItem['status'] } }) => workspaceService.updateTimeDevice(slug, uuid, body),
    onSuccess: () => { setError(''); refresh() },
    onError: (e) => setError(errMsg(e)),
  })
  const remove = useMutation({
    mutationFn: (uuid: string) => workspaceService.deleteTimeDevice(slug, uuid),
    onSuccess: () => { setError(''); refresh() },
    onError: (e) => setError(errMsg(e)),
  })

  if (isLoading) return <Spinner />

  return (
    <div className="mt-6">
      <h3 className="mb-2 flex items-center gap-2 text-base font-semibold text-gray-900"><Laptop className="h-4 w-4 text-brand-600" /> Dispositivos do ponto</h3>
      {error && <p className="mb-2 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{error}</p>}
      {!data?.length ? (
        <div className="rounded-xl border border-dashed border-gray-200 bg-white p-4 text-sm text-gray-400">
          Nenhum dispositivo ainda. Eles aparecem aqui quando alguém marca o ponto com o cadastro de dispositivos ligado.
        </div>
      ) : (
        <Panel className="divide-y divide-gray-100 p-0">
          {data.map((d) => (
            <div key={d.uuid} className="flex flex-wrap items-center gap-3 px-4 py-3">
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="truncate text-sm font-semibold text-gray-900">{d.name}</span>
                  <Pill tone={STATUS_PILL[d.status].tone}>{STATUS_PILL[d.status].label}</Pill>
                </div>
                <div className="text-xs text-gray-500">
                  {d.label ?? 'Navegador desconhecido'} · {d.users_count} {d.users_count === 1 ? 'pessoa usou' : 'pessoas usaram'}
                  {d.last_ip && <> · IP {d.last_ip}</>}
                  {d.last_seen_at && <> · visto em {fmtDate(d.last_seen_at)}</>}
                  {d.registered_by && <> · cadastrado por {d.registered_by.name}</>}
                </div>
              </div>
              <div className="flex flex-wrap gap-1.5">
                {d.status !== 'approved' && <button type="button" className={btnPrimary} disabled={update.isPending} onClick={() => update.mutate({ uuid: d.uuid, body: { status: 'approved' } })}>Aprovar</button>}
                {d.status !== 'blocked' && <button type="button" className={btnGhost} disabled={update.isPending} onClick={() => update.mutate({ uuid: d.uuid, body: { status: 'blocked' } })}>Bloquear</button>}
                <button
                  type="button" className={btnGhost}
                  onClick={() => { const name = window.prompt('Novo nome do dispositivo', d.name); if (name && name.trim().length >= 2) update.mutate({ uuid: d.uuid, body: { name: name.trim() } }) }}
                >
                  Renomear
                </button>
                <button
                  type="button" className="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label={`Remover ${d.name}`}
                  onClick={() => { if (window.confirm(`Remover o dispositivo “${d.name}”? As marcações antigas continuam com o nome dele.`)) remove.mutate(d.uuid) }}
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            </div>
          ))}
        </Panel>
      )}
    </div>
  )
}

export default function PunchSettingsSection({ slug }: { slug: string }) {
  const qc = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['ws', slug, 'time', 'settings'], queryFn: () => workspaceService.timeSettings(slug) })
  const [geo, setGeo] = useState<Mode>('off')
  const [photo, setPhoto] = useState<Mode>('off')
  const [fenceOn, setFenceOn] = useState(false)
  const [lat, setLat] = useState('')
  const [lng, setLng] = useState('')
  const [radius, setRadius] = useState('150')
  const [block, setBlock] = useState(false)
  const [devices, setDevices] = useState<DeviceMode>('off')
  const [networks, setNetworks] = useState<Net[]>([])
  const [netBlock, setNetBlock] = useState(false)
  const [error, setError] = useState('')
  const [saved, setSaved] = useState(false)

  useEffect(() => {
    if (!data) return
    setGeo(data.geo); setPhoto(data.photo)
    setFenceOn(!!data.fence)
    setLat(data.fence ? String(data.fence.lat) : ''); setLng(data.fence ? String(data.fence.lng) : '')
    setRadius(String(data.fence?.radius_m ?? 150)); setBlock(data.fence?.block ?? false)
    setDevices(data.devices ?? 'off'); setNetworks(data.networks ?? []); setNetBlock(data.networks_block ?? false)
  }, [data])

  const save = useMutation({
    mutationFn: () => {
      const body: PunchSettings = {
        geo, photo,
        fence: fenceOn ? { lat: parseFloat(lat), lng: parseFloat(lng), radius_m: parseInt(radius, 10), block } : null,
        devices,
        networks: networks.filter((n) => n.name.trim() && n.cidr.trim()).map((n) => ({ name: n.name.trim(), cidr: n.cidr.trim() })),
        networks_block: netBlock,
      }
      return workspaceService.saveTimeSettings(slug, body)
    },
    onSuccess: () => { setError(''); setSaved(true); qc.invalidateQueries({ queryKey: ['ws', slug, 'time'] }) },
    onError: (e) => { setSaved(false); setError(errMsg(e)) },
  })

  const touch = () => setSaved(false)
  const useHere = () => {
    setError('')
    navigator.geolocation?.getCurrentPosition(
      (p) => { setLat(p.coords.latitude.toFixed(6)); setLng(p.coords.longitude.toFixed(6)); touch() },
      () => setError('Não foi possível obter a localização deste aparelho. Digite as coordenadas.'),
      { enableHighAccuracy: true, timeout: 10_000 },
    )
  }
  const setNet = (i: number, patch: Partial<Net>) => { setNetworks((cur) => cur.map((n, j) => (j === i ? { ...n, ...patch } : n))); touch() }

  if (isLoading) return <Spinner />
  const modeSelect = (value: Mode, set: (m: Mode) => void, label: string) => (
    <div>
      <label className={labelCls}>{label}</label>
      <select className={inputCls} value={value} onChange={(e) => { set(e.target.value as Mode); touch() }}>
        {(Object.keys(MODE_LABEL) as Mode[]).map((m) => <option key={m} value={m}>{MODE_LABEL[m]}</option>)}
      </select>
    </div>
  )

  return (
    <section className="mb-8">
      <div className="mb-1 flex items-center gap-2">
        <MapPin className="h-5 w-5 text-brand-600" />
        <h2 className="text-lg font-semibold text-gray-900">Ponto: local, aparelho e rede</h2>
      </div>
      <p className="mb-3 text-sm text-gray-500">
        Reforça a confiança nas marcações. Foto, localização, IP e aparelho são dados pessoais: guarde só o que faz sentido para a operação e avise a equipe.
      </p>
      <Panel className="space-y-4">
        <div className="grid gap-3 sm:grid-cols-2">
          {modeSelect(geo, setGeo, 'Localização na marcação')}
          {modeSelect(photo, setPhoto, 'Foto na marcação')}
        </div>

        <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
          <input type="checkbox" className="rounded border-gray-300 text-brand-600" checked={fenceOn} onChange={(e) => { setFenceOn(e.target.checked); touch() }} />
          Usar cerca geográfica (local de trabalho)
        </label>

        {fenceOn && (
          <div className="space-y-3 rounded-xl bg-gray-50 p-3">
            <div className="grid gap-3 sm:grid-cols-3">
              <div><label className={labelCls}>Latitude</label><input className={inputCls} inputMode="decimal" value={lat} onChange={(e) => { setLat(e.target.value); touch() }} placeholder="-23.550520" /></div>
              <div><label className={labelCls}>Longitude</label><input className={inputCls} inputMode="decimal" value={lng} onChange={(e) => { setLng(e.target.value); touch() }} placeholder="-46.633308" /></div>
              <div><label className={labelCls}>Raio (metros)</label><input className={inputCls} inputMode="numeric" value={radius} onChange={(e) => { setRadius(e.target.value); touch() }} /></div>
            </div>
            <button type="button" className={btnGhost} onClick={useHere}><Crosshair className="h-4 w-4" /> Usar minha localização atual</button>
            <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" className="rounded border-gray-300 text-brand-600" checked={block} onChange={(e) => { setBlock(e.target.checked); touch() }} />
              Bloquear marcações fora do raio (sem isso, apenas sinaliza “fora do local”)
            </label>
          </div>
        )}

        <div className="border-t border-gray-100 pt-4">
          <label className={labelCls}>Dispositivos de marcação</label>
          <select className={`${inputCls} sm:max-w-sm`} value={devices} onChange={(e) => { setDevices(e.target.value as DeviceMode); touch() }}>
            {(Object.keys(DEVICE_MODE_LABEL) as DeviceMode[]).map((m) => <option key={m} value={m}>{DEVICE_MODE_LABEL[m]}</option>)}
          </select>
          <p className="mt-1 text-xs text-gray-500">
            O navegador não revela o nome do computador. Aqui o nome é o que o RH ou o colaborador dá ao aparelho (ex.: “Recepção — PC 01”).
            Em “Exigir aprovação”, um aparelho novo só marca ponto depois que o RH aprovar.
          </p>
        </div>

        <div className="border-t border-gray-100 pt-4">
          <div className="mb-2 flex items-center gap-2 text-sm font-medium text-gray-900"><Wifi className="h-4 w-4 text-brand-600" /> Redes autorizadas (por IP)</div>
          {networks.length === 0 && <p className="mb-2 text-xs text-gray-500">Nenhuma rede cadastrada: o IP é registrado em cada marcação, sem verificação.</p>}
          <div className="space-y-2">
            {networks.map((n, i) => (
              <div key={i} className="flex flex-wrap items-center gap-2">
                <input className={`${inputCls} !w-auto min-w-[10rem] flex-1`} value={n.name} placeholder="Nome (ex.: Matriz — Wi-Fi)" maxLength={80} onChange={(e) => setNet(i, { name: e.target.value })} aria-label="Nome da rede" />
                <input className={`${inputCls} !w-auto min-w-[10rem] flex-1`} value={n.cidr} placeholder="IP ou faixa (200.150.10.0/24)" maxLength={50} onChange={(e) => setNet(i, { cidr: e.target.value })} aria-label="IP ou faixa da rede" />
                <button type="button" className="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="Remover rede" onClick={() => { setNetworks((cur) => cur.filter((_, j) => j !== i)); touch() }}><Trash2 className="h-4 w-4" /></button>
              </div>
            ))}
          </div>
          <div className="mt-2 flex flex-wrap gap-2">
            <button type="button" className={btnGhost} disabled={networks.length >= 10} onClick={() => { setNetworks((cur) => [...cur, { name: '', cidr: '' }]); touch() }}><Plus className="h-4 w-4" /> Adicionar rede</button>
            {data?.current_ip && (
              <button type="button" className={btnGhost} disabled={networks.length >= 10} onClick={() => { setNetworks((cur) => [...cur, { name: 'Rede atual', cidr: data.current_ip! }]); touch() }}>
                Usar meu IP atual ({data.current_ip})
              </button>
            )}
          </div>
          {networks.length > 0 && (
            <label className="mt-3 flex cursor-pointer items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" className="rounded border-gray-300 text-brand-600" checked={netBlock} onChange={(e) => { setNetBlock(e.target.checked); touch() }} />
              Bloquear marcações fora dessas redes (sem isso, apenas sinaliza “fora da rede”)
            </label>
          )}
        </div>

        {error && <p className="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{error}</p>}
        <div className="flex items-center gap-3">
          <button type="button" className={btnPrimary} disabled={save.isPending || (fenceOn && (!lat || !lng))} onClick={() => save.mutate()}>
            {save.isPending ? 'Salvando…' : 'Salvar configuração'}
          </button>
          {saved && <span className="inline-flex items-center gap-1 text-sm text-emerald-700"><Check className="h-4 w-4" /> Salvo</span>}
        </div>
      </Panel>

      <DevicesList slug={slug} />
    </section>
  )
}
