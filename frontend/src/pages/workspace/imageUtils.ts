/**
 * Reduz a foto no aparelho antes do envio (lado maior de 1600 px, JPEG 82%): menos dados na rede e no disco,
 * e a recodificação já descarta EXIF/GPS (o servidor também limpa, como segunda barreira).
 */
export async function resizeImage(file: File, maxSide = 1600, quality = 0.82): Promise<Blob> {
  const bitmap = await createImageBitmap(file)
  const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height))
  const canvas = document.createElement('canvas')
  canvas.width = Math.round(bitmap.width * scale)
  canvas.height = Math.round(bitmap.height * scale)
  canvas.getContext('2d')?.drawImage(bitmap, 0, 0, canvas.width, canvas.height)
  bitmap.close?.()
  const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality))
  if (!blob) throw new Error('Não foi possível processar a imagem.')
  return blob
}
