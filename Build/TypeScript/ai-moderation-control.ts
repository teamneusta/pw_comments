/**
 * AI Moderation Control Module for TYPO3 Backend
 * 
 * Handles AI moderation recheck functionality for pw_comments extension
 */

declare global {
  interface Window {
    TYPO3: {
      settings: {
        ajaxUrls: {
          pwcomments_recheck_moderation: string;
        };
      };
    };
  }
}

interface ModerationResponse {
  success: boolean;
  message: string;
  result?: {
    status: string;
    reason: string;
    confidence: number;
  };
}

class AiModerationControl {
  private initialized = false;

  /**
   * Initialize the AI moderation control functionality
   */
  public initialize(): void {
    if (this.initialized) {
      return;
    }

    this.bindEvents();
    this.initialized = true;
  }

  /**
   * Bind click events to recheck buttons
   */
  private bindEvents(): void {
    document.addEventListener('click', (event: Event) => {
      const target = event.target as HTMLElement;
      
      if (target.classList.contains('js-recheck-ai-moderation')) {
        event.preventDefault();
        this.handleRecheckClick(target);
      }
    });
  }

  /**
   * Handle click on recheck button
   */
  private handleRecheckClick(button: HTMLElement): void {
    const commentUid = button.getAttribute('data-comment-uid');
    
    if (!commentUid) {
      this.showAlert('Error: No comment ID found');
      return;
    }

    this.recheckAiModeration(parseInt(commentUid, 10));
  }

  /**
   * Perform AI moderation recheck for a comment
   */
  private async recheckAiModeration(commentUid: number): Promise<void> {
    if (!this.confirmRecheck()) {
      return;
    }

    try {
      const response = await this.makeAjaxRequest(commentUid);
      this.handleResponse(response);
    } catch (error) {
      this.showAlert('Error: Failed to communicate with server');
      console.error('AI moderation recheck failed:', error);
    }
  }

  /**
   * Show confirmation dialog
   */
  private confirmRecheck(): boolean {
    return confirm('Are you sure you want to re-run AI moderation for this comment?');
  }

  /**
   * Make AJAX request to recheck moderation
   */
  private async makeAjaxRequest(commentUid: number): Promise<ModerationResponse> {
    const ajaxUrl = window.TYPO3?.settings?.ajaxUrls?.pwcomments_recheck_moderation;
    
    if (!ajaxUrl) {
      throw new Error('AJAX URL not configured');
    }

    const formData = new FormData();
    formData.append('commentUid', commentUid.toString());

    const response = await fetch(ajaxUrl, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();
    
    if (typeof data !== 'object' || data === null) {
      throw new Error('Invalid response format');
    }

    return data as ModerationResponse;
  }

  /**
   * Handle the response from the server
   */
  private handleResponse(response: ModerationResponse): void {
    if (response.success) {
      this.showAlert(`Success: ${response.message}`);
      this.reloadPage();
    } else {
      this.showAlert(`Error: ${response.message}`);
    }
  }

  /**
   * Show alert message
   */
  private showAlert(message: string): void {
    alert(message);
  }

  /**
   * Reload the current page
   */
  private reloadPage(): void {
    window.location.reload();
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    const control = new AiModerationControl();
    control.initialize();
  });
} else {
  const control = new AiModerationControl();
  control.initialize();
}

// Export for potential external usage
export default AiModerationControl;