<?php
namespace T3\PwComments\Hooks;

// phpcs:disable

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2019 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */
use T3\PwComments\Domain\Repository\CommentRepository;
use T3\PwComments\Utility\HashEncryptionUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

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
            $repo = $objectManager->get(CommentRepository::class);
            $comment = $repo->findByCommentUid($id);

            // Build URL to eID script
            $url = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
            if (!$url) {
                throw new \UnexpectedValueException('Environment variable "TYPO3_SITE_URL" is empty!');
            }

            // Save eID access hash to registry
            $hash = HashEncryptionUtility::createHashForComment($comment);
            $url = rtrim($url, '/') . '/?eID=pw_comments_send_mail';
            $url .= '&action=sendAuthorMailWhenCommentHasBeenApproved';
            $url .= '&uid=' . (int) $comment->getUid();
            $url .= '&pid=' . (int) $comment->getPid();
            $url .= '&hash=' . $hash;

            // Call eID script
            $content = GeneralUtility::getUrl($url);
            if (!$content) {
                throw new \RuntimeException('Error while calling the following url: ' . $url);
            }

            // Add flash message
            $flashMessageService = $objectManager->get(FlashMessageService::class);
            $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $messageQueue->addMessage(new FlashMessage(LocalizationUtility::translate(
                'mailSentToAuthorAfterPublish',
                'PwComments',
                [$comment->getCommentAuthorMailAddress()]
            )));
        }
    }
}
// phpcs:enable
