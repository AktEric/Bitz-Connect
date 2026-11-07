const STORAGE_KEY = 'punch_device_key'

/**
 * Chave aleatória deste navegador/aparelho, guardada localmente. Identifica o aparelho para o cadastro de dispositivos
 * do ponto (o servidor só guarda o hash). Se o navegador limpar os dados do site, o aparelho precisa ser cadastrado de novo.
 */
export function getDeviceKey(): string {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved && /^[A-Za-z0-9_-]{16,128}$/.test(saved)) return saved
    const bytes = new Uint8Array(24)
    crypto.getRandomValues(bytes)
    const key = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')
    localStorage.setItem(STORAGE_KEY, key)
    return key
  } catch {
    // sem armazenamento (modo privado restrito): chave só desta sessão
    return (window as unknown as { __deviceKey?: string }).__deviceKey ??= Array.from(crypto.getRandomValues(new Uint8Array(24)), (b) => b.toString(16).padStart(2, '0')).join('')
  }
}
