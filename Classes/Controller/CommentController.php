<?php
namespace PwCommentsTeam\PwComments\Controller;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2016 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016 Christian Wolfram <c.wolfram@chriwo.de>
 */
use PwCommentsTeam\PwComments\Domain\Model\Comment;
use PwCommentsTeam\PwComments\Domain\Model\Vote;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\CacheService;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * The comment controller
 *
 * @package PwCommentsTeam\PwComments
 */
class CommentController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {
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
	protected $currentUser = array();

	/**
	 * @var string
	 */
	protected $currentAuthorIdent;

	/**
	 * @var \PwCommentsTeam\PwComments\Utility\Settings
	 * @inject
	 */
	protected $settingsUtility;

	/**
	 * @var \PwCommentsTeam\PwComments\Utility\Mail
	 * @inject
	 */
	protected $mailUtility;

	/**
	 * @var \PwCommentsTeam\PwComments\Utility\Cookie
	 * @inject
	 */
	protected $cookieUtility;

	/**
	 * @var \PwCommentsTeam\PwComments\Domain\Repository\CommentRepository
	 * @inject
	 */
	protected $commentRepository;

	/**
	 * @var \PwCommentsTeam\PwComments\Domain\Repository\FrontendUserRepository
	 * @inject
	 */
	protected $frontendUserRepository;

	/**
	 * @var \PwCommentsTeam\PwComments\Domain\Repository\VoteRepository
	 * @inject
	 */
	protected $voteRepository;

	/**
	 * Initialize action, which will be executed before every
	 * other action in this controller
	 *
	 * @return void
	 */
	public function  initializeAction() {
		$this->settings = $this->settingsUtility->renderConfigurationArray(
			$this->settings,
			($this->settings['_skipMakingSettingsRenderable']) ? FALSE : TRUE
		);
		$this->pageUid = $GLOBALS['TSFE']->id;
		$this->currentUser = $GLOBALS['TSFE']->fe_user->user;
		$this->currentAuthorIdent = ($this->currentUser['uid']) ? $this->currentUser['uid'] : $this->cookieUtility->get('ahash');
		if (is_numeric($this->currentAuthorIdent) && !$this->currentUser['uid']) {
			$this->currentAuthorIdent = NULL;
		}

		if ($this->settings['useEntryUid']) {
			$this->entryUid = intval($this->settings['entryUid']);
		}

		if (isset($this->request->getArguments()['newComment']) || isset($this->request->getArguments()['comment'])) {
			$this->clearPageCache($this->pageUid);
		}
	}

	/**
	 * Displays all comments by pid
	 *
	 * @param Comment $commentToReplyTo
	 * @return void
	 *
	 * @dontvalidate $commentToReplyTo
	 * @ignorevalidation $commentToReplyTo
	 */
	public function indexAction(Comment $commentToReplyTo = NULL) {
		if ($this->settings['invertCommentSorting']) {
			$this->commentRepository->setInvertCommentSorting(TRUE);
		}
		if ($this->settings['invertReplySorting']) {
			$this->commentRepository->setInvertReplySorting(TRUE);
		}

		if ($this->entryUid > 0) {
			/* @var $comments \TYPO3\CMS\Extbase\Persistence\Generic\QueryResult */
			$comments = $this->commentRepository->findByPidAndEntryUid($this->pageUid, $this->entryUid);
		} else {
			/* @var $comments \TYPO3\CMS\Extbase\Persistence\Generic\QueryResult */
			$comments = $this->commentRepository->findByPid($this->pageUid);
		}

		$this->handleCustomMessages();

		$upvotedCommentUids = array();
		$downvotedCommentUids = array();
		if ($this->currentAuthorIdent !== NULL) {
			$votes = $this->voteRepository->findByPidAndAuthorIdent($this->pageUid, $this->currentAuthorIdent);
			/** @var $vote Vote */
			foreach ($votes as $vote) {
				if ($vote->isDownvote()) {
					$downvotedCommentUids[] = $vote->getComment()->getUid();
				} else {
					$upvotedCommentUids[] = $vote->getComment()->getUid();
				}
			}
		}
		$this->view->assign('upvotedCommentUids', $upvotedCommentUids);
		$this->view->assign('downvotedCommentUids', $downvotedCommentUids);

		$this->view->assign('comments', $comments);
		$this->view->assign('commentCount', $this->calculateCommentCount($comments));
		$this->view->assign('commentToReplyTo', $commentToReplyTo);
	}

