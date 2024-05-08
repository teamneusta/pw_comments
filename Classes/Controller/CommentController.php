<?php
namespace T3\PwComments\Controller;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 *  |     2023 Malek Olabi <m.olabi@neusta.de>
 */
use T3\PwComments\Domain\Validator\CommentValidator;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use RuntimeException;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Domain\Model\FrontendUser;
use T3\PwComments\Domain\Model\Vote;
use T3\PwComments\Domain\Repository\CommentRepository;
use T3\PwComments\Domain\Repository\FrontendUserRepository;
use T3\PwComments\Domain\Repository\VoteRepository;
use T3\PwComments\Utility\Cookie;
use T3\PwComments\Utility\HashEncryptionUtility;
use T3\PwComments\Utility\Mail;
use T3\PwComments\Utility\Settings;
use T3\PwComments\Utility\StringUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * The comment controller
 *
 * @package T3\PwComments
 */
class CommentController extends ActionController
{
    /**
     * @var int
     */
    protected $pageUid = 0;

    /**
     * @var int
     */
    protected $entryUid = 0;

    /**
     * @var array
     */
    protected $currentUser = [];

    /**
     * @var string|null
     */
    protected $currentAuthorIdent;

    /**
     * @var Mail
     */
    protected $mailUtility;

    /**
     * @var Cookie
     */
    protected $cookieUtility;

    /**
     * @var CommentRepository
     */
    protected $commentRepository;

    /**
     * @var FrontendUserRepository
     */
    protected $frontendUserRepository;

    /**
     * @var VoteRepository
     */
    protected $voteRepository;

    /**
     * @var int
     */
    protected $commentStorageUid;

    public function injectMailUtility(Mail $mailUtility): void
    {
        $this->mailUtility = $mailUtility;
    }

    public function injectCookieUtility(Cookie $cookieUtility): void
    {
        $this->cookieUtility = $cookieUtility;
    }

    public function injectCommentRepository(CommentRepository $commentRepository): void
    {
        $this->commentRepository = $commentRepository;
    }

    public function injectFrontendUserRepository(FrontendUserRepository $frontendUserRepository): void
    {
        $this->frontendUserRepository = $frontendUserRepository;
    }

    public function injectVoteRepository(VoteRepository $voteRepository): void
    {
        $this->voteRepository = $voteRepository;
    }

    /**
     * Initialize action, which will be executed before every
     * other action in this controller
     *
     * @return void
     */
    public function initializeAction(): void
    {
        if (!is_array($this->settings)) {
            throw new RuntimeException(
                'It seems no pw_comments configuration has been added to TypoScript Template (Include Static)!',
                1501862644
            );
        }
        $this->settings = Settings::renderConfigurationArray(
            settings: $this->settings,
            request: $this->request,
            makeSettingsRenderable: !isset($this->settings['_skipMakingSettingsRenderable']) || !$this->settings['_skipMakingSettingsRenderable']
        );
        if ($this->mailUtility === null) {
            $this->mailUtility = GeneralUtility::makeInstance(Mail::class);
        }
        $this->mailUtility->setSettings($this->settings);
        $this->pageUid = $GLOBALS['TSFE']->id;
        $this->commentStorageUid = is_numeric($this->settings['storagePid'] ?? null)
            ? $this->settings['storagePid']
            : $this->pageUid;
        $this->currentUser = isset($GLOBALS['TSFE']->fe_user->user['uid']) ? $GLOBALS['TSFE']->fe_user->user : [];
        $this->currentAuthorIdent = isset($this->currentUser['uid'])
            ? $this->currentUser['uid']
            : $this->cookieUtility->get('ahash');

        if (is_numeric($this->currentAuthorIdent) && !isset($this->currentUser['uid'])) {
            $this->currentAuthorIdent = null;
        }

        if (isset($this->settings['useEntryUid']) && $this->settings['useEntryUid']) {
            $this->entryUid = (int)$this->settings['entryUid'];
        }
    }

