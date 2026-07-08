import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import connectionService from '@/services/connection.service'
import { profileKeys } from '@/hooks/useProfile'
import { useToast } from '@/contexts/ToastContext'
import { notificationKeys } from '@/hooks/useNotifications'
import type { ConnectionStatus } from '@/types/api.types'
import type { User } from '@/types/auth.types'

export const connectionKeys = {
  myConnections: ['connections', 'mine'] as const,
  pending: ['connections', 'pending'] as const,
}

export function useConnectionStatus(userUuid: string): ConnectionStatus {
  const qc = useQueryClient()
  const profile = qc.getQueryData<User>(profileKeys.user(userUuid))
  return profile?.connection_status ?? null
}

/** Send a connection invite */
export function useSendConnection(userUuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: () => connectionService.send(userUuid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: profileKeys.user(userUuid) })
      qc.invalidateQueries({ queryKey: connectionKeys.myConnections })
      success('Convite de conexão enviado!')
    },
    onError: () => error('Não foi possível enviar o convite. Tente novamente.'),
  })
}

/** Remove a connection or cancel an invite */
export function useRemoveConnection(userUuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: () => connectionService.remove(userUuid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: profileKeys.user(userUuid) })
      qc.invalidateQueries({ queryKey: connectionKeys.myConnections })
      qc.invalidateQueries({ queryKey: connectionKeys.pending })
      success('Conexão removida.')
    },
    onError: () => error('Não foi possível remover a conexão.'),
  })
}

/** Accept a pending invite */
export function useAcceptConnection(userUuid: string) {
  const qc = useQueryClient()
  const { success, error } = useToast()
  return useMutation({
    mutationFn: () => connectionService.accept(userUuid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: profileKeys.user(userUuid) })
      qc.invalidateQueries({ queryKey: connectionKeys.myConnections })
      qc.invalidateQueries({ queryKey: connectionKeys.pending })
      qc.invalidateQueries({ queryKey: notificationKeys.all })
      qc.invalidateQueries({ queryKey: notificationKeys.unread })
      success('Conexão aceita! Vocês agora estão conectados.')
    },
    onError: () => error('Não foi possível aceitar o convite.'),
  })
}

/** Reject a pending invite */
export function useRejectConnection(userUuid: string) {
  const qc = useQueryClient()
  const { info, error } = useToast()
  return useMutation({
    mutationFn: () => connectionService.reject(userUuid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: profileKeys.user(userUuid) })
      qc.invalidateQueries({ queryKey: connectionKeys.pending })
      info('Convite recusado.')
    },
    onError: () => error('Não foi possível recusar o convite.'),
  })
}

/** List accepted connections for the authenticated user */
export function useMyConnections() {
  return useQuery({
    queryKey: connectionKeys.myConnections,
    queryFn: () => connectionService.getConnections().then((r) => r.data),
  })
}

/** List pending invites received by the authenticated user */
export function usePendingConnections() {
  return useQuery({
    queryKey: connectionKeys.pending,
    queryFn: () => connectionService.getPending().then((r) => r.data),
  })
}