	/**
	 * Create action
	 *
	 * @param Comment $newComment
	 * @return bool
	 * @dontverifyrequesthash
	 */
	public function createAction(Comment $newComment = NULL) {
		// Hidden field Spam-Protection
		if ($this->settings['hiddenFieldSpamProtection'] && $this->request->hasArgument($this->settings['hiddenFieldName'])
			&& $this->request->getArgument($this->settings['hiddenFieldName'])) {

			$this->redirectToURI($this->buildUriByUid($this->pageUid) . '#' . $this->settings['writeCommentAnchor']);
			return FALSE;
		}
		if ($newComment === NULL) {
			$this->redirectToURI($this->buildUriByUid($this->pageUid));
			return FALSE;
		}
		$this->createAuthorIdent();

		$newComment->setPid($this->pageUid);
		$newComment->setEntryUid($this->entryUid);
		$newComment->setAuthorIdent($this->currentAuthorIdent);

		$author = NULL;
		if ($this->currentUser['uid']) {
			$author = $this->frontendUserRepository->findByUid($this->currentUser['uid']);
		}
		if ($author !== NULL) {
			$newComment->setAuthor($author);
		} else {
			$newComment->setAuthor(NULL);
			$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_pwcomments_unregistredUserName', $newComment->getAuthorName());
			$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_pwcomments_unregistredUserMail', $newComment->getAuthorMail());
		}
		$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_pwcomments_lastComment', time());

		$translateArguments = array(
			'name' => $newComment->getAuthorName(),
			'email' => $newComment->getAuthorMail(),
			'message' => $newComment->getMessage(),
		);

			// Modify comment if moderation is active
		if ($this->settings['moderateNewComments']) {
			$newComment->setHidden(TRUE);
			$this->addFlashMessage(
				LocalizationUtility::translate('tx_pwcomments.moderationNotice', 'PwComments', $translateArguments)
			);
		} else {
			$this->addFlashMessage(
				LocalizationUtility::translate('tx_pwcomments.thanks', 'PwComments', $translateArguments)
			);
		}

		$this->commentRepository->add($newComment);

		$this->getPersistenceManager()->persistAll();

		if ($this->settings['sendMailOnNewCommentsTo']) {
			$this->mailUtility->setSettings($this->settings);
			$this->mailUtility->setFluidTemplate($this->makeFluidTemplateObject());
			$this->mailUtility->setControllerContext($this->controllerContext);
			$this->mailUtility->setReceivers($this->settings['sendMailOnNewCommentsTo']);
			$this->mailUtility->setTemplatePath($this->settings['sendMailTemplate']);
			$this->mailUtility->sendMail($newComment);
		}

		if ($this->settings['sendMailToAuthorAfterSubmit'] && $newComment->hasCommentAuthorMailAddress()) {
			$this->mailUtility->setSettings($this->settings);
			$this->mailUtility->setFluidTemplate($this->makeFluidTemplateObject());
			$this->mailUtility->setControllerContext($this->controllerContext);
			$this->mailUtility->setReceivers($newComment->getCommentAuthorMailAddress());
			$this->mailUtility->setTemplatePath($this->settings['sendMailToAuthorAfterSubmitTemplate']);
			$this->mailUtility->sendMail($newComment);
		}

		if ($this->settings['moderateNewComments']) {
			$anchor = '#' . $this->settings['successfulAnchor'];
		} else {
			$anchor = '#' . $this->settings['commentAnchorPrefix'] . $newComment->getUid();
		}

		$this->redirectToURI($this->buildUriByUid($this->pageUid, TRUE) . $anchor);
		return FALSE;
	}

	/**
	 * New action
	 *
	 * @param Comment $newComment New Comment
	 * @param Comment $commentToReplyTo Comment to reply to
	 * @return void
	 *
	 * @dontvalidate $newComment
	 * @dontvalidate $commentToReplyTo
	 * @ignorevalidation $newComment
	 * @ignorevalidation $commentToReplyTo
	 * @dontverifyrequesthash
	 */
	public function newAction(Comment $newComment = NULL, Comment $commentToReplyTo = NULL) {
		if ($newComment !== NULL) {
			$this->view->assign('newComment', $newComment);
		}
		$this->view->assign('commentToReplyTo', $commentToReplyTo);

		if ($this->currentUser) {
			$this->view->assign('user', $this->currentUser);
		} else {
				// Get name of unregistred user
			if ($newComment !== NULL && $newComment->getAuthorName()) {
				$unregistredUserName = $newComment->getAuthorName();
			} else {
				$unregistredUserName = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_pwcomments_unregistredUserName');
			}

				// Get mail of unregistred user
			if ($newComment !== NULL && $newComment->getAuthorMail()) {
				$unregistredUserMail = $newComment->getAuthorMail();
			} else {
				$unregistredUserMail = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_pwcomments_unregistredUserMail');
			}

			$this->view->assign('unregistredUserName', $unregistredUserName);
			$this->view->assign('unregistredUserMail', $unregistredUserMail);
		}
	}

