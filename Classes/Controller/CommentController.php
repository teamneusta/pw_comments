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
	 * @var Tx_PwComments_Utility_Settings
	 */
	protected $settingsUtility = NULL;

	/**
	 * @var Tx_PwComments_Utility_Mail
	 */
	protected $mailUtility = NULL;

	/**
	 * @var Tx_PwComments_Domain_Repository_CommentRepository
	 */
	protected $commentRepository;

	/**
	 * @var Tx_PwComments_Domain_Repository_FrontendUserRepository
	 */
	protected $frontendUserRepository;

	/**
	 * Injects the settings utility
	 *
	 * @param Tx_PwComments_Utility_Settings $utility
	 *
	 * @return void
	 */
	public function injectSettingsUtility(Tx_PwComments_Utility_Settings $utility) {
		$this->settingsUtility = $utility;
	}

	/**
	 * Injects the mail utility
	 *
	 * @param Tx_PwComments_Utility_Mail $utility
	 *
	 * @return void
	 */
	public function injectMailUtility(Tx_PwComments_Utility_Mail $utility) {
		$this->mailUtility = $utility;
	}

	/**
	 * Injects the comment repository
	 *
	 * @param Tx_PwComments_Domain_Repository_CommentRepository $repository the repository to inject
	 *
	 * @return void
	 */
	public function injectCommentRepository(Tx_PwComments_Domain_Repository_CommentRepository $repository) {
		$this->commentRepository = $repository;
	}

	/**
	 * Injects the frontend user repository
	 *
	 * @param Tx_PwComments_Domain_Repository_FrontendUserRepository $repository the repository to inject
	 *
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

		if ($this->settings['useEntryUid']) {
			$this->entryUid = intval($this->settings['entryUid']);
		}
	}

	/**
	 * Displays all comments by pid
	 */
	public function indexAction() {
		if ($this->entryUid > 0) {
			/* @var $comments Tx_Extbase_Persistence_QueryResult */
			$comments = $this->commentRepository->findByPidAndEntryUid($this->pageUid, $this->entryUid);
		} else {
			/* @var $comments Tx_Extbase_Persistence_QueryResult */
			$comments = $this->commentRepository->findByPid($this->pageUid);
		}

		$this->view->assign('comments', $comments);
	}

	/**
	 * Create action
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $newComment New comment to persist
	 *
	 * @return void
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
		$newComment->setPid($this->pageUid);
		$newComment->setEntryUid($this->entryUid);

		$author = $this->frontendUserRepository->findByUid($this->currentUser['uid']);
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

		if ($this->settings['sendMailToAuthorAfterSubmit']) {
			$this->mailUtility->setSettings($this->settings);
			$this->mailUtility->setFluidTemplate($this->makeFluidTemplateObject());
			$this->mailUtility->setControllerContext($this->controllerContext);
			$this->mailUtility->setReceivers($newComment->getAuthorMail());
			$this->mailUtility->setTemplatePath($this->settings['sendMailToAuthorAfterSubmitTemplate']);
			$this->mailUtility->sendMail($newComment);
		}

		if ($this->settings['moderateNewComments']) {
			$anchor = '#' . $this->settings['successfulAnchor'];
		} else {
			$anchor = '#' . $this->settings['commentAnchorPrefix'] . $newComment->getUid();
		}

		$this->redirectToURI($this->buildUriByUid($this->pageUid) . $anchor);
	}

	/**
	 * New action
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $newComment New Comment
	 *
	 * @dontvalidate $newComment
	 *
	 * @return void
	 */
	public function newAction($newComment = NULL) {
		if ($newComment !== NULL) {
			$this->view->assign('newComment', $newComment);
		}
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
	 * @return void
	 */
	public function sendAuthorMailWhenCommentHasBeenApprovedAction() {
		/** @var Tx_PwComments_Domain_Model_Comment $comment */
		$comment = $this->commentRepository->findByCommentUid($this->settings['_commentUid']);

		t3lib_utility_Debug::debug($comment, $this->settings['_commentUid']);

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
	 *
	 * @return string The link
	 */
	private function buildUriByUid($uid) {
		$uri = $this->uriBuilder
				->setTargetPageUid($uid)
				->setAddQueryString(TRUE)
				->setArgumentsToBeExcludedFromQueryString(array('tx_pwcomments_pi1[action]', 'tx_pwcomments_pi1[controller]'))
				->build();
		$uri = $this->addBaseUriIfNecessary($uri);
		return $uri;
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
}
?>