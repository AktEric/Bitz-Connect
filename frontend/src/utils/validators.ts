/**
 * Returns true if the string is non-empty after trimming whitespace.
 * Requirements: 4.1 (post min 1 char)
 */
export function isNonEmpty(value: string): boolean {
  return value.trim().length > 0
}

/**
 * Returns true if the file is a valid image type and within size limit.
 * Requirements: 2.4, 3.5
 */
export function isValidImage(file: File, maxMb = 5): boolean {
  const allowed = ['image/jpeg', 'image/png', 'image/webp']
  const maxBytes = maxMb * 1024 * 1024
  return allowed.includes(file.type) && file.size <= maxBytes
}
