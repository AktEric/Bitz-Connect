import { describe, it, expect } from 'vitest'
import fc from 'fast-check'
import { isNonEmpty, isValidImage } from './validators'

describe('isNonEmpty', () => {
  it('returns false for strings that are entirely whitespace', () => {
    // Property: any string made up purely of whitespace chars is not non-empty
    fc.assert(
      fc.property(
        fc.stringMatching(/^\s*$/),
        (ws) => {
          expect(isNonEmpty(ws)).toBe(false)
        }
      )
    )
  })

  it('returns true for strings with at least one non-whitespace character', () => {
    fc.assert(
      fc.property(
        // at least one visible ASCII char followed by arbitrary string
        fc.string({ minLength: 1 }).filter((s) => s.trim().length > 0),
        (s) => {
          expect(isNonEmpty(s)).toBe(true)
        }
      )
    )
  })
})

describe('isValidImage', () => {
  it('rejects files over 5 MB', () => {
    const bigFile = new File(['x'.repeat(6 * 1024 * 1024)], 'big.jpg', { type: 'image/jpeg' })
    expect(isValidImage(bigFile)).toBe(false)
  })

  it('rejects unsupported mime types', () => {
    const gifFile = new File(['data'], 'anim.gif', { type: 'image/gif' })
    expect(isValidImage(gifFile)).toBe(false)
  })

  it('accepts valid image types within size limit', () => {
    const types = ['image/jpeg', 'image/png', 'image/webp']
    for (const type of types) {
      const file = new File(['data'], 'img', { type })
      expect(isValidImage(file)).toBe(true)
    }
  })
})
