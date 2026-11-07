import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { TimeType } from '@/types/workspace.types'
import workspaceService from '@/services/workspace.service'

export const wsKeys = {
  mine: ['workspaces'] as const,
  info: (slug: string) => ['workspace', slug] as const,
}

export function useMyWorkspaces() {
  return useQuery({ queryKey: wsKeys.mine, queryFn: workspaceService.mine, staleTime: 60_000 })
}

export function useWorkspaceInfo(slug: string) {
  return useQuery({
    queryKey: wsKeys.info(slug),
    queryFn: () => workspaceService.info(slug),
    enabled: !!slug,
    staleTime: 30_000,
    refetchInterval: 60_000,
  })
}


export function useTimeToday(slug: string) {
  return useQuery({
    queryKey: ['ws', slug, 'time', 'today'],
    queryFn: () => workspaceService.timeToday(slug),
    enabled: !!slug,
    refetchInterval: 60_000,
  })
}

export function usePunch(slug: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (opts: { type?: TimeType; geo?: { lat: number; lng: number; accuracy: number } | null; photo?: Blob | null; deviceName?: string }) => workspaceService.punch(slug, opts),
    onSuccess: (data) => {
      qc.setQueryData(['ws', slug, 'time', 'today'], data)
      qc.invalidateQueries({ queryKey: ['ws', slug, 'time', 'timesheet'] })
      qc.invalidateQueries({ queryKey: ['ws', slug, 'time', 'team'] })
    },
  })
}

export function useTimesheet(slug: string, month: string, user?: string) {
  return useQuery({
    queryKey: ['ws', slug, 'time', 'timesheet', month, user ?? 'me'],
    queryFn: () => workspaceService.timesheet(slug, { month, user }),
    enabled: !!slug,
  })
}

export function useTimeTeam(slug: string, enabled: boolean) {
  return useQuery({
    queryKey: ['ws', slug, 'time', 'team'],
    queryFn: () => workspaceService.timeTeam(slug),
    enabled: !!slug && enabled,
    refetchInterval: 60_000,
  })
}

export function useTimeBank(slug: string, user?: string) {
  return useQuery({
    queryKey: ['ws', slug, 'time', 'bank', user ?? 'me'],
    queryFn: () => workspaceService.timeBank(slug, user),
    enabled: !!slug,
  })
}

export function useTimeBankTeam(slug: string, enabled: boolean) {
  return useQuery({
    queryKey: ['ws', slug, 'time', 'bank', 'team'],
    queryFn: () => workspaceService.timeBankTeam(slug),
    enabled: !!slug && enabled,
  })
}
