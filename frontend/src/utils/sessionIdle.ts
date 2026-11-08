/**
 * Sessão por inatividade: a pessoa continua logada entre visitas (token em localStorage),
 * mas a sessão cai após IDLE_LIMIT_MS sem atividade real (mouse, teclado, toque, rolagem).
 * Requisições automáticas (polling do chat/notificações) NÃO contam como atividade.
 * O servidor aplica o mesmo limite ao token (sanctum.idle_timeout), então a regra vale
 * mesmo com o navegador fechado ou o PC desligado.
 */
export const IDLE_LIMIT_MS = 2 * 60 * 60 * 1000
const KEY = 'last_activity'
const WRITE_THROTTLE_MS = 15_000
const CHECK_EVERY_MS = 30_000
const EVENTS = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart', 'click'] as const

export function markActivity(now = Date.now()): void {
  try {
    localStorage.setItem(KEY, String(now))
  } catch {
    /* armazenamento indisponível: o servidor ainda aplica o limite */
  }
}

export function clearActivity(): void {
  try {
    localStorage.removeItem(KEY)
  } catch {
    /* noop */
  }
}

/** true quando já passou do limite desde a última atividade registrada. Sem registro = não expirada. */
export function isIdleExpired(now = Date.now()): boolean {
  try {
    const raw = localStorage.getItem(KEY)
    if (!raw) return false
    const last = Number(raw)
    return Number.isFinite(last) && now - last > IDLE_LIMIT_MS
  } catch {
    return false
  }
}

/** Observa a atividade da pessoa e chama onExpire quando ficar ociosa além do limite. Retorna a função de limpeza. */
export function watchIdle(onExpire: () => void): () => void {
  let lastWrite = 0
  const onActivity = () => {
    const now = Date.now()
    if (now - lastWrite < WRITE_THROTTLE_MS) return
    lastWrite = now
    markActivity(now)
  }
  const check = () => {
    if (isIdleExpired()) onExpire()
  }

  if (!localStorage.getItem(KEY)) markActivity()
  EVENTS.forEach((e) => window.addEventListener(e, onActivity, { passive: true }))
  document.addEventListener('visibilitychange', check)
  const timer = window.setInterval(check, CHECK_EVERY_MS)
  check()

  return () => {
    EVENTS.forEach((e) => window.removeEventListener(e, onActivity))
    document.removeEventListener('visibilitychange', check)
    window.clearInterval(timer)
  }
}
