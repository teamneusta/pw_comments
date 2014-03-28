<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Armin Ruediger Vieweg <info@professorweb.de>
*
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * The comment controller
 *
 * @version $Id$
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Tx_PwComments_Controller_CommentController extends Tx_Extbase_MVC_Controller_ActionController {
	/**
	 * @var integer
	 */
	protected $pageUid = 0;

	/**
	 * @var integer
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
	 * @var Tx_PwComments_Utility_Settings
	 */
	protected $settingsUtility = NULL;

	/**
	 * @var Tx_PwComments_Utility_Mail
	 */
	protected $mailUtility = NULL;

	/**
	 * @var Tx_PwComments_Utility_Cookie
	 */
	protected $cookieUtility = NULL;

	/**
	 * @var Tx_PwComments_Domain_Repository_CommentRepository
	 */
	protected $commentRepository;

	/**
	 * @var Tx_PwComments_Domain_Repository_FrontendUserRepository
	 */
	protected $frontendUserRepository;

	/**
	 * @var Tx_PwComments_Domain_Repository_VoteRepository
	 */
	protected $voteRepository;

	/**
	 * Injects the voteRepository
	 *
	 * @param Tx_PwComments_Domain_Repository_VoteRepository $repository
	 * @return void
	 */
	public function injectVoteRepository(Tx_PwComments_Domain_Repository_VoteRepository $repository) {
		$this->voteRepository = $repository;
	}

	/**
	 * Injects the settings utility
	 *
	 * @param Tx_PwComments_Utility_Settings $utility
	 * @return void
	 */
	public function injectSettingsUtility(Tx_PwComments_Utility_Settings $utility) {
		$this->settingsUtility = $utility;
	}

	/**
	 * Injects the mail utility
	 *
	 * @param Tx_PwComments_Utility_Mail $utility
	 * @return void
	 */
	public function injectMailUtility(Tx_PwComments_Utility_Mail $utility) {
		$this->mailUtility = $utility;
	}

	/**
	 * Injects the cookie utility
	 *
	 * @param Tx_PwComments_Utility_Cookie $utility
	 * @return void
	 */
	public function injectCookieUtility(Tx_PwComments_Utility_Cookie $utility) {
		$this->cookieUtility = $utility;
	}

	/**
	 * Injects the comment repository
	 *
	 * @param Tx_PwComments_Domain_Repository_CommentRepository $repository the repository to inject
	 * @return void
	 */
	public function injectCommentRepository(Tx_PwComments_Domain_Repository_CommentRepository $repository) {
		$this->commentRepository = $repository;
	}

	/**
	 * Injects the frontend user repository
	 *
	 * @param Tx_PwComments_Domain_Repository_FrontendUserRepository $repository the repository to inject
	 * @return void
	 */
	public function injectFrontendUserRepository(Tx_PwComments_Domain_Repository_FrontendUserRepository $repository) {
		$this->frontendUserRepository = $repository;
	}

	/**
	 * Initialize action, which will be executed before every other action in this controller
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
	}

	/**
	 * Displays all comments by pid
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $commentToReplyTo
	 * @return void
	 *
	 * @dontvalidate $commentToReplyTo
	 * @ignorevalidation $commentToReplyTo
	 */
	public function indexAction(Tx_PwComments_Domain_Model_Comment $commentToReplyTo = NULL) {
		if ($this->entryUid > 0) {
			/* @var $comments Tx_Extbase_Persistence_QueryResult */
			$comments = $this->commentRepository->findByPidAndEntryUid($this->pageUid, $this->entryUid);
		} else {
			/* @var $comments Tx_Extbase_Persistence_QueryResult */
			$comments = $this->commentRepository->findByPid($this->pageUid);
		}

		$this->handleCustomMessages();

		$upvotedCommentUids = array();
		$downvotedCommentUids = array();
		if ($this->currentAuthorIdent !== NULL) {
			$votes = $this->voteRepository->findByPidAndAuthorIdent($this->pageUid, $this->currentAuthorIdent);
			/** @var $vote Tx_PwComments_Domain_Model_Vote */
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
	 * @param Tx_PwComments_Domain_Model_Comment $newComment New comment to persist
	 * @return void
	 *
	 * @dontverifyrequesthash
	 */
	public function createAction(Tx_PwComments_Domain_Model_Comment $newComment = NULL) {
		// Hidden field Spam-Protection
		if ($this->settings['hiddenFieldSpamProtection'] && $this->request->hasArgument($this->settings['hiddenFieldName']) && $this->request->getArgument($this->settings['hiddenFieldName'])) {
			$this->redirectToURI($this->buildUriByUid($this->pageUid) . '#' . $this->settings['writeCommentAnchor']);
			return;
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
			$this->flashMessageContainer->add(
				Tx_Extbase_Utility_Localization::translate('tx_pwcomments.moderationNotice', 'pw_comments', $translateArguments)
			);
		} else {
			$this->flashMessageContainer->add(
				Tx_Extbase_Utility_Localization::translate('tx_pwcomments.thanks', 'pw_comments', $translateArguments)
			);
		}

		$this->commentRepository->add($newComment);

		/* @var $persistenceManager Tx_Extbase_Persistence_Manager */
		$persistenceManager = t3lib_div::makeInstance('Tx_Extbase_Persistence_Manager');
		$persistenceManager->persistAll();

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
	}

	/**
	 * New action
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $newComment New Comment
	 * @param Tx_PwComments_Domain_Model_Comment $commentToReplyTo Comment to reply to
	 * @return void
	 *
	 * @dontvalidate $newComment
	 * @dontvalidate $commentToReplyTo
	 * @ignorevalidation $newComment
	 * @ignorevalidation $commentToReplyTo

	 * @dontverifyrequesthash
	 */
	public function newAction(Tx_PwComments_Domain_Model_Comment $newComment = NULL, Tx_PwComments_Domain_Model_Comment $commentToReplyTo = NULL) {
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
	 * @param Tx_PwComments_Domain_Model_Comment $comment
	 * @return void
	 * @dontvalidate $comment
	 * @ignorevalidation $comment
	 */
	public function upvoteAction(Tx_PwComments_Domain_Model_Comment $comment) {
		$this->performVoting($comment, Tx_PwComments_Domain_Model_Vote::TYPE_UPVOTE);
		return;
	}

	/**
	 * Downvote action
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $comment
	 * @return void
	 * @dontvalidate $comment
	 * @ignorevalidation $comment
	 */
	public function downvoteAction(Tx_PwComments_Domain_Model_Comment $comment) {
		$this->performVoting($comment, Tx_PwComments_Domain_Model_Vote::TYPE_DOWNVOTE);
		return;
	}

	/**
	 * Perform database operations for voting
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $comment
	 * @param integer $type Check out Tx_PwComments_Domain_Model_Vote constants
	 * @return void
	 */
	protected function performVoting(Tx_PwComments_Domain_Model_Comment $comment, $type) {
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
				/* @var $persistenceManager Tx_Extbase_Persistence_Manager */
				$persistenceManager = t3lib_div::makeInstance('Tx_Extbase_Persistence_Manager');
				$persistenceManager->persistAll();
				$this->performVoting($comment, $type);
			}
		}

		/* @var $persistenceManager Tx_Extbase_Persistence_Manager */
		$persistenceManager = t3lib_div::makeInstance('Tx_Extbase_Persistence_Manager');
		$persistenceManager->persistAll();

		$this->redirectToURI($this->buildUriToPage($this->pageUid) . $commentAnchor);
	}


	/**
	 * Creates new vote instance
	 *
	 * @param integer $type See Tx_PwComments_Domain_Model_Vote constants
	 * @param Tx_PwComments_Domain_Model_Comment $comment
	 * @return Tx_PwComments_Domain_Model_Vote
	 */
	protected function createNewVote($type, Tx_PwComments_Domain_Model_Comment $comment) {
		/** @var Tx_PwComments_Domain_Model_Vote $newVote */
		$newVote = t3lib_div::makeInstance('Tx_PwComments_Domain_Model_Vote');
		$newVote->setComment($comment);
		$newVote->setPid($this->pageUid);
		$newVote->setAuthorIdent($this->currentAuthorIdent);
		if ($this->currentUser['uid']) {
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
	 * @return void
	 */
	public function sendAuthorMailWhenCommentHasBeenApprovedAction() {
		/** @var Tx_PwComments_Domain_Model_Comment $comment */
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
			$this->flashMessageContainer->add(Tx_Extbase_Utility_Localization::translate('mailSentToAuthorAfterPublish', 'pw_comments', array($comment->getAuthorMail())));
		}
	}

	/**
	 * Returns a built URI by pageUid
	 *
	 * @param integer $uid The uid to use for building link
	 * @param boolean $excludeCommentToReplyTo If TRUE the comment to reply to will be removed
	 * @return string The link
	 */
	private function buildUriByUid($uid, $excludeCommentToReplyTo = FALSE) {
		$excludeFromQueryString = array('tx_pwcomments_pi1[action]', 'tx_pwcomments_pi1[controller]');
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
	 * @param integer $uid
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
	 * @return Tx_Fluid_View_StandaloneView the fluid template object
	 */
	protected function makeFluidTemplateObject() {
		/** @var Tx_Fluid_View_StandaloneView $fluidTemplate  */
		$fluidTemplate = t3lib_div::makeInstance('Tx_Fluid_View_StandaloneView');

		// Set controller context
		$controllerContext = $this->buildControllerContext();
		$controllerContext->setRequest($this->request);
		$fluidTemplate->setControllerContext($controllerContext);

		return $fluidTemplate;
	}

	/**
	 * Returns count of comments and/or comments and replies.
	 *
	 * @param Tx_Extbase_Persistence_QueryResult $comments
	 * @return integer
	 */
	protected function calculateCommentCount(Tx_Extbase_Persistence_QueryResult $comments) {
		$replyAmount = 0;
		if ($this->settings['countReplies']) {
			/** @var $comment Tx_PwComments_Domain_Model_Comment */
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
		if ($this->settings['ignoreVotingForOwnComments'] && t3lib_div::_GP('doNotVoteForYourself') == 1) {
			$this->flashMessageContainer->add(
				Tx_Extbase_Utility_Localization::translate('tx_pwcomments.custom.doNotVoteForYourself', 'pw_comments')
			);
			$this->view->assign('hasCustomMessages', TRUE);
		} elseif (!$this->settings['enableVoting'] && t3lib_div::_GP('votingDisabled') == 1) {
			$this->flashMessageContainer->add(
				Tx_Extbase_Utility_Localization::translate('tx_pwcomments.custom.votingDisabled', 'pw_comments')
			);
			$this->view->assign('hasCustomMessages', TRUE);
		}
	}
}
?>