    /**
     * Displays all comments by pid
     *
     * @return void
     */
    #[IgnoreValidation(['value' => 'commentToReplyTo'])]
    public function indexAction(Comment $commentToReplyTo = null): ResponseInterface
    {
        if (isset($this->settings['invertCommentSorting']) && $this->settings['invertCommentSorting']) {
            $this->commentRepository->setInvertCommentSorting(true);
        }
        if (isset($this->settings['invertReplySorting']) && $this->settings['invertReplySorting']) {
            $this->commentRepository->setInvertReplySorting(true);
        }

        if ($this->entryUid > 0) {
            /* @var $comments QueryResult */
            $comments = $this->commentRepository->findByPidAndEntryUid($this->commentStorageUid, $this->entryUid);
        } else {
            /* @var $comments QueryResult */
            $comments = $this->commentRepository->findByPid($this->commentStorageUid);
        }

        $this->handleCustomMessages();

        $upvotedCommentUids = [];
        $downvotedCommentUids = [];
        if ($this->currentAuthorIdent !== null) {
            $votes = $this->voteRepository->findByPidAndAuthorIdent(
                $this->commentStorageUid,
                $this->currentAuthorIdent
            );
            /** @var Vote $vote */
            foreach ($votes as $vote) {
                if ($vote->getComment()) {
                    if ($vote->isDownvote()) {
                        $downvotedCommentUids[] = $vote->getComment()->getUid();
                    } else {
                        $upvotedCommentUids[] = $vote->getComment()->getUid();
                    }
                }
            }
        }
        $this->view->assign('upvotedCommentUids', $upvotedCommentUids);
        $this->view->assign('downvotedCommentUids', $downvotedCommentUids);

        $this->view->assign('comments', $comments);
        $this->view->assign('commentCount', $this->calculateCommentCount($comments));
        $this->view->assign('commentToReplyTo', $commentToReplyTo);

        return $this->htmlResponse();
    }

    /**
     * Create action
     */
    #[Validate(['validator' => CommentValidator::class, 'param' => 'newComment'])]
    public function createAction(Comment $newComment = null): ResponseInterface
    {
        // Hidden field Spam-Protection
        if (isset($this->settings['hiddenFieldSpamProtection']) && $this->settings['hiddenFieldSpamProtection']
            && $this->request->hasArgument($this->settings['hiddenFieldName'])
            && $this->request->getArgument($this->settings['hiddenFieldName'])) {
            return $this->redirectToUri($this->buildUriByUid($this->pageUid) . '#' . $this->settings['writeCommentAnchor']);
        }
        if ($newComment === null) {
            return $this->redirectToUri($this->buildUriByUid($this->pageUid));
        }
        $this->createAuthorIdent();

        $newComment->setMessage(
            StringUtility::prepareCommentMessage($newComment->getMessage(), $this->settings['linkUrlsInComments'])
        );
        $newComment->setPid($this->commentStorageUid);
        $newComment->setOrigPid($this->pageUid);
        $newComment->setEntryUid($this->entryUid);
        $newComment->setAuthorIdent($this->currentAuthorIdent);

        $author = null;
        if (isset($this->currentUser['uid'])) {
            $author = $this->frontendUserRepository->findByUid($this->currentUser['uid']);
        }
        if ($author !== null) {
            $newComment->setAuthor($author);
        } else {
            $newComment->setAuthor(null);
            $GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_pwcomments_unregistredUserName', $newComment->getAuthorName());
            $GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_pwcomments_unregistredUserMail', $newComment->getAuthorMail());
        }
        $GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_pwcomments_lastComment', time());

        $translateArguments = [
            'name' => $newComment->getAuthorName(),
            'email' => $newComment->getCommentAuthorMailAddress(),
            'message' => $newComment->getMessage(),
        ];

            // Modify comment if moderation is active
        if (isset($this->settings['moderateNewComments']) && $this->settings['moderateNewComments']) {
            $newComment->setHidden(true);
            $this->addFlashMessage(
                LocalizationUtility::translate('tx_pwcomments.moderationNotice', 'PwComments', $translateArguments)
            );
        } else {
            $this->addFlashMessage(
                LocalizationUtility::translate('tx_pwcomments.thanks', 'PwComments', $translateArguments)
            );
        }

        $this->commentRepository->add($newComment);
        $this->commentRepository->persistAll();

        if (isset($this->settings['sendMailOnNewCommentsTo']) && $this->settings['sendMailOnNewCommentsTo']) {
            $this->mailUtility->setFluidTemplate($this->makeFluidTemplateObject());
            $this->mailUtility->setReceivers($this->settings['sendMailOnNewCommentsTo']);
            $this->mailUtility->setTemplatePath($this->settings['sendMailTemplate']);
            $this->mailUtility->sendMail($newComment, HashEncryptionUtility::createHashForComment($newComment));
        }

        if (isset($this->settings['sendMailToAuthorAfterSubmit']) &&
            $this->settings['sendMailToAuthorAfterSubmit'] &&
            $newComment->hasCommentAuthorMailAddress()
        ) {
            $this->mailUtility->setFluidTemplate($this->makeFluidTemplateObject());
            $this->mailUtility->setReceivers($newComment->getCommentAuthorMailAddress());
            $this->mailUtility->setTemplatePath($this->settings['sendMailToAuthorAfterSubmitTemplate']);
            $this->mailUtility->sendMail($newComment);
        }

        if (isset($this->settings['moderateNewComments']) && $this->settings['moderateNewComments']) {
            $anchor = '#' . $this->settings['successfulAnchor'];
        } else {
            $anchor = '#' . $this->settings['commentAnchorPrefix'] . $newComment->getUid();
        }

        return $this->redirectToUri($this->buildUriByUid($this->pageUid, true) . $anchor);
    }