	/**
	 * Upvote action
	 *
	 * @param Comment $comment
	 * @return string Empty string. This action will perform a redirect
	 *
	 * @dontvalidate $comment
	 * @ignorevalidation $comment
	 */
	public function upvoteAction(Comment $comment) {
		$this->performVoting($comment, Vote::TYPE_UPVOTE);
		return '';
	}

	/**
	 * Downvote action
	 *
	 * @param Comment $comment
	 * @return string Empty string. This action will perform a redirect
	 *
	 * @dontvalidate $comment
	 * @ignorevalidation $comment
	 */
	public function downvoteAction(Comment $comment) {
		$this->performVoting($comment, Vote::TYPE_DOWNVOTE);
		return '';
	}

	/**
	 * Perform database operations for voting
	 *
	 * @param Comment $comment
	 * @param int $type Check out Tx_PwComments_Domain_Model_Vote constants
	 * @return void
	 */
	protected function performVoting(Comment $comment, $type) {
		$commentAnchor = '#' . $this->settings['commentAnchorPrefix'] . $comment->getUid();
		if (!$this->settings['enableVoting']) {
			$this->redirectToURI($this->buildUriToPage($this->pageUid, array('votingDisabled' => 1)) . $commentAnchor);
			return;
		}

		$this->createAuthorIdent();

		$vote = NULL;
		if ($this->currentAuthorIdent !== NULL) {
			if ($this->settings['ignoreVotingForOwnComments'] && $this->currentAuthorIdent == $comment->getAuthorIdent()) {
				$this->redirectToURI($this->buildUriToPage($this->pageUid, array('doNotVoteForYourself' => 1)) . $commentAnchor);
				return;
			}
			$vote = $this->voteRepository->findOneByCommentAndAuthorIdent($comment, $this->currentAuthorIdent);
		}

		if ($vote === NULL) {
			$comment->addVote($this->createNewVote($type, $comment));
			$this->commentRepository->update($comment);
		} else {
			$comment->removeVote($vote);
			$this->commentRepository->update($comment);
			$this->voteRepository->remove($vote);
			if ($type !== $vote->getType()) {
				$this->getPersistenceManager()->persistAll();
				$this->performVoting($comment, $type);
			}
		}

		$this->getPersistenceManager()->persistAll();

		$this->redirectToURI($this->buildUriToPage($this->pageUid) . $commentAnchor);
	}


