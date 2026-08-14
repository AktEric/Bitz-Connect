import { useState, useEffect } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { Handshake, Sparkles, Send } from 'lucide-react'
import { useCan } from '@/hooks/useSubscription'
import { useMentorship, useCreateMentorship, useUpdateMentorship, usePublishMentorship } from '@/hooks/useMentorships'
import { Button } from '@/components/ui/Button'
import type { SessionType } from '@/types/mentorship.types'

/**
 * Editor de mentoria (criar/editar + publicar). Exclusivo de Consultores PRO
 * (gate sell_mentorships).
 * Sprint: monetization-hotelconnect-pro (Mentorias)
 */
export default function MentorshipEditorPage() {
  const { uuid } = useParams<{ uuid?: string }>()
  const isEdit = !!uuid
  const navigate = useNavigate()
  const canCreate = useCan('sell_mentorships')

  const { data: mentorship } = useMentorship(uuid ?? '')
  const createMentorship = useCreateMentorship()
  const updateMentorship = useUpdateMentorship(uuid ?? '')
  const publishMentorship = usePublishMentorship(uuid ?? '')

  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [category, setCategory] = useState('')
  const [duration, setDuration] = useState('60')
  const [sessionType, setSessionType] = useState<SessionType>('online')

  useEffect(() => {
    if (mentorship) {
      setTitle(mentorship.title)
      setDescription(mentorship.description ?? '')
      setCategory(mentorship.category ?? '')
      setDuration(String(mentorship.duration_minutes))
      setSessionType(mentorship.session_type)
    }
  }, [mentorship])

  if (!canCreate) {
    return (
      <div className="mx-auto max-w-lg px-4 py-12 text-center">
        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
          <Sparkles className="h-3.5 w-3.5" /> ConnectHotel PRO — Consultor
        </span>
        <h1 className="mt-3 text-xl font-semibold text-gray-900">Oferecer mentorias é um recurso PRO</h1>
        <p className="mt-1 text-sm text-gray-500">Assine o plano Consultor para oferecer mentorias.</p>
        <Button className="mt-4" icon={<Sparkles className="h-4 w-4" />} onClick={() => navigate('/plans')}>
          Conhecer ConnectHotel PRO
        </Button>
      </div>
    )
  }

  function handleSave(e: React.FormEvent) {
    e.preventDefault()
    const payload = {
      title: title.trim(),
      description: description.trim() || undefined,
      category: category.trim() || undefined,
      duration_minutes: Number(duration) || 60,
      session_type: sessionType,
    }
    if (isEdit) {
      updateMentorship.mutate(payload)
    } else {
      createMentorship.mutate(payload, { onSuccess: (m) => navigate(`/mentorships/${m.uuid}/edit`) })
    }
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-6">
      <div className="mb-5 flex items-center gap-2">
        <Handshake className="h-6 w-6 text-primary" />
        <h1 className="text-2xl font-semibold">{isEdit ? 'Editar mentoria' : 'Nova mentoria'}</h1>
      </div>

      <form onSubmit={handleSave} className="space-y-4 rounded-2xl border border-gray-100 bg-white p-5">
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Título</label>
          <input value={title} onChange={(e) => setTitle(e.target.value)} maxLength={255} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Ex: Mentoria de Carreira Hoteleira" />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Descrição</label>
          <textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="O que você oferece nesta mentoria?" />
        </div>
        <div className="grid grid-cols-3 gap-3">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Categoria</label>
            <input value={category} onChange={(e) => setCategory(e.target.value)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Ex: Carreira" />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Duração (min)</label>
            <input value={duration} onChange={(e) => setDuration(e.target.value)} type="number" min="15" max="480" className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Formato</label>
            <select value={sessionType} onChange={(e) => setSessionType(e.target.value as SessionType)} className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              <option value="online">Online</option>
              <option value="presential">Presencial</option>
            </select>
          </div>
        </div>
        <div className="flex items-center justify-between">
          {isEdit && mentorship?.status === 'draft' && (
            <Button type="button" variant="secondary" size="sm" icon={<Send className="h-3.5 w-3.5" />} loading={publishMentorship.isPending} onClick={() => publishMentorship.mutate()}>
              Publicar mentoria
            </Button>
          )}
          {isEdit && mentorship?.status === 'published' && <span className="rounded bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Publicada</span>}
          <div className="ml-auto">
            <Button type="submit" loading={createMentorship.isPending || updateMentorship.isPending} disabled={!title.trim()}>
              {isEdit ? 'Salvar' : 'Criar mentoria'}
            </Button>
          </div>
        </div>
      </form>

      {isEdit && (
        <p className="mt-3 text-center text-sm text-gray-400">
          Gerencie as solicitações na <button onClick={() => navigate(`/mentorships/${uuid}`)} className="font-medium text-primary hover:underline">página da mentoria</button>.
        </p>
      )}
    </div>
  )
}
