import { vi } from 'vitest'

// Mock window.TYPO3 global
Object.defineProperty(window, 'TYPO3', {
  value: {
    settings: {
      ajaxUrls: {
        pwcomments_recheck_moderation: '/ajax/pwcomments/recheck-moderation'
      }
    }
  },
  writable: true
})

// Mock window methods
Object.defineProperty(window, 'alert', {
  value: vi.fn(),
  writable: true
})

Object.defineProperty(window, 'confirm', {
  value: vi.fn(() => true),
  writable: true
})

// Location will be mocked in individual tests

// Mock fetch globally
global.fetch = vi.fn()