    /**
     * New action
     *
     * @param Comment $newComment New Comment
     * @param Comment $commentToReplyTo Comment to reply to
     * @return void
     */
    #[IgnoreValidation(['value' => 'newComment'])]
    #[IgnoreValidation(['value' => 'commentToReplyTo'])]
    public function newAction(Comment $newComment = null, Comment $commentToReplyTo = null): ResponseInterface
    {
        if ($newComment !== null) {
            $this->view->assign('newComment', $newComment);
        }
        $this->view->assign('commentToReplyTo', $commentToReplyTo);

        // Get name of unregistred user
        if ($newComment !== null && $newComment->getAuthorName()) {
            $unregistredUserName = $newComment->getAuthorName();
        } else {
            $unregistredUserName = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_pwcomments_unregistredUserName');
        }

        // Get mail of unregistred user
        if ($newComment !== null && $newComment->getAuthorMail()) {
            $unregistredUserMail = $newComment->getAuthorMail();
        } else {
            $unregistredUserMail = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_pwcomments_unregistredUserMail');
        }

        $this->view->assign('unregistredUserName', $unregistredUserName);
        $this->view->assign('unregistredUserMail', $unregistredUserMail);

        return $this->htmlResponse();
    }

    /**
     * Upvote action
     *
     * @return string Empty string. This action will perform a redirect
     */
    #[IgnoreValidation(['value' => 'comment'])]
    public function upvoteAction(Comment $comment): ResponseInterface
    {
        return $this->performVoting($comment, Vote::TYPE_UPVOTE);
    }

    /**
     * Downvote action
     *
     * @return string Empty string. This action will perform a redirect
     */
    #[IgnoreValidation(['value' => 'comment'])]
    public function downvoteAction(Comment $comment): ResponseInterface
    {
        return $this->performVoting($comment, Vote::TYPE_DOWNVOTE);
    }

