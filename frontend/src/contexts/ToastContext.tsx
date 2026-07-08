import { createContext, useContext, useState, useCallback, type ReactNode } from 'react'
import { CheckCircle, XCircle, AlertCircle, X } from 'lucide-react'

// ──────────────────────────────────────────────────────────
// Types
// ──────────────────────────────────────────────────────────

type ToastType = 'success' | 'error' | 'info'

interface Toast {
  id: string
  type: ToastType
  message: string
}

interface ToastContextValue {
  toast: (message: string, type?: ToastType) => void
  success: (message: string) => void
  error: (message: string) => void
  info: (message: string) => void
}

// ──────────────────────────────────────────────────────────
// Context
// ──────────────────────────────────────────────────────────

const ToastContext = createContext<ToastContextValue | null>(null)

export function useToast(): ToastContextValue {
  const ctx = useContext(ToastContext)
  if (!ctx) throw new Error('useToast must be used inside ToastProvider')
  return ctx
}

// ──────────────────────────────────────────────────────────
// Toast item
// ──────────────────────────────────────────────────────────

function ToastItem({ toast, onDismiss }: { toast: Toast; onDismiss: (id: string) => void }) {
  const icons = {
    success: <CheckCircle className="h-4 w-4 text-green-500 flex-shrink-0" />,
    error:   <XCircle     className="h-4 w-4 text-red-500   flex-shrink-0" />,
    info:    <AlertCircle className="h-4 w-4 text-blue-500  flex-shrink-0" />,
  }

  const bg = {
    success: 'bg-white border-green-200',
    error:   'bg-white border-red-200',
    info:    'bg-white border-blue-200',
  }

  return (
    <div className={`flex items-center gap-3 rounded-xl border px-4 py-3 shadow-lg ${bg[toast.type]} min-w-[260px] max-w-xs animate-slide-in`}>
      {icons[toast.type]}
      <p className="flex-1 text-sm text-gray-800">{toast.message}</p>
      <button
        onClick={() => onDismiss(toast.id)}
        className="ml-1 text-gray-400 hover:text-gray-600 flex-shrink-0"
        aria-label="Fechar"
      >
        <X className="h-3.5 w-3.5" />
      </button>
    </div>
  )
}

// ──────────────────────────────────────────────────────────
// Provider
// ──────────────────────────────────────────────────────────

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([])

  const dismiss = useCallback((id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id))
  }, [])

  const toast = useCallback((message: string, type: ToastType = 'info') => {
    const id = `${Date.now()}-${Math.random()}`
    setToasts((prev) => [...prev.slice(-4), { id, type, message }])
    setTimeout(() => dismiss(id), 4000)
  }, [dismiss])

  const success = useCallback((m: string) => toast(m, 'success'), [toast])
  const error   = useCallback((m: string) => toast(m, 'error'),   [toast])
  const info    = useCallback((m: string) => toast(m, 'info'),    [toast])

  return (
    <ToastContext.Provider value={{ toast, success, error, info }}>
      {children}

      {/* Toast container — bottom right */}
      {toasts.length > 0 && (
        <div className="fixed bottom-20 right-4 z-[9999] flex flex-col gap-2 md:bottom-6">
          {toasts.map((t) => (
            <ToastItem key={t.id} toast={t} onDismiss={dismiss} />
          ))}
        </div>
      )}
    </ToastContext.Provider>
  )
}
