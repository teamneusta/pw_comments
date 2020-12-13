<?php
namespace T3\PwComments\Hooks;

// phpcs:disable

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2021 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */
use T3\PwComments\Domain\Repository\CommentRepository;
use T3\PwComments\Utility\HashEncryptionUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * ProcessDatamap Hook
 *
 * @package T3\PwComments
 */
class ProcessDatamap
{
    /** @var array */
    protected $enabledTables = ['tx_pwcomments_domain_model_comment'];

    /** @var array */
    protected $enabledStatus = ['update'];

    /**
     * After Save hook
     *
     * @param string $status
     * @param string $table
     * @param int $id
     * @param array $fieldArray
     * @param DataHandler $pObj
     * @return void
     */
    public function processDatamap_postProcessFieldArray($status, $table, $id, $fieldArray, $pObj)
    {
        if (\in_array($table, $this->enabledTables, true) &&
            \in_array($status, $this->enabledStatus, true) &&
            isset($fieldArray['hidden']) &&
            (int)$fieldArray['hidden'] === 0
        ) {
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            /** @var CommentRepository $repo */
            $repo = $objectManager->get(CommentRepository::class);
            $comment = $repo->findByCommentUid($id);

            // Get typoscript settings
            $setup = $this->getTypoScriptSetup($comment->getOrigPid());
            if (isset($setup['plugin.']['tx_pwcomments.']['settings.'])) {
                $settings = $setup['plugin.']['tx_pwcomments.']['settings.'];
            } else {
                return;
            }

            if (!$settings['moderateNewComments'] || !$settings['sendMailToAuthorAfterPublish']) {
                return;
            }

            // Save access hash to registry
            $hash = HashEncryptionUtility::createHashForComment($comment);
            // Build URL for middleware request
            $typoLinkAdditionalParams = [
                'action' => 'sendAuthorMailWhenCommentHasBeenApproved',
                'uid' => (int) $comment->getUid(),
                'pid' => (int) $comment->getOrigPid() ?: $comment->getPid(),
                'hash' => $hash
            ];
            $typoLinkConfiguration = [
                'parameter' => $comment->getOrigPid(),
                'additionalParams' => GeneralUtility::implodeArrayForUrl('tx_pwcomments', $typoLinkAdditionalParams)
            ];
            $contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $url = $contentObject->typoLink_URL($typoLinkConfiguration);
            // Call url - fetches by middleware request
            $content = GeneralUtility::getUrl($url);
            if ($content && $content === '200') {
                // Add flash message
                /** @var FlashMessageService $flashMessageService */
                $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
                $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
                $messageQueue->addMessage(new FlashMessage(LocalizationUtility::translate(
                    'mailSentToAuthorAfterPublish',
                    'PwComments',
                    [$comment->getCommentAuthorMailAddress()]
                )));
            } else {
                throw new \RuntimeException('Error while calling the following url: ' . $url);
            }
        }
    }

    /**
     * Returns TypoScript Setup array from a given page id
     * Adoption of same method in BackendConfigurationManager
     *
     * @param int|null $pageId
     * @return array the raw TypoScript setup
     */
    protected function getTypoScriptSetup($pageId): array
    {
        /** @var TemplateService $template */
        $template = GeneralUtility::makeInstance(TemplateService::class);
        // do not log time-performance information
        $template->tt_track = false;
        // Explicitly trigger processing of extension static files
        $template->setProcessExtensionStatics(true);
        // Get the root line
        $rootline = [];
        if ($pageId > 0) {
            try {
                $rootline = GeneralUtility::makeInstance(RootlineUtility::class, $pageId)->get();
            } catch (\RuntimeException $e) {
                $rootline = [];
            }
        }
        // This generates the constants/config + hierarchy info for the template.
        $template->runThroughTemplates($rootline, 0);
        $template->generateConfig();
        return $template->setup;
    }
}
// phpcs:enable
