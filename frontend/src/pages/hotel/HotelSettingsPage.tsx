import { useEffect } from 'react'
import { useParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Camera, Building2 } from 'lucide-react'
import { useHotel, useUpdateHotel, useUploadLogo, useUploadCover } from '@/hooks/useHotel'

const schema = z.object({
  name:          z.string().min(2, 'Nome deve ter ao menos 2 caracteres').max(255),
  description:   z.string().min(10, 'Descrição deve ter ao menos 10 caracteres').max(5000),
  city:          z.string().max(100).optional(),
  state:         z.string().max(100).optional(),
  country:       z.string().max(100).optional(),
  website:       z.string().url('URL inválida').or(z.literal('')).optional(),
  phone:         z.string().max(30).optional(),
  contact_email: z.string().email('E-mail inválido').or(z.literal('')).optional(),
})

type FormValues = z.infer<typeof schema>

/**
 * HotelSettingsPage — edição dos dados do hotel (nome, descrição, contato,
 * logo e capa). Reaproveita os mesmos campos/validação de CreateHotelPage.
 */
export default function HotelSettingsPage() {
  const { slug = '' } = useParams<{ slug: string }>()
  const { data: hotel, isLoading } = useHotel(slug)
  const updateHotel = useUpdateHotel(slug)
  const uploadLogo = useUploadLogo(slug)
  const uploadCover = useUploadCover(slug)

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (!hotel) return
    reset({
      name: hotel.name,
      description: hotel.description ?? '',
      city: hotel.city ?? '',
      state: hotel.state ?? '',
      country: hotel.country ?? '',
      website: hotel.website ?? '',
      phone: hotel.phone ?? '',
      contact_email: hotel.contact_email ?? '',
    })
  }, [hotel, reset])

  async function onSubmit(values: FormValues) {
    await updateHotel.mutateAsync({
      name: values.name,
      description: values.description,
      city: values.city || undefined,
      state: values.state || undefined,
      country: values.country || undefined,
      website: values.website || undefined,
      phone: values.phone || undefined,
      contact_email: values.contact_email || undefined,
    })
  }

  function handleLogoSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (file) uploadLogo.mutate(file)
  }

  function handleCoverSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (file) uploadCover.mutate(file)
  }

  if (isLoading || !hotel) {
    return (
      <div className="flex h-64 items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <h1 className="mb-1 text-2xl font-bold text-gray-900">Configurações</h1>
      <p className="mb-6 text-sm text-gray-500">Atualize as informações públicas do seu hotel.</p>

      {/* Cover + Logo */}
      <div className="mb-8 overflow-hidden rounded-2xl border border-gray-100 bg-white">
        <div
          className="relative h-36 w-full bg-gray-100 bg-cover bg-center"
          style={hotel.cover_url ? { backgroundImage: `url(${hotel.cover_url})` } : undefined}
        >
          <label className="absolute inset-0 flex cursor-pointer items-center justify-center bg-black/0 text-white opacity-0 transition-opacity hover:bg-black/30 hover:opacity-100">
            <span className="flex items-center gap-1.5 rounded-lg bg-white/90 px-3 py-1.5 text-xs font-medium text-gray-800">
              <Camera className="h-3.5 w-3.5" />
              {uploadCover.isPending ? 'Enviando...' : 'Alterar capa'}
            </span>
            <input type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={handleCoverSelect} />
          </label>
        </div>
        <div className="flex items-center gap-4 px-5 pb-5 pt-0">
          <div className="-mt-8 h-20 w-20 flex-shrink-0 overflow-hidden rounded-xl border-4 border-white bg-white shadow-md">
            <label className="group relative flex h-full w-full cursor-pointer items-center justify-center bg-primary/10">
              {hotel.logo_url ? (
                <img src={hotel.logo_url} alt={hotel.name} className="h-full w-full object-cover" />
              ) : (
                <Building2 className="h-8 w-8 text-primary" />
              )}
              <span className="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition-opacity group-hover:bg-black/40 group-hover:opacity-100">
                <Camera className="h-4 w-4 text-white" />
              </span>
              <input type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={handleLogoSelect} />
            </label>
          </div>
          <div className="pt-2 text-sm text-gray-500">
            {uploadLogo.isPending ? 'Enviando logo...' : 'Clique na capa ou na logo para alterar.'}
          </div>
        </div>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">
            Nome do hotel <span className="text-red-500">*</span>
          </label>
          <input
            {...register('name')}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          />
          {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name.message}</p>}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">
            Descrição <span className="text-red-500">*</span>
          </label>
          <textarea
            {...register('description')}
            rows={4}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
          />
          {errors.description && <p className="mt-1 text-xs text-red-500">{errors.description.message}</p>}
        </div>

        <div className="grid grid-cols-3 gap-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Cidade</label>
            <input {...register('city')} className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Estado</label>
            <input {...register('state')} className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">País</label>
            <input {...register('country')} className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
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

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">Telefone</label>
            <input {...register('phone')} className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-gray-700">E-mail de contato</label>
            <input {...register('contact_email')} type="email" className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" />
            {errors.contact_email && <p className="mt-1 text-xs text-red-500">{errors.contact_email.message}</p>}
          </div>
        </div>

        <div className="flex justify-end pt-2">
          <button
            type="submit"
            disabled={isSubmitting || updateHotel.isPending}
            className="rounded-md bg-primary px-5 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50"
          >
            {isSubmitting || updateHotel.isPending ? 'Salvando...' : 'Salvar alterações'}
          </button>
        </div>
      </form>
    </div>
  )
}
