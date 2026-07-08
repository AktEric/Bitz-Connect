import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import {
  useMyProfile,
  useUpdateProfile,
  useUploadAvatar,
  useUploadBanner,
  useAddExperience,
  useUpdateExperience,
  useDeleteExperience,
} from '@/hooks/useProfile'
import AvatarUpload from '@/components/common/AvatarUpload'
import ExperienceList from '@/components/common/ExperienceList'

const schema = z.object({
  name: z.string().min(1, 'Nome obrigatório').max(255),
  bio: z.string().max(1000).optional(),
  city: z.string().max(100).optional(),
  state: z.string().max(100).optional(),
  website: z.string().url('URL inválida').or(z.literal('')).optional(),
})

type FormValues = z.infer<typeof schema>

/**
 * Edit profile page — update fields, avatar, banner and experiences.
 * Requirements: 2.2, 2.3, 2.4, 2.5, 2.6
 */
export default function EditProfilePage() {
  const navigate = useNavigate()
  const { data: profile, isLoading } = useMyProfile()

  const updateProfile = useUpdateProfile()
  const uploadAvatar = useUploadAvatar()
  const uploadBanner = useUploadBanner()
  const addExperience = useAddExperience()
  const updateExperience = useUpdateExperience()
  const deleteExperience = useDeleteExperience()

  const isMutating =
    addExperience.isPending || updateExperience.isPending || deleteExperience.isPending

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (profile) {
      reset({
        name: profile.name,
        bio: profile.bio ?? '',
        city: profile.city ?? '',
        state: profile.state ?? '',
        website: profile.website ?? '',
      })
    }
  }, [profile, reset])

  if (isLoading || !profile) {
    return (
      <div className="flex h-64 items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }

  const experiences = (profile as any).experiences ?? []

  async function onSubmit(values: FormValues) {
    await updateProfile.mutateAsync({
      name: values.name,
      bio: values.bio || undefined,
      city: values.city || undefined,
      state: values.state || undefined,
      website: values.website || undefined,
    })
    navigate('/profile')
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      {/* Back link */}
      <button
        onClick={() => navigate(-1)}
        className="mb-6 flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
      >
        <ArrowLeft className="h-4 w-4" />
        Voltar
      </button>

      <h1 className="mb-6 text-2xl font-bold text-gray-900">Editar perfil</h1>

      {/* Banner upload */}
      <div className="relative mb-4">
        <div
          className="h-32 cursor-pointer rounded-xl bg-gradient-to-r from-primary/20 to-primary/40 hover:opacity-90"
          style={
            profile.banner_url
              ? { backgroundImage: `url(${profile.banner_url})`, backgroundSize: 'cover' }
              : undefined
          }
          onClick={() => {
            const input = document.createElement('input')
            input.type = 'file'
            input.accept = 'image/jpeg,image/png,image/webp'
            input.onchange = (e) => {
              const file = (e.target as HTMLInputElement).files?.[0]
              if (file) uploadBanner.mutate(file)
            }
            input.click()
          }}
        >
          <div className="flex h-full items-center justify-center text-sm font-medium text-gray-600">
            {uploadBanner.isPending ? 'Enviando...' : 'Clique para alterar o banner'}
          </div>
        </div>

        <div className="absolute -bottom-8 left-6">
          <AvatarUpload
            currentUrl={profile.avatar_url}
            name={profile.name}
            onFileSelect={(file) => uploadAvatar.mutate(file)}
            loading={uploadAvatar.isPending}
            size="lg"
          />
        </div>
      </div>

      {/* Profile form */}
      <form onSubmit={handleSubmit(onSubmit)} className="mt-12 space-y-5">
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Nome completo</label>
          <input
            {...register('name')}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          />
          {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name.message}</p>}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Biografia</label>
          <textarea
            {...register('bio')}
            rows={3}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="Fale um pouco sobre você..."
          />
          {errors.bio && <p className="mt-1 text-xs text-red-500">{errors.bio.message}</p>}
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Cidade</label>
            <input
              {...register('city')}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Estado</label>
            <input
              {...register('state')}
              className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Website</label>
          <input
            {...register('website')}
            type="url"
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="https://..."
          />
          {errors.website && <p className="mt-1 text-xs text-red-500">{errors.website.message}</p>}
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <button
            type="button"
            onClick={() => navigate('/profile')}
            className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
          >
            Cancelar
          </button>
          <button
            type="submit"
            disabled={isSubmitting || updateProfile.isPending}
            className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50"
          >
            {isSubmitting || updateProfile.isPending ? 'Salvando...' : 'Salvar'}
          </button>
        </div>
      </form>

      {/* Experiences section */}
      <div className="mt-8 rounded-xl border border-gray-100 bg-white p-6">
        <ExperienceList
          experiences={experiences}
          editable
          onAdd={(data) => addExperience.mutate(data)}
          onUpdate={(id, data) => updateExperience.mutate({ id, data })}
          onDelete={(id) => deleteExperience.mutate(id)}
          mutating={isMutating}
        />
      </div>
    </div>
  )
}
