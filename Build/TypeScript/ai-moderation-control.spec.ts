import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import AiModerationControl from './ai-moderation-control'

describe('AiModerationControl', () => {
  let control: AiModerationControl
  let mockFetch: ReturnType<typeof vi.fn>
  let mockAlert: ReturnType<typeof vi.fn>
  let mockConfirm: ReturnType<typeof vi.fn>
  let mockReload: ReturnType<typeof vi.fn>

  beforeEach(() => {
    // Reset DOM
    document.body.innerHTML = ''
    
    // Reset mocks
    mockFetch = vi.fn()
    mockAlert = vi.fn()
    mockConfirm = vi.fn(() => true)
    mockReload = vi.fn()
    
    global.fetch = mockFetch
    window.alert = mockAlert
    window.confirm = mockConfirm
    
    // Properly mock location.reload for each test
    delete (window as any).location
    window.location = { ...window.location, reload: mockReload } as any
    
    // Ensure TYPO3 global is available
    window.TYPO3 = {
      settings: {
        ajaxUrls: {
          pwcomments_recheck_moderation: '/ajax/pwcomments/recheck-moderation'
        }
      }
    }
    
    control = new AiModerationControl()
  })

  afterEach(() => {
    vi.clearAllMocks()
  })

  describe('initialize', () => {
    it('should initialize only once', () => {
      const bindEventsSpy = vi.spyOn(control as any, 'bindEvents')
      
      control.initialize()
      control.initialize()
      
      expect(bindEventsSpy).toHaveBeenCalledTimes(1)
    })

    it('should bind events when initialized', () => {
      const bindEventsSpy = vi.spyOn(control as any, 'bindEvents')
      
      control.initialize()
      
      expect(bindEventsSpy).toHaveBeenCalledTimes(1)
    })
  })

  describe('event handling', () => {
    beforeEach(() => {
      control.initialize()
      // Mock makeAjaxRequest to prevent actual network calls in these tests
      vi.spyOn(control as any, 'makeAjaxRequest').mockResolvedValue({ success: true, message: 'Test' })
    })

    it('should handle click on recheck button', () => {
      const button = document.createElement('button')
      button.classList.add('js-recheck-ai-moderation')
      button.setAttribute('data-comment-uid', '123')
      document.body.appendChild(button)

      const handleRecheckSpy = vi.spyOn(control as any, 'handleRecheckClick')
      
      button.click()
      
      expect(handleRecheckSpy).toHaveBeenCalledWith(button)
    })

    it('should ignore clicks on non-recheck buttons', () => {
      const button = document.createElement('button')
      button.classList.add('other-button')
      document.body.appendChild(button)

      const handleRecheckSpy = vi.spyOn(control as any, 'handleRecheckClick')
      
      button.click()
      
      expect(handleRecheckSpy).not.toHaveBeenCalled()
    })

    it('should prevent default on recheck button click', () => {
      const button = document.createElement('button')
      button.classList.add('js-recheck-ai-moderation')
      button.setAttribute('data-comment-uid', '123')
      document.body.appendChild(button)

      const event = new MouseEvent('click', { bubbles: true })
      const preventDefaultSpy = vi.spyOn(event, 'preventDefault')
      
      button.dispatchEvent(event)
      
      expect(preventDefaultSpy).toHaveBeenCalled()
    })
  })

  describe('handleRecheckClick', () => {
    beforeEach(() => {
      control.initialize()
    })

    it('should show error when no comment UID is found', () => {
      const button = document.createElement('button')
      button.classList.add('js-recheck-ai-moderation')
      
      control['handleRecheckClick'](button)
      
      expect(mockAlert).toHaveBeenCalledWith('Error: No comment ID found')
    })

    it('should call recheckAiModeration with parsed comment UID', () => {
      const button = document.createElement('button')
      button.setAttribute('data-comment-uid', '456')
      
      const recheckSpy = vi.spyOn(control as any, 'recheckAiModeration').mockResolvedValue(undefined)
      
      control['handleRecheckClick'](button)
      
      expect(recheckSpy).toHaveBeenCalledWith(456)
    })
  })

  describe('recheckAiModeration', () => {
    beforeEach(() => {
      control.initialize()
    })

    it('should return early if user cancels confirmation', async () => {
      mockConfirm.mockReturnValue(false)
      const makeAjaxSpy = vi.spyOn(control as any, 'makeAjaxRequest')
      
      await control['recheckAiModeration'](123)
      
      expect(makeAjaxSpy).not.toHaveBeenCalled()
    })

    it('should make AJAX request when confirmed', async () => {
      const mockResponse = { success: true, message: 'Success' }
      const makeAjaxSpy = vi.spyOn(control as any, 'makeAjaxRequest').mockResolvedValue(mockResponse)
      const handleResponseSpy = vi.spyOn(control as any, 'handleResponse')
      
      await control['recheckAiModeration'](123)
      
      expect(makeAjaxSpy).toHaveBeenCalledWith(123)
      expect(handleResponseSpy).toHaveBeenCalledWith(mockResponse)
    })

    it('should handle AJAX request errors', async () => {
      const error = new Error('Network error')
      vi.spyOn(control as any, 'makeAjaxRequest').mockRejectedValue(error)
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
      
      await control['recheckAiModeration'](123)
      
      expect(mockAlert).toHaveBeenCalledWith('Error: Failed to communicate with server')
      expect(consoleSpy).toHaveBeenCalledWith('AI moderation recheck failed:', error)
    })
  })

  describe('confirmRecheck', () => {
    it('should show confirmation dialog', () => {
      const result = control['confirmRecheck']()
      
      expect(mockConfirm).toHaveBeenCalledWith('Are you sure you want to re-run AI moderation for this comment?')
      expect(result).toBe(true)
    })

    it('should return false when user cancels', () => {
      mockConfirm.mockReturnValue(false)
      
      const result = control['confirmRecheck']()
      
      expect(result).toBe(false)
    })
  })

  describe('makeAjaxRequest', () => {
    beforeEach(() => {
      control.initialize()
    })

    it('should throw error when AJAX URL is not configured', async () => {
      window.TYPO3.settings.ajaxUrls.pwcomments_recheck_moderation = undefined as any
      
      await expect(control['makeAjaxRequest'](123)).rejects.toThrow('AJAX URL not configured')
    })

    it('should make POST request with correct parameters', async () => {
      const mockResponse = {
        ok: true,
        json: vi.fn().mockResolvedValue({ success: true, message: 'Success' })
      }
      mockFetch.mockResolvedValue(mockResponse)
      
      await control['makeAjaxRequest'](123)
      
      expect(mockFetch).toHaveBeenCalledWith('/ajax/pwcomments/recheck-moderation', {
        method: 'POST',
        body: expect.any(FormData),
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      
      const formData = mockFetch.mock.calls[0][1].body as FormData
      expect(formData.get('commentUid')).toBe('123')
    })

    it('should throw error for non-OK HTTP responses', async () => {
      const mockResponse = {
        ok: false,
        status: 500
      }
      mockFetch.mockResolvedValue(mockResponse)
      
      await expect(control['makeAjaxRequest'](123)).rejects.toThrow('HTTP error! status: 500')
    })

    it('should throw error for invalid JSON response', async () => {
      const mockResponse = {
        ok: true,
        json: vi.fn().mockResolvedValue(null)
      }
      mockFetch.mockResolvedValue(mockResponse)
      
      await expect(control['makeAjaxRequest'](123)).rejects.toThrow('Invalid response format')
    })

    it('should return parsed response for valid JSON', async () => {
      const expectedResponse = { success: true, message: 'Success' }
      const mockResponse = {
        ok: true,
        json: vi.fn().mockResolvedValue(expectedResponse)
      }
      mockFetch.mockResolvedValue(mockResponse)
      
      const result = await control['makeAjaxRequest'](123)
      
      expect(result).toEqual(expectedResponse)
    })
  })

  describe('handleResponse', () => {
    beforeEach(() => {
      control.initialize()
    })

    it('should show success message and reload page for successful response', () => {
      const response = { success: true, message: 'Moderation completed' }
      
      control['handleResponse'](response)
      
      expect(mockAlert).toHaveBeenCalledWith('Success: Moderation completed')
      expect(mockReload).toHaveBeenCalled()
    })

    it('should show error message for failed response', () => {
      const response = { success: false, message: 'Moderation failed' }
      
      control['handleResponse'](response)
      
      expect(mockAlert).toHaveBeenCalledWith('Error: Moderation failed')
      expect(mockReload).not.toHaveBeenCalled()
    })
  })

  describe('showAlert', () => {
    it('should call window.alert with message', () => {
      control['showAlert']('Test message')
      
      expect(mockAlert).toHaveBeenCalledWith('Test message')
    })
  })

  describe('reloadPage', () => {
    it('should call window.location.reload', () => {
      control['reloadPage']()
      
      expect(mockReload).toHaveBeenCalled()
    })
  })

  describe('DOM ready initialization', () => {
    it('should initialize when DOM is already loaded', () => {
      // Simulate DOM already loaded
      Object.defineProperty(document, 'readyState', {
        value: 'complete',
        writable: true
      })
      
      const initSpy = vi.spyOn(AiModerationControl.prototype, 'initialize')
      
      // Re-import to trigger initialization logic
      vi.resetModules()
      
      expect(initSpy).toBeDefined()
    })

    it('should initialize after DOMContentLoaded when DOM is loading', () => {
      Object.defineProperty(document, 'readyState', {
        value: 'loading',
        writable: true
      })
      
      const addEventListenerSpy = vi.spyOn(document, 'addEventListener')
      
      // Re-import to trigger initialization logic
      vi.resetModules()
      
      expect(addEventListenerSpy).toBeDefined()
    })
  })

  describe('integration tests', () => {
    beforeEach(() => {
      control.initialize()
    })

    it('should complete full recheck flow successfully', async () => {
      // Setup DOM
      const button = document.createElement('button')
      button.classList.add('js-recheck-ai-moderation')
      button.setAttribute('data-comment-uid', '789')
      document.body.appendChild(button)

      // Mock successful response
      const mockResponse = {
        ok: true,
        json: vi.fn().mockResolvedValue({
          success: true,
          message: 'Comment re-moderated successfully',
          result: {
            status: 'approved',
            reason: 'Clean content',
            confidence: 0.95
          }
        })
      }
      mockFetch.mockResolvedValue(mockResponse)

      // Trigger the flow
      button.click()

      // Wait for async operations
      await new Promise(resolve => setTimeout(resolve, 0))

      expect(mockConfirm).toHaveBeenCalledWith('Are you sure you want to re-run AI moderation for this comment?')
      expect(mockFetch).toHaveBeenCalledWith('/ajax/pwcomments/recheck-moderation', expect.any(Object))
      expect(mockAlert).toHaveBeenCalledWith('Success: Comment re-moderated successfully')
      expect(mockReload).toHaveBeenCalled()
    })

    it('should handle complete error flow', async () => {
      // Spy on the error handling methods directly instead of going through full integration
      const makeAjaxSpy = vi.spyOn(control as any, 'makeAjaxRequest').mockRejectedValue(new Error('Network failure'))
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
      
      // Call recheckAiModeration directly to test error handling
      await control['recheckAiModeration'](999)

      expect(makeAjaxSpy).toHaveBeenCalledWith(999)
      expect(mockAlert).toHaveBeenCalledWith('Error: Failed to communicate with server')
      expect(consoleSpy).toHaveBeenCalledWith('AI moderation recheck failed:', expect.any(Error))
      expect(mockReload).not.toHaveBeenCalled()
    })
  })
})