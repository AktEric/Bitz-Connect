export interface Sticker { id: string; emoji: string; label: string; bg: string }

/** Figurinhas prontas (o servidor só guarda o id; o desenho vive aqui). */
export const STICKERS: Sticker[] = [
  { id: 'parabens', emoji: '🎉', label: 'Parabéns!', bg: 'from-pink-100 to-amber-100' },
  { id: 'obrigado', emoji: '🙏', label: 'Obrigado!', bg: 'from-sky-100 to-indigo-100' },
  { id: 'show', emoji: '🤩', label: 'Show!', bg: 'from-amber-100 to-orange-100' },
  { id: 'bom-dia', emoji: '☀️', label: 'Bom dia!', bg: 'from-yellow-100 to-amber-100' },
  { id: 'boa-noite', emoji: '🌙', label: 'Boa noite!', bg: 'from-indigo-100 to-purple-100' },
  { id: 'cafe', emoji: '☕', label: 'Bora um café?', bg: 'from-orange-100 to-stone-200' },
  { id: 'ok', emoji: '👍', label: 'Fechado!', bg: 'from-emerald-100 to-lime-100' },
  { id: 'palmas', emoji: '👏', label: 'Palmas!', bg: 'from-rose-100 to-orange-100' },
  { id: 'risada', emoji: '😂', label: 'Kkkk', bg: 'from-yellow-100 to-lime-100' },
  { id: 'triste', emoji: '😢', label: 'Que pena…', bg: 'from-blue-100 to-slate-200' },
  { id: 'pensando', emoji: '🤔', label: 'Hmm…', bg: 'from-violet-100 to-sky-100' },
  { id: 'surpresa', emoji: '😮', label: 'Nossa!', bg: 'from-cyan-100 to-blue-100' },
  { id: 'amor', emoji: '❤️', label: 'Amei!', bg: 'from-red-100 to-pink-100' },
  { id: 'forca', emoji: '💪', label: 'Bora!', bg: 'from-teal-100 to-emerald-100' },
  { id: 'fogo', emoji: '🔥', label: 'Pegando fogo!', bg: 'from-orange-100 to-red-100' },
  { id: 'pizza', emoji: '🍕', label: 'Hora do rango', bg: 'from-amber-100 to-red-100' },
  { id: 'sexta', emoji: '🥳', label: 'Sextou!', bg: 'from-fuchsia-100 to-amber-100' },
  { id: 'dormindo', emoji: '😴', label: 'Zzz…', bg: 'from-slate-100 to-indigo-100' },
]

export const stickerById = (id: string) => STICKERS.find((s) => s.id === id)
