import { describe, it, expect } from 'vitest'
import fc from 'fast-check'
import type { ApiResponse, ApiErrorResponse, PaginatedResponse } from './api.types'

/**
 * Tests for the global API response type contracts.
 * Requirements: 8.1, 8.2, 8.3
 */

describe('ApiResponse shape', () => {
  it('success response must have success=true, message, and data', () => {
    // Property: for any valid data payload, an ApiResponse wrapping it must
    // carry success=true, a string message, and the data unchanged.
    fc.assert(
      fc.property(fc.string(), fc.anything(), (message, payload) => {
        const response: ApiResponse<typeof payload> = {
          success: true,
          message,
          data: payload,
        }
        expect(response.success).toBe(true)
        expect(typeof response.message).toBe('string')
        expect(response.data).toBe(payload)
      })
    )
  })

  it('error response must have success=false, message, and errors', () => {
    fc.assert(
      fc.property(fc.string(), (message) => {
        const response: ApiErrorResponse = {
          success: false,
          message,
          errors: [],
        }
        expect(response.success).toBe(false)
        expect(typeof response.message).toBe('string')
        expect(Array.isArray(response.errors)).toBe(true)
      })
    )
  })
})

describe('PaginatedResponse shape', () => {
  it('must carry meta with pagination fields', () => {
    fc.assert(
      fc.property(
        fc.array(fc.string()),
        fc.integer({ min: 1, max: 100 }),
        fc.integer({ min: 1, max: 1000 }),
        (items, perPage, total) => {
          const lastPage = Math.ceil(total / perPage)
          const response: PaginatedResponse<string> = {
            success: true,
            message: 'OK',
            data: items,
            meta: {
              current_page: 1,
              last_page: lastPage,
              per_page: perPage,
              total,
            },
            links: { first: null, last: null, prev: null, next: null },
          }
          expect(response.meta.per_page).toBe(perPage)
          expect(response.meta.total).toBe(total)
          expect(response.meta.last_page).toBe(lastPage)
          expect(Array.isArray(response.data)).toBe(true)
        }
      )
    )
  })
})