	/**
	 * Creates new vote instance
	 *
	 * @param int $type See Tx_PwComments_Domain_Model_Vote constants
	 * @param Comment $comment
	 * @return Vote
	 */
	protected function createNewVote($type, Comment $comment) {
		/** @var Vote $newVote */
		$newVote = GeneralUtility::makeInstance('PwCommentsTeam\PwComments\Domain\Model\Vote');
		$newVote->setComment($comment);
		$newVote->setPid($this->pageUid);
		$newVote->setAuthorIdent($this->currentAuthorIdent);
		if ($this->currentUser['uid']) {
			/** @var \PwCommentsTeam\PwComments\Domain\Model\FrontendUser $author */
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
	protected function createAuthorIdent() {
		if ($this->currentAuthorIdent === NULL) {
			$this->currentAuthorIdent = uniqid() . uniqid();
			$this->cookieUtility->set('ahash', $this->currentAuthorIdent);
		}
	}

	/**
	 * Sends mail to comment author whe comment has been approved (and published)
	 *
	 * @return void
	 */
	public function sendAuthorMailWhenCommentHasBeenApprovedAction() {
		/** @var Comment $comment */
		$comment = $this->commentRepository->findByCommentUid($this->settings['_commentUid']);

		if ($this->settings['moderateNewComments'] && $this->settings['sendMailToAuthorAfterPublish']) {
			$this->mailUtility->setSettings($this->settings);
			$this->mailUtility->setFluidTemplate($this->makeFluidTemplateObject());
			$this->mailUtility->setControllerContext($this->controllerContext);
			$this->mailUtility->setReceivers($comment->getAuthorMail());
			$this->mailUtility->setTemplatePath($this->settings['sendMailToAuthorAfterPublishTemplate']);
			$this->mailUtility->setSubjectLocallangKey('tx_pwcomments.mailToAuthorAfterPublish.subject');
			$this->mailUtility->setAddQueryStringToLinks(FALSE);
			$this->mailUtility->sendMail($comment);
			$this->addFlashMessage(
				LocalizationUtility::translate('mailSentToAuthorAfterPublish', 'PwComments', array($comment->getAuthorMail()))
			);
		}
	}

	/**
	 * Returns a built URI by pageUid
	 *
	 * @param int $uid The uid to use for building link
	 * @param bool $excludeCommentToReplyTo If TRUE the comment to reply to will be
	 * 			   removed from query string
	 * @return string The link
	 */
	private function buildUriByUid($uid, $excludeCommentToReplyTo = FALSE) {
		$excludeFromQueryString = array('tx_pwcomments_pi1[action]', 'tx_pwcomments_pi1[controller]', 'cHash');
		if ($excludeCommentToReplyTo === TRUE) {
			$excludeFromQueryString[] = 'tx_pwcomments_pi1[commentToReplyTo]';
		}
		$uri = $this->uriBuilder
				->setTargetPageUid($uid)
				->setAddQueryString(TRUE)
				->setArgumentsToBeExcludedFromQueryString($excludeFromQueryString)
				->build();
		$uri = $this->addBaseUriIfNecessary($uri);
		return $uri;
	}

	/**
	 * Builds uri by uid and arguments
	 *
	 * @param int $uid
	 * @param array $arguments
	 * @return string
	 */
	protected function buildUriToPage($uid, array $arguments = array()) {
		$uri = $this->uriBuilder
				->setTargetPageUid($uid)
				->setArguments($arguments)
				->build();
		return $this->addBaseUriIfNecessary($uri);
	}

	/**
	 * Makes and returns a fluid template object
	 *
	 * @return \TYPO3\CMS\Fluid\View\StandaloneView the fluid template object
	 */
	protected function makeFluidTemplateObject() {
		/** @var \TYPO3\CMS\Fluid\View\StandaloneView $fluidTemplate  */
		$fluidTemplate = GeneralUtility::makeInstance('TYPO3\CMS\Fluid\View\StandaloneView');

		// Set controller context
		$controllerContext = $this->buildControllerContext();
		$controllerContext->setRequest($this->request);
		$fluidTemplate->setControllerContext($controllerContext);

		return $fluidTemplate;
	}

	/**
	 * Returns count of comments and/or comments and replies.
	 *
	 * @param \TYPO3\CMS\Extbase\Persistence\Generic\QueryResult $comments
	 * @return int
	 */
	protected function calculateCommentCount(\TYPO3\CMS\Extbase\Persistence\Generic\QueryResult $comments) {
		$replyAmount = 0;
		if ($this->settings['countReplies']) {
			/** @var $comment Comment */
			foreach ($comments as $comment) {
				$replyAmount += count($comment->getReplies());
			}
		}
		return count($comments) + $replyAmount;
	}

	/**
	 * Adds flash messages based on predefined get parameters
	 *
	 * @return void
	 */
	protected function handleCustomMessages() {
		if ($this->settings['ignoreVotingForOwnComments'] && GeneralUtility::_GP('doNotVoteForYourself') == 1) {
			$this->addFlashMessage(
				LocalizationUtility::translate('tx_pwcomments.custom.doNotVoteForYourself', 'PwComments')
			);
			$this->view->assign('hasCustomMessages', TRUE);
		} elseif (!$this->settings['enableVoting'] && GeneralUtility::_GP('votingDisabled') == 1) {
			$this->addFlashMessage(
				LocalizationUtility::translate('tx_pwcomments.custom.votingDisabled', 'PwComments')
			);
			$this->view->assign('hasCustomMessages', TRUE);
		}
	}

	/**
	 * Don't show Error Message because of own genereated error Messages
	 *
	 * @return string The flash message or FALSE if no flash message should be set
	 */
	protected function getErrorFlashMessage() {
		return FALSE;
	}

	/**
	 * Get PersistenceManager
	 *
	 * @return \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
	 */
	protected function getPersistenceManager() {
		/** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
		$objectManager = GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Object\ObjectManager');
		return $objectManager->get('TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager');
	}

	/**
	 * Clears the cache of the page with the given page id.
	 *
	 * @param int $pageId
	 * @return void
	 */
	protected function clearPageCache($pageId) {
		$pageId = (int) $pageId;
		if ($pageId <= 0) {
			return;
		}
		$this->cacheService->clearPageCache($pageId);
	}
}
