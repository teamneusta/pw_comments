<?php
namespace PwCommentsTeam\PwComments\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2014 Armin Ruediger Vieweg <armin.vieweg@diemedialen.de>
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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use PwCommentsTeam\PwComments\Domain\Model\Comment;

/**
 * This class provides some methods to build and send mails
 *
 * @package PwCommentsTeam\PwComments
 */
class Mail {
	/**
	 * @var array settings of controller
	 */
	protected $settings = array();

	/**
	 * @var \TYPO3\CMS\Fluid\View\TemplateView
	 */
	protected $fluidTemplate = NULL;

	/**
	 * @var \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext
	 */
	protected $controllerContext = NULL;

	/**
	 * @var string comma separated string of mail addresses
	 */
	protected $receivers = '';

	/**
	 * @var string
	 */
	protected $templatePath = '';

	/**
	 * @var string
	 */
	protected $subjectLocallangKey = 'tx_pwcomments.notificationMail.subject';

	/**
	 * @var bool
	 */
	protected $addQueryStringToLinks = TRUE;

	/**
	 * Sets the settings of controller
	 *
	 * @param array $settings settings to set
	 * @return void
	 */
	public function setSettings(array $settings) {
		$this->settings = $settings;
	}

	/**
	 * Set the fluid template from controller
	 *
	 * @param \TYPO3\CMS\Fluid\View\StandaloneView $fluidTemplate the fluid template
	 * @return void
	 */
	public function setFluidTemplate(\TYPO3\CMS\Fluid\View\StandaloneView $fluidTemplate) {
		$this->fluidTemplate = $fluidTemplate;
	}

	/**
	 * Set the controller context from controller
	 *
	 * @param \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext $controllerContext
	 * @return void
	 */
	public function setControllerContext(\TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext $controllerContext) {
		$this->controllerContext = $controllerContext;
	}

	/**
	 * Creates and sends mail
	 *
	 * @param Comment $comment comment
	 * @return bool Returns TRUE if the mail has been sent successfully
	 */
	public function sendMail(Comment $comment) {
		/** @var \TYPO3\CMS\Core\Mail\MailMessage $mail */
		$mail = GeneralUtility::makeInstance('TYPO3\CMS\Core\Mail\MailMessage');

		$mail->setFrom(
			LocalizationUtility::translate(
				'tx_pwcomments.notificationMail.from.mail', 'PwComments', array(GeneralUtility::getHostname())
			),
			LocalizationUtility::translate(
				'tx_pwcomments.notificationMail.from.name', 'PwComments'
			)
		);

		$receivers = GeneralUtility::trimExplode(',', $this->getReceivers(), TRUE);
		$mail->setTo($receivers);
		$mail->setSubject(LocalizationUtility::translate(
			$this->getSubjectLocallangKey(), 'PwComments', array(GeneralUtility::getHostname())
		));
		$mail->addPart($this->getMailMessage($comment), $this->settings['sendMailMimeType']);
		return (bool) $mail->send();
	}

	/**
	 * Gets the message for a notification mail as fluid template
	 *
	 * @param Comment $comment comment which triggers the mail send method
	 * @return string The rendered fluid template (HTML or plain text)
	 *
	 * @throws \Exception
	 */
	protected function getMailMessage(Comment $comment) {
		$mailTemplate = GeneralUtility::getFileAbsFileName($this->getTemplatePath());
		if (!file_exists($mailTemplate)) {
			throw new \Exception('Mail template (' . $mailTemplate . ') not found. ');
		}
		$this->fluidTemplate->setTemplatePathAndFilename($mailTemplate);

		// Assign variables
		$this->fluidTemplate->assign('comment', $comment);
		$this->fluidTemplate->assign('settings', $this->settings);

		$uriBuilder = $this->controllerContext->getUriBuilder();

		$subFolder = ($this->settings['subFolder']) ? $this->settings['subFolder'] : '';

		$articleLink = 'http://' . GeneralUtility::getHostname() . $subFolder . '/' .
						$uriBuilder
							->setTargetPageUid($comment->getPid())
							->setAddQueryString($this->getAddQueryStringToLinks())
							->setArgumentsToBeExcludedFromQueryString(
								array('id', 'cHash', 'tx_pwcomments_pi1[action]', 'tx_pwcomments_pi1[controller]')
							)
							->setUseCacheHash(FALSE)
							->buildFrontendUri();
		$this->fluidTemplate->assign('articleLink', $articleLink);

		$backendDomain = $this->settings['overwriteBackendDomain']
			? $this->settings['overwriteBackendDomain']
			: GeneralUtility::getHostname();

		$backendLink = 'http://' . $backendDomain . $subFolder . '/typo3/alt_doc.php?M=web_list&id=' . $comment->getPid() .
			'&edit[tx_pwcomments_domain_model_comment][' . $comment->getUid() . ']=edit';

		$this->fluidTemplate->assign('backendLink', $backendLink);
		return $this->fluidTemplate->render();
	}

	/**
	 * Get receivers
	 *
	 * @return string
	 */
	public function getReceivers() {
		return $this->receivers;
	}

	/**
	 * Set receivers
	 *
	 * @param string $receivers
	 * @return void
	 */
	public function setReceivers($receivers) {
		$this->receivers = $receivers;
	}

	/**
	 * Get template path
	 *
	 * @return string
	 */
	public function getTemplatePath() {
		return $this->templatePath;
	}

	/**
	 * Set template path
	 *
	 * @param string $templatePath
	 * @return void
	 */
	public function setTemplatePath($templatePath) {
		$this->templatePath = $templatePath;
	}

	/**
	 * Get subject locallang key
	 *
	 * @return string
	 */
	public function getSubjectLocallangKey() {
		return $this->subjectLocallangKey;
	}

	/**
	 * Set subject locallang key
	 *
	 * @param string $subjectLocallangKey
	 * @return void
	 */
	public function setSubjectLocallangKey($subjectLocallangKey) {
		$this->subjectLocallangKey = $subjectLocallangKey;
	}

	/**
	 * Get add query string to links
	 *
	 * @return bool
	 */
	public function getAddQueryStringToLinks() {
		return $this->addQueryStringToLinks;
	}

	/**
	 * Set add query string to links
	 *
	 * @param bool $addQueryStringToLinks
	 * @return void
	 */
	public function setAddQueryStringToLinks($addQueryStringToLinks) {
		$this->addQueryStringToLinks = $addQueryStringToLinks;
	}
}