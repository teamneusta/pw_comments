<?php declare(strict_types=1);

namespace T3\PwComments\UserFunc\TCA;

use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Domain\Repository\CommentRepository;
use T3\PwComments\Service\Moderation\ModerationProviderFactory;
use T3\PwComments\Utility\Settings;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Log\Channel;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

#[Channel('pw_comments')]
class AiModerationControl extends AbstractNode
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly ModerationProviderFactory $moderationProviderFactory,
        private readonly PersistenceManager $persistenceManager,
    ) {
    }

    /**
     * Render AI moderation control button
     */
    public function render(): array
    {
        $result = $this->initializeResultArray();
        $row = $this->data['databaseRow'];
        $commentUid = (int)($row['uid'] ?? 0);
        
        if (!$commentUid) {
            $result['html'] = '<div class="alert alert-info">Save the record first to enable AI moderation controls.</div>';
            return $result;
        }

        $result['html'] = $this->renderControl($commentUid);
        return $result;
    }

    private function renderControl(int $commentUid): string
    {

        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $recheckIcon = $iconFactory->getIcon('actions-refresh', IconSize::SMALL);

        /** @var PageRenderer $pageRenderer */
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadJavaScriptModule('@t3/pw-comments/ai-moderation-control.js');
        $pageRenderer->addInlineLanguageLabelFile('EXT:pw_comments/Resources/Private/Language/locallang_be.xlf');

        $html = '<div class="btn-group" role="group">';
        
        // Recheck button
        $html .= sprintf(
            '<button type="button" class="btn btn-sm btn-primary js-recheck-ai-moderation" data-comment-uid="%d" title="Re-run AI moderation check">
                %s Re-check AI Moderation
            </button>',
            $commentUid,
            $recheckIcon
        );

        $html .= '</div>';

        return $html;
    }

    /**
     * AJAX endpoint for re-checking AI moderation
     */
    public function recheckModeration(): void
    {
        $commentUid = (int)($GLOBALS['TYPO3_REQUEST']->getParsedBody()['commentUid'] ?? null);
        
        if (!$commentUid) {
            $this->outputJson(['success' => false, 'message' => 'Invalid comment ID']);
            return;
        }

        try {
            $comment = $this->commentRepository->findByCommentUid($commentUid);
            
            if (!$comment instanceof Comment) {
                $this->outputJson(['success' => false, 'message' => 'Comment not found']);
                return;
            }

            // Get AI moderation settings from TYPO3 configuration
            $settings = $this->getAiModerationSettings();
            if (!$settings['enableAiModeration']) {
                $this->outputJson(['success' => false, 'message' => 'AI moderation is disabled']);
                return;
            }

            // Run AI moderation
            $moderationService = $this->moderationProviderFactory->createProvider(
                $settings['aiModerationProvider'] ?? 'openai',
                $settings
            );
            
            $moderationResult = $moderationService->moderateComment($comment);
            
            // Update comment with new moderation results
            if ($moderationResult->isViolation()) {
                $comment->setAiModerationStatus('flagged');
                $comment->setAiModerationReason($moderationResult->getReason());
                $comment->setAiModerationConfidence($moderationResult->getMaxScore());
                $comment->setHidden(true);
            } else {
                $comment->setAiModerationStatus('approved');
                $comment->setAiModerationReason('');
                $comment->setAiModerationConfidence($moderationResult->getMaxScore());
            }

            $this->commentRepository->update($comment);
            $this->persistenceManager->persistAll();

            $this->logger->info('Manual AI moderation recheck completed', [
                'comment_uid' => $commentUid,
                'result' => $moderationResult->isViolation() ? 'flagged' : 'approved',
                'confidence' => $moderationResult->getMaxScore()
            ]);

            $this->outputJson([
                'success' => true,
                'message' => 'AI moderation check completed',
                'result' => [
                    'status' => $comment->getAiModerationStatus(),
                    'reason' => $comment->getAiModerationReason(),
                    'confidence' => $comment->getAiModerationConfidence()
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Manual AI moderation recheck failed', [
                'comment_uid' => $commentUid,
                'exception' => $e
            ]);
            
            $this->outputJson([
                'success' => false,
                'message' => 'AI moderation check failed: ' . $e->getMessage()
            ]);
        }
    }

    private function getAiModerationSettings(): array
    {
        // Get TypoScript settings for AI moderation
        return Settings::getExtensionSettings();
    }

    private function outputJson(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
