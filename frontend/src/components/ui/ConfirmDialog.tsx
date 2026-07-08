import { useEffect, useRef } from 'react'
import { cn } from '@/utils/cn'
import { Button } from './Button'

export interface ConfirmDialogProps {
  open: boolean
  title: string
  description: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'danger' | 'warning' | 'default'
  onConfirm: () => void
  onCancel: () => void
  loading?: boolean
}

const titleId = 'confirm-dialog-title'

export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel = 'Confirmar',
  cancelLabel = 'Cancelar',
  variant = 'default',
  onConfirm,
  onCancel,
  loading = false,
}: ConfirmDialogProps) {
  const cancelRef = useRef<HTMLButtonElement>(null)

  // Auto-focus the cancel button when the dialog opens (safer default for destructive dialogs)
  useEffect(() => {
    if (open) {
      // Use a small timeout to let the element become visible first
      const id = setTimeout(() => cancelRef.current?.focus(), 0)
      return () => clearTimeout(id)
    }
  }, [open])

  // Close on Escape key press
  useEffect(() => {
    if (!open) return

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault()
        onCancel()
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [open, onCancel])

  if (!open) return null

  const confirmButtonVariant =
    variant === 'danger'
      ? 'danger'
      : variant === 'warning'
        ? 'primary' // styled via className below
        : 'primary'

  const confirmClassName =
    variant === 'warning'
      ? 'bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white shadow-sm'
      : undefined

  return (
    <>
      {/* Backdrop overlay */}
      <div
        className="fixed inset-0 z-50 bg-black/50"
        aria-hidden="true"
        onClick={onCancel}
      />

      {/* Dialog panel */}
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby={titleId}
          className={cn(
            'relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl',
            'focus:outline-none',
          )}
          onClick={(e) => e.stopPropagation()}
        >
          {/* Title */}
          <h2
            id={titleId}
            className="text-base font-semibold text-gray-900"
          >
            {title}
          </h2>

          {/* Description */}
          <p className="mt-2 text-sm text-gray-500">{description}</p>

          {/* Actions */}
          <div className="mt-6 flex justify-end gap-3">
            <Button
              ref={cancelRef}
              variant="outline"
              size="md"
              disabled={loading}
              onClick={onCancel}
            >
              {cancelLabel}
            </Button>

            <Button
              variant={confirmButtonVariant}
              size="md"
              loading={loading}
              className={confirmClassName}
              onClick={onConfirm}
            >
              {confirmLabel}
            </Button>
          </div>
        </div>
      </div>
    </>
  )
}