    /**
     * Confirm action to confirm a comment request
     *
     * @param int $comment uid of the comment
     * @param string $hash hash to confirm
     */
    public function confirmCommentAction($comment, $hash): ResponseInterface
    {
        $resolvedComment = $this->commentRepository->findByCommentUid($comment);
        if (!$resolvedComment || !HashEncryptionUtility::validCommentHash($hash, $resolvedComment) || !$resolvedComment->getHidden()) {
            $this->addFlashMessage(
                LocalizationUtility::translate('noCommentAvailable', 'PwComments'),
                '',
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirectToUri($this->buildUriByUid($this->pageUid, true));
        }

        $resolvedComment->setHidden(false);
        $this->commentRepository->update($resolvedComment);
        $this->commentRepository->persistAll();

        if (isset($this->settings['moderateNewComments']) && $this->settings['moderateNewComments'] &&
            isset($this->settings['sendMailToAuthorAfterPublish']) && $this->settings['sendMailToAuthorAfterPublish']
        ) {
            $this->mailUtility->setFluidTemplate($this->makeFluidTemplateObject());
            $this->mailUtility->setReceivers($resolvedComment->getAuthorMail());
            $this->mailUtility->setTemplatePath($this->settings['sendMailToAuthorAfterPublishTemplate']);
            $this->mailUtility->setSubjectLocallangKey('tx_pwcomments.mailToAuthorAfterPublish.subject');
            $this->mailUtility->setAddQueryStringToLinks(false);
            $this->mailUtility->sendMail($resolvedComment);
            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'mailSentToAuthorAfterPublish',
                    'PwComments',
                    [$resolvedComment->getAuthorMail()]
                )
            );
        }

        $this->addFlashMessage(LocalizationUtility::translate('commentPublished', 'PwComments'));

