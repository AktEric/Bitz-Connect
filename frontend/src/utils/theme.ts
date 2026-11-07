export type Theme = 'light' | 'dark'
const KEY = 'theme'

export function getTheme(): Theme {
  try {
    const saved = localStorage.getItem(KEY)
    if (saved === 'light' || saved === 'dark') return saved
  } catch { /* storage bloqueado */ }
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

export function applyTheme(t: Theme) {
  document.documentElement.classList.toggle('dark', t === 'dark')
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', t === 'dark' ? '#0f1117' : '#6d28d9')
}

export function setTheme(t: Theme) {
  try { localStorage.setItem(KEY, t) } catch { /* ignora */ }
  applyTheme(t)
}
