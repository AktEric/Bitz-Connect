/**
 * Extracts a human-readable error message from an Axios-style error object.
 * Falls back to a generic Portuguese message if no API message is available.
 */
export function extractApiError(err: unknown): string {
  return (
    (err as { response?: { data?: { message?: string } } })
      ?.response?.data?.message ?? 'Ocorreu um erro inesperado.'
  )
}
