<?php
namespace T3\PwComments\Hooks;

// phpcs:disable
/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 *  |     2023 Malek Olabi <m.olabi@neusta.de>
 */

use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use T3\PwComments\Domain\Repository\CommentRepository;
use T3\PwComments\Utility\HashEncryptionUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use function htmlspecialchars;

/**
 * ProcessDatamap Hook
 *
 * @package T3\PwComments
 */
final readonly class ProcessDatamap
{
    protected string $enabledTable;
    protected string $enabledStatus;

    public function __construct(
        private CommentRepository $commentRepository,
        private ContentObjectRenderer $contentObjectRenderer,
        private FlashMessageService $flashMessageService,
        private BackendConfigurationManager $backendConfigurationManager,
    ) {
        $this->enabledTable = 'tx_pwcomments_domain_model_comment';
        $this->enabledStatus = 'update';
    }

    /**
     * After Save hook
     *
     * @param string $status
     * @param string $table
     * @param string|int $id
     * @param array $fieldArray
     * @param DataHandler $pObj
     * @return void
     */
    public function processDatamap_postProcessFieldArray(string $status, string $table, $id, array $fieldArray, DataHandler $pObj): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (
            $request === null ||
            (int)($fieldArray['hidden'] ?? 1) === 1 ||
            $table !== $this->enabledTable ||
            $status !== $this->enabledStatus
        ) {
            return;
        }

        $comment = $this->commentRepository->findByCommentUid($id);
        $settings = $this->getTypoScriptSetup($request)['plugin.']['tx_pwcomments.']['settings.'] ?? [];
        $moderateNewComments = (bool)($settings['moderateNewComments'] ?? false);
        $sendMailToAuthorAfterPublish = (bool)($settings['sendMailToAuthorAfterPublish'] ?? false);
        if ($comment === null || (!$moderateNewComments || !$sendMailToAuthorAfterPublish)) {
            return;
        }

        // Save access hash to registry
        $hash = HashEncryptionUtility::createHashForComment($comment);
        // Build URL for middleware request
        $typoLinkAdditionalParams = [
            'action' => 'sendAuthorMailWhenCommentHasBeenApproved',
            'uid' => (int) $comment->getUid(),
            'pid' => $comment->getOrigPid() ?: $comment->getPid(),
            'hash' => $hash
        ];
        $typoLinkConfiguration = [
            'parameter' => $comment->getOrigPid(),
            'additionalParams' => GeneralUtility::implodeArrayForUrl('tx_pwcomments', $typoLinkAdditionalParams),
            'forceAbsoluteUrl' => true,
        ];
        $url = $this->contentObjectRenderer->typoLink_URL($typoLinkConfiguration);
        // Call url - fetches by middleware request
        $content = GeneralUtility::getUrl($url);
        if ($content && $content === '200') {
            // Add flash message
            $messageQueue = $this->flashMessageService->getMessageQueueByIdentifier(FlashMessageQueue::NOTIFICATION_QUEUE);
            $messageToDisplay = LocalizationUtility::translate(
                'mailSentToAuthorAfterPublish',
                'PwComments',
                [$comment->getCommentAuthorMailAddress()]
            );
            $messageQueue->enqueue(new FlashMessage($messageToDisplay ?? '', '', ContextualFeedbackSeverity::OK, true));
        } else {
            throw new RuntimeException('Error while calling the following url: ' . $url, 4620589602);
        }
    }

    /**
     * Returns TypoScript Setup array from a given page id
     * Adoption of same method in BackendConfigurationManager
     *
     * @return array the raw TypoScript setup
     */
    protected function getTypoScriptSetup(ServerRequestInterface $request): array
    {
        return $this->backendConfigurationManager->getTypoScriptSetup($request);
    }
}
// phpcs:enable
