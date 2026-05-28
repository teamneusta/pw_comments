<?php

declare(strict_types=1);

namespace T3\PwComments\UserFunc\TCA;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Domain\Repository\CommentRepository;
use T3\PwComments\Service\Moderation\ModerationProviderFactory;
use T3\PwComments\Utility\Settings;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Log\Channel;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

#[Channel('pw_comments')]
class AiModerationControl extends AbstractNode implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly ModerationProviderFactory $moderationProviderFactory,
        private readonly PersistenceManager $persistenceManager,
        private readonly IconFactory $iconFactory,
        private readonly PageRenderer $pageRenderer,
    ) {}

    /**
     * Render AI moderation control button
     */
    public function render(): array
    {
        $result = $this->initializeResultArray();
        $row = $this->data['databaseRow'];
        $commentUid = (int) ($row['uid'] ?? 0);

        if (!$commentUid) {
            $result['html'] = '<div class="alert alert-info">Save the record first to enable AI moderation controls.</div>';
            return $result;
        }

        $result['html'] = $this->renderControl($commentUid);
        return $result;
    }

    private function renderControl(int $commentUid): string
    {
        $recheckIcon = $this->iconFactory->getIcon('actions-refresh', IconSize::SMALL);
        $this->pageRenderer->loadJavaScriptModule('@t3/pw-comments/ai-moderation-control.js');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:pw_comments/Resources/Private/Language/locallang_be.xlf');

        $html = '<div class="btn-group" role="group">';

        // Recheck button
        $html .= sprintf(
            '<button type="button" class="btn btn-sm btn-primary js-recheck-ai-moderation" data-comment-uid="%d" title="Re-run AI moderation check">
                %s Re-check AI Moderation
            </button>',
            $commentUid,
            $recheckIcon,
        );

        return $html . '</div>';
    }

    /**
     * AJAX endpoint for re-checking AI moderation
     */
    public function recheckModeration(ServerRequestInterface $request): ResponseInterface
    {
        $commentUid = (int) ($request->getParsedBody()['commentUid'] ?? null);

        if (!$commentUid) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid comment ID']);
        }

        try {
            $comment = $this->commentRepository->findByCommentUid($commentUid);

            if (!$comment instanceof Comment) {
                return new JsonResponse(['success' => false, 'message' => 'Comment not found']);
            }

            $settings = $this->getAiModerationSettings();
            if (!$settings['enableAiModeration']) {
                return new JsonResponse(['success' => false, 'message' => 'AI moderation is disabled']);
            }

            $moderationService = $this->moderationProviderFactory->createProvider(
                $settings['aiModerationProvider'] ?? 'openai',
                $settings,
            );

            $moderationResult = $moderationService->moderateComment($comment);

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
                'confidence' => $moderationResult->getMaxScore(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'AI moderation check completed',
                'result' => [
                    'status' => $comment->getAiModerationStatus(),
                    'reason' => $comment->getAiModerationReason(),
                    'confidence' => $comment->getAiModerationConfidence(),
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Manual AI moderation recheck failed', [
                'comment_uid' => $commentUid,
                'exception' => $e,
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'AI moderation check failed: ' . $e->getMessage(),
            ]);
        }
    }

    protected function getAiModerationSettings(): array
    {
        return Settings::getExtensionSettings();
    }
}
