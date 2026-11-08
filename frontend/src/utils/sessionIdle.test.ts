// @vitest-environment jsdom
import { describe, it, expect, beforeEach } from 'vitest'
import { IDLE_LIMIT_MS, markActivity, clearActivity, isIdleExpired } from './sessionIdle'

describe('sessionIdle', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('não expira sem registro de atividade', () => {
    expect(isIdleExpired()).toBe(false)
  })

  it('não expira dentro das 2 horas', () => {
    const t0 = 1_000_000
    markActivity(t0)
    expect(isIdleExpired(t0 + IDLE_LIMIT_MS - 1)).toBe(false)
  })

  it('expira depois de 2 horas sem atividade', () => {
    const t0 = 1_000_000
    markActivity(t0)
    expect(isIdleExpired(t0 + IDLE_LIMIT_MS + 1)).toBe(true)
  })

  it('nova atividade renova o prazo', () => {
    const t0 = 1_000_000
    markActivity(t0)
    markActivity(t0 + IDLE_LIMIT_MS - 1000)
    expect(isIdleExpired(t0 + IDLE_LIMIT_MS + 1)).toBe(false)
  })

  it('clearActivity remove o registro', () => {
    markActivity(1)
    clearActivity()
    expect(isIdleExpired(Number.MAX_SAFE_INTEGER)).toBe(false)
  })
})