        return $this->redirectToUri($this->buildUriByUid($this->pageUid, true));
    }

    /**
     * Perform database operations for voting
     *
     * @param Comment $comment
     * @param int $type Check out Tx_PwComments_Domain_Model_Vote constants
     *
     */
    /**
     * Perform database operations for voting
     *
     * @param int $type Check out Tx_PwComments_Domain_Model_Vote constants
     * @return ResponseInterface
     */
    protected function performVoting(Comment $comment, $type): ResponseInterface
    {
        $commentAnchor = '#' . $this->settings['commentAnchorPrefix'] . $comment->getUid();
        if (!$this->settings['enableVoting']) {
            return new ForwardResponse('index');
        }

        $this->createAuthorIdent();

        $vote = null;
        if ($this->currentAuthorIdent !== null) {
            if (isset($this->settings['ignoreVotingForOwnComments']) && $this->settings['ignoreVotingForOwnComments'] &&
                $this->currentAuthorIdent === $comment->getAuthorIdent()
            ) {
                // TODO: use flash messages here?
                return $this->redirectToUri(
                    $this->buildUriByUid($this->pageUid, true, ['doNotVoteForYourself' => 1]) . $commentAnchor
                );
            }
            $vote = $this->voteRepository->findOneByCommentAndAuthorIdent($comment, $this->currentAuthorIdent);
        }

        if ($vote === null) {
            $comment->addVote($this->createNewVote($type, $comment));
            $this->commentRepository->update($comment);
        } else {
            $comment->removeVote($vote);
            $this->commentRepository->update($comment);
            $this->voteRepository->remove($vote);
            if ($type !== $vote->getType()) {
                $this->commentRepository->persistAll();
                $this->performVoting($comment, $type);
            }
        }

        $this->commentRepository->persistAll();

        return new ForwardResponse('index');
    }


    /**
     * Creates new vote instance
     *
     * @param int $type See Tx_PwComments_Domain_Model_Vote constants
     * @return Vote
     */
    protected function createNewVote($type, Comment $comment)
    {
        /** @var Vote $newVote */
        $newVote = GeneralUtility::makeInstance(Vote::class);
        $newVote->setComment($comment);
        $newVote->setPid($this->commentStorageUid);
        $newVote->setOrigPid($this->pageUid);
        $newVote->setAuthorIdent($this->currentAuthorIdent);
        if (isset($this->currentUser['uid']) && $this->currentUser['uid']) {
            /** @var FrontendUser $author */
            $author = $this->frontendUserRepository->findByUid($this->currentUser['uid']);
            $newVote->setAuthor($author);
        }
        $newVote->setType($type);

        return $newVote;
    }

    /**
     * Creates a unique string for author identification
     *
     * @return void
     */
    protected function createAuthorIdent()
    {
        if ($this->currentAuthorIdent === null) {
            $this->currentAuthorIdent = uniqid(more_entropy: true) . uniqid(more_entropy: true);
            $this->cookieUtility->set('ahash', $this->currentAuthorIdent);
        }
    }

    /**
     * Sends mail to comment author whe comment has been approved (and published)
     *
     * @return ResponseInterface
     */
    public function sendAuthorMailWhenCommentHasBeenApprovedAction(): ResponseInterface
    {
        /** @var Comment $comment */
        $comment = $this->commentRepository->findByCommentUid($this->settings['_commentUid']);

        if (isset($this->settings['moderateNewComments']) && $this->settings['moderateNewComments'] &&
            isset($this->settings['sendMailToAuthorAfterPublish']) && $this->settings['sendMailToAuthorAfterPublish']
        ) {
            $this->mailUtility->setFluidTemplate($this->makeFluidTemplateObject());
            $this->mailUtility->setReceivers($comment->getCommentAuthorMailAddress());
            $this->mailUtility->setTemplatePath($this->settings['sendMailToAuthorAfterPublishTemplate']);
            $this->mailUtility->setSubjectLocallangKey('tx_pwcomments.mailToAuthorAfterPublish.subject');
            $this->mailUtility->setAddQueryStringToLinks(false);
            $this->mailUtility->sendMail($comment);
            $this->addFlashMessage(
                LocalizationUtility::translate(
                    'mailSentToAuthorAfterPublish',
                    'PwComments',
                    [$comment->getCommentAuthorMailAddress()]
                )
            );
        }

        return $this->htmlResponse('');
    }

    /**
     * Returns a built URI by pageUid
     *
     * @param int $uid The uid to use for building link
     * @param bool $excludeCommentRelatedParameter If TRUE the comment to reply to will be
     *             removed from query string
     * @return string The link
     */
    private function buildUriByUid(
        $uid,
        $excludeCommentRelatedParameter = false,
        array $arguments = []
    ): string {
        $excludeFromQueryString = [
            'tx_pwcomments_pi1[action]',
            'tx_pwcomments_pi1[controller]',
            'tx_pwcomments_pi1[hash]',
            'cHash'
        ];

        if ($excludeCommentRelatedParameter === true) {
            $excludeFromQueryString[] = 'tx_pwcomments_pi1[comment]';
            $excludeFromQueryString[] = 'tx_pwcomments_pi1[commentToReplyTo]';
        }

        $uri = $this->uriBuilder
                ->reset()
                ->setTargetPageUid($uid)
                ->setAddQueryString(true)
                ->setArgumentsToBeExcludedFromQueryString($excludeFromQueryString)
                ->setArguments($arguments)
                ->build();

        return $this->addBaseUriIfNecessary($uri);
    }

    protected function makeFluidTemplateObject(): StandaloneView
    {
        $fluidTemplate = GeneralUtility::makeInstance(StandaloneView::class);
        $fluidTemplate->setRequest($this->request);
        $fluidTemplate->setPartialRootPaths($this->view->getPartialRootPaths());

        return $fluidTemplate;
    }

    /**
     * Returns count of comments and/or comments and replies.
     *
     * @return int
     */
    protected function calculateCommentCount(QueryResult $comments): int
    {
        $replyAmount = 0;
        if (isset($this->settings['countReplies']) && $this->settings['countReplies']) {
            /** @var Comment $comment */
            foreach ($comments as $comment) {
                $replyAmount += count($comment->getReplies());
            }
        }

        return count($comments) + $replyAmount;
    }

    protected function handleCustomMessages(): void
    {
        if (isset($this->settings['ignoreVotingForOwnComments']) && $this->settings['ignoreVotingForOwnComments'] && GeneralUtility::_GP('doNotVoteForYourself') == 1) {
            $this->addFlashMessage(
                LocalizationUtility::translate('tx_pwcomments.custom.doNotVoteForYourself', 'PwComments')
            );
            $this->view->assign('hasCustomMessages', true);
        } elseif ((!isset($this->settings['enableVoting']) || !$this->settings['enableVoting']) && GeneralUtility::_GP('votingDisabled') == 1) {
            $this->addFlashMessage(
                LocalizationUtility::translate('tx_pwcomments.custom.votingDisabled', 'PwComments')
            );
            $this->view->assign('hasCustomMessages', true);
        }
    }

    /**
     * Don't show Error Message because of own genereated error Messages
     *
     * @return false The flash message or FALSE if no flash message should be set
     */
    protected function getErrorFlashMessage(): bool
    {
        return false;
    }
}
