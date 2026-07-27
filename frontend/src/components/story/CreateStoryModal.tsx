import { useEffect, useRef, useState } from 'react'
import { X, Briefcase, GraduationCap, ImagePlus } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { UpgradeModal } from '@/components/subscription/UpgradeModal'
import { useAuth } from '@/hooks/useAuth'
import { useCan } from '@/hooks/useSubscription'
import { useMyHotels } from '@/hooks/useHotel'
import { useManageJobs } from '@/hooks/useJobs'
import { useMentorMentorships } from '@/hooks/useMentorships'
import { useCreateStory } from '@/hooks/useStories'
import type { StoryType } from '@/types/story.types'

interface CreateStoryModalProps {
  open: boolean
  onClose: () => void
}

/** Formulário de publicação de story a partir de uma vaga ou mentoria já existente. */
export function CreateStoryModal({ open, onClose }: CreateStoryModalProps) {
  const { user } = useAuth()
  const canCreatePersonal = useCan('publish_stories')
  const { data: myHotels } = useMyHotels()
  const proHotels = (myHotels?.data ?? []).filter((h) => h.is_admin && h.is_pro)

  const [storyType, setStoryType] = useState<StoryType | null>(null)
  const [hotelSlug, setHotelSlug] = useState<string>('')
  const [itemUuid, setItemUuid] = useState<string>('')
  const [caption, setCaption] = useState('')
  const [image, setImage] = useState<File | null>(null)
  const [imagePreview, setImagePreview] = useState<string | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const createStory = useCreateStory()

  const handleImageChange = (file: File | null) => {
    setImage(file)
    setImagePreview((prev) => {
      if (prev) URL.revokeObjectURL(prev)
      return file ? URL.createObjectURL(file) : null
    })
  }

  const { data: manageJobsData } = useManageJobs(hotelSlug, 1)
  const jobs = (manageJobsData?.data ?? []).filter((j) => j.status === 'active')

  const { data: mentorshipsData } = useMentorMentorships(user?.uuid ?? '', storyType === 'mentorship')
  const mentorships = (mentorshipsData?.data ?? []).filter((m) => m.status === 'published')

  useEffect(() => {
    if (!open) return
    setStoryType(null)
    setHotelSlug(proHotels[0]?.slug ?? '')
    setItemUuid('')
    setCaption('')
    handleImageChange(null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  useEffect(() => {
    return () => {
      if (imagePreview) URL.revokeObjectURL(imagePreview)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    setItemUuid('')
  }, [storyType, hotelSlug])

  if (!open) return null

  const canCreateAny = canCreatePersonal || proHotels.length > 0
  if (!canCreateAny) {
    return (
      <UpgradeModal
        open={open}
        onClose={onClose}
        title="Publique stories em destaque"
        description="Dê destaque às suas vagas ou mentorias com stories de 10s no topo do feed."
        highlights={[
          'Story de vaga: mensagem, candidatura, curtida e compartilhamento',
          'Story de mentoria: mensagem, acesso à mentoria, curtida e compartilhamento',
          'Aparece em destaque no topo do feed para toda a comunidade',
        ]}
      />
    )
  }

  const handleSubmit = () => {
    if (!storyType || !itemUuid) return
    createStory.mutate(
      {
        story_type: storyType,
        job_uuid: storyType === 'job' ? itemUuid : undefined,
        mentorship_uuid: storyType === 'mentorship' ? itemUuid : undefined,
        caption: caption.trim() || undefined,
        image: image ?? undefined,
      },
      { onSuccess: onClose },
    )
  }

  return (
    <>
      <div className="fixed inset-0 z-50 bg-black/50" aria-hidden="true" onClick={onClose} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          role="dialog"
          aria-modal="true"
          className="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl"
          onClick={(e) => e.stopPropagation()}
        >
          <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <h2 className="text-lg font-semibold text-gray-900">Criar story</h2>
            <button onClick={onClose} aria-label="Fechar" className="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
              <X className="h-4 w-4" />
            </button>
          </div>

          <div className="max-h-[70vh] overflow-y-auto px-5 py-4">
            {/* Tipo */}
            <div className="flex gap-2">
              {proHotels.length > 0 && (
                <button
                  onClick={() => setStoryType('job')}
                  className={`flex flex-1 items-center justify-center gap-1.5 rounded-xl border px-3 py-2.5 text-sm font-medium transition-colors ${
                    storyType === 'job' ? 'border-primary bg-violet-50 text-primary' : 'border-gray-200 text-gray-600 hover:bg-gray-50'
                  }`}
                >
                  <Briefcase className="h-4 w-4" /> Vaga
                </button>
              )}
              {canCreatePersonal && (
                <button
                  onClick={() => setStoryType('mentorship')}
                  className={`flex flex-1 items-center justify-center gap-1.5 rounded-xl border px-3 py-2.5 text-sm font-medium transition-colors ${
                    storyType === 'mentorship' ? 'border-primary bg-violet-50 text-primary' : 'border-gray-200 text-gray-600 hover:bg-gray-50'
                  }`}
                >
                  <GraduationCap className="h-4 w-4" /> Mentoria
                </button>
              )}
            </div>

            {storyType === 'job' && (
              <div className="mt-4 space-y-3">
                {proHotels.length > 1 && (
                  <div>
                    <label className="mb-1 block text-sm font-medium text-gray-700">Hotel</label>
                    <select
                      value={hotelSlug}
                      onChange={(e) => setHotelSlug(e.target.value)}
                      className="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                    >
                      {proHotels.map((h) => (
                        <option key={h.slug} value={h.slug}>{h.name}</option>
                      ))}
                    </select>
                  </div>
                )}
                <div>
                  <label className="mb-1 block text-sm font-medium text-gray-700">Vaga</label>
                  {jobs.length === 0 ? (
                    <p className="text-sm text-gray-400">Nenhuma vaga ativa neste hotel.</p>
                  ) : (
                    <select
                      value={itemUuid}
                      onChange={(e) => setItemUuid(e.target.value)}
                      className="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                    >
                      <option value="">Selecione uma vaga</option>
                      {jobs.map((j) => (
                        <option key={j.uuid} value={j.uuid}>{j.title}</option>
                      ))}
                    </select>
                  )}
                </div>
              </div>
            )}

            {storyType === 'mentorship' && (
              <div className="mt-4">
                <label className="mb-1 block text-sm font-medium text-gray-700">Mentoria</label>
                {mentorships.length === 0 ? (
                  <p className="text-sm text-gray-400">Nenhuma mentoria publicada.</p>
                ) : (
                  <select
                    value={itemUuid}
                    onChange={(e) => setItemUuid(e.target.value)}
                    className="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  >
                    <option value="">Selecione uma mentoria</option>
                    {mentorships.map((m) => (
                      <option key={m.uuid} value={m.uuid}>{m.title}</option>
                    ))}
                  </select>
                )}
              </div>
            )}

            {storyType && (
              <div className="mt-4">
                <label className="mb-1 block text-sm font-medium text-gray-700">Imagem de fundo (opcional)</label>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  className="hidden"
                  onChange={(e) => handleImageChange(e.target.files?.[0] ?? null)}
                />
                {imagePreview ? (
                  <div className="relative overflow-hidden rounded-xl">
                    <img src={imagePreview} alt="Pré-visualização" className="h-40 w-full object-cover" />
                    <button
                      onClick={() => handleImageChange(null)}
                      className="absolute right-2 top-2 rounded-full bg-black/60 p-1 text-white hover:bg-black/80"
                      aria-label="Remover imagem"
                    >
                      <X className="h-3.5 w-3.5" />
                    </button>
                  </div>
                ) : (
                  <button
                    onClick={() => fileInputRef.current?.click()}
                    className="flex h-24 w-full flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-gray-300 text-gray-400 hover:border-primary hover:text-primary"
                  >
                    <ImagePlus className="h-5 w-5" />
                    <span className="text-xs">Sem imagem, o story usa um card padrão</span>
                  </button>
                )}
              </div>
            )}

            {storyType && (
              <div className="mt-4">
                <label className="mb-1 block text-sm font-medium text-gray-700">Legenda (opcional)</label>
                <textarea
                  value={caption}
                  onChange={(e) => setCaption(e.target.value)}
                  maxLength={280}
                  rows={2}
                  className="w-full resize-none rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"
                  placeholder="Uma frase curta para o story..."
                />
              </div>
            )}
          </div>

          <div className="border-t border-gray-100 px-5 py-4">
            <Button fullWidth disabled={!storyType || !itemUuid} loading={createStory.isPending} onClick={handleSubmit}>
              Publicar story
            </Button>
          </div>
        </div>
      </div>
    </>
  )
}
