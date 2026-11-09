import type { ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import type { Capability } from '@/types/workspace.types'
import { useWs } from './ui'

/**
 * Permissões do Workspace no frontend. A lista vem do servidor (`info.me.permissions`) e é a ÚNICA fonte para
 * decidir o que aparece: menu, rotas e botões. O servidor continua sendo a barreira real.
 */
export function useCan(capability: Capability): boolean {
  const { info } = useWs()
  return info.me.permissions?.includes(capability) ?? false
}

export function useCanAny(...capabilities: Capability[]): boolean {
  const { info } = useWs()
  return capabilities.some((c) => info.me.permissions?.includes(c))
}

/** Mostra os filhos só para quem tem a capacidade (ou qualquer uma, se passar `any`). */
export function Can({ cap, any, children, fallback = null }: { cap?: Capability; any?: Capability[]; children: ReactNode; fallback?: ReactNode }) {
  const { info } = useWs()
  const has = (c: Capability) => info.me.permissions?.includes(c) ?? false
  const ok = cap ? has(cap) : (any ?? []).some(has)
  return <>{ok ? children : fallback}</>
}

/** Guarda de rota: sem a capacidade, volta ao Início em vez de mostrar uma tela que a pessoa não pode usar. */
export function RequireCan({ cap, any, children }: { cap?: Capability; any?: Capability[]; children: ReactNode }) {
  const { slug, info } = useWs()
  const has = (c: Capability) => info.me.permissions?.includes(c) ?? false
  const ok = cap ? has(cap) : (any ?? []).some(has)
  return ok ? <>{children}</> : <Navigate to={`/w/${slug}`} replace />
}
