import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, Crosshair, MapPin } from 'lucide-react'
import workspaceService from '@/services/workspace.service'
import type { PunchSettings } from '@/types/workspace.types'
import { Panel, Spinner, btnGhost, btnPrimary, errMsg, inputCls, labelCls } from './ui'

const MODE_LABEL = { off: 'Desligado', optional: 'Opcional', required: 'Obrigatório' } as const
type Mode = keyof typeof MODE_LABEL

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
  const [error, setError] = useState('')
  const [saved, setSaved] = useState(false)

  useEffect(() => {
    if (!data) return
    setGeo(data.geo); setPhoto(data.photo)
    setFenceOn(!!data.fence)
    setLat(data.fence ? String(data.fence.lat) : ''); setLng(data.fence ? String(data.fence.lng) : '')
    setRadius(String(data.fence?.radius_m ?? 150)); setBlock(data.fence?.block ?? false)
  }, [data])

  const save = useMutation({
    mutationFn: () => {
      const body: PunchSettings = {
        geo, photo,
        fence: fenceOn ? { lat: parseFloat(lat), lng: parseFloat(lng), radius_m: parseInt(radius, 10), block } : null,
      }
      return workspaceService.saveTimeSettings(slug, body)
    },
    onSuccess: () => { setError(''); setSaved(true); qc.invalidateQueries({ queryKey: ['ws', slug, 'time'] }) },
    onError: (e) => { setSaved(false); setError(errMsg(e)) },
  })

  const useHere = () => {
    setError('')
    navigator.geolocation?.getCurrentPosition(
      (p) => { setLat(p.coords.latitude.toFixed(6)); setLng(p.coords.longitude.toFixed(6)) },
      () => setError('Não foi possível obter a localização deste aparelho. Digite as coordenadas.'),
      { enableHighAccuracy: true, timeout: 10_000 },
    )
  }

  if (isLoading) return <Spinner />
  const modeSelect = (value: Mode, set: (m: Mode) => void, label: string) => (
    <div>
      <label className={labelCls}>{label}</label>
      <select className={inputCls} value={value} onChange={(e) => { set(e.target.value as Mode); setSaved(false) }}>
        {(Object.keys(MODE_LABEL) as Mode[]).map((m) => <option key={m} value={m}>{MODE_LABEL[m]}</option>)}
      </select>
    </div>
  )

  return (
    <section className="mb-8">
      <div className="mb-1 flex items-center gap-2">
        <MapPin className="h-5 w-5 text-violet-600" />
        <h2 className="text-lg font-semibold text-gray-900">Ponto: localização e foto</h2>
      </div>
      <p className="mb-3 text-sm text-gray-500">
        Reforça a confiança nas marcações. A foto e a localização são dados pessoais: guarde só se fizer sentido para a operação e avise a equipe.
      </p>
      <Panel className="space-y-4">
        <div className="grid gap-3 sm:grid-cols-2">
          {modeSelect(geo, setGeo, 'Localização na marcação')}
          {modeSelect(photo, setPhoto, 'Foto na marcação')}
        </div>

        <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
          <input type="checkbox" className="rounded border-gray-300 text-violet-600" checked={fenceOn} onChange={(e) => { setFenceOn(e.target.checked); setSaved(false) }} />
          Usar cerca geográfica (local de trabalho)
        </label>

        {fenceOn && (
          <div className="space-y-3 rounded-xl bg-gray-50 p-3">
            <div className="grid gap-3 sm:grid-cols-3">
              <div><label className={labelCls}>Latitude</label><input className={inputCls} inputMode="decimal" value={lat} onChange={(e) => setLat(e.target.value)} placeholder="-23.550520" /></div>
              <div><label className={labelCls}>Longitude</label><input className={inputCls} inputMode="decimal" value={lng} onChange={(e) => setLng(e.target.value)} placeholder="-46.633308" /></div>
              <div><label className={labelCls}>Raio (metros)</label><input className={inputCls} inputMode="numeric" value={radius} onChange={(e) => setRadius(e.target.value)} /></div>
            </div>
            <button type="button" className={btnGhost} onClick={useHere}><Crosshair className="h-4 w-4" /> Usar minha localização atual</button>
            <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
              <input type="checkbox" className="rounded border-gray-300 text-violet-600" checked={block} onChange={(e) => setBlock(e.target.checked)} />
              Bloquear marcações fora do raio (sem isso, apenas sinaliza “fora do local”)
            </label>
          </div>
        )}

        {error && <p className="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{error}</p>}
        <div className="flex items-center gap-3">
          <button type="button" className={btnPrimary} disabled={save.isPending || (fenceOn && (!lat || !lng))} onClick={() => save.mutate()}>
            {save.isPending ? 'Salvando…' : 'Salvar configuração'}
          </button>
          {saved && <span className="inline-flex items-center gap-1 text-sm text-emerald-700"><Check className="h-4 w-4" /> Salvo</span>}
        </div>
      </Panel>
    </section>
  )
}
