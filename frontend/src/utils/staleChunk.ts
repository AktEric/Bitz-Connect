const KEY = 'stale-chunk-reload-at'

/** Erro típico de aba aberta numa versão antiga do site (os arquivos do build mudaram). */
export function isStaleChunkError(error: unknown): boolean {
  const msg = error instanceof Error ? error.message : String(error ?? '')
  return /dynamically imported module|Importing a module script failed|error loading dynamically imported module/i.test(msg)
}

/** Recarrega a página uma vez (no máximo a cada 30s) para buscar a versão nova. Retorna true se recarregou. */
export function reloadForNewVersion(): boolean {
  try {
    const last = Number(sessionStorage.getItem(KEY) ?? 0)
    if (Date.now() - last < 30_000) return false
    sessionStorage.setItem(KEY, String(Date.now()))
  } catch {
    // sem sessionStorage: arrisca um único reload
  }
  window.location.reload()
  return true
}
