<?php
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

/**
 * This class provides some methods to build and send mails
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Tx_PwComments_Utility_Mail {
	/**
	 * @var array settings of controller
	 */
	protected $settings = array();

	/**
	 * @var Tx_Fluid_View_TemplateView
	 */
	protected $fluidTemplate = NULL;

	/**
	 * @var Tx_Extbase_MVC_Controller_ControllerContext
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
	 * @var boolean
	 */
	protected $addQueryStringToLinks = TRUE;

	/**
	 * Sets the settings of controller
	 *
	 * @param array $settings settings to set
	 *
	 * @return void
	 */
	public function setSettings(array $settings) {
		$this->settings = $settings;
	}

	/**
	 * Set the fluid template from controller
	 *
	 * @param Tx_Fluid_View_StandaloneView $fluidTemplate the fluid template
	 *
	 * @return void
	 */
	public function setFluidTemplate(Tx_Fluid_View_StandaloneView $fluidTemplate) {
		$this->fluidTemplate = $fluidTemplate;
	}

	/**
	 * Set the controller context from controller
	 *
	 * @param Tx_Extbase_MVC_Controller_ControllerContext $controllerContext the controller context
	 *
	 * @return void
	 */
	public function setControllerContext(Tx_Extbase_MVC_Controller_ControllerContext $controllerContext) {
		$this->controllerContext = $controllerContext;
	}

	/**
	 * Creates and sends mail
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $comment comment which triggers the mail send method
	 * @return boolean Returns TRUE if the mail has been sent successfully, otherwise returns FALSE
	 */
	public function sendMail(Tx_PwComments_Domain_Model_Comment $comment) {
		/** @var t3lib_mail_Message $mail */
        $mail = t3lib_div::makeInstance('t3lib_mail_Message');

        $mail->setFrom(
            Tx_Extbase_Utility_Localization::translate('tx_pwcomments.notificationMail.from.mail', 'PwComments', array(t3lib_div::getHostname())),
            Tx_Extbase_Utility_Localization::translate('tx_pwcomments.notificationMail.from.name', 'PwComments')
        );

        $receivers = t3lib_div::trimExplode(',', $this->getReceivers(), TRUE);
        $mail->setTo($receivers);


        $mail->setSubject(Tx_Extbase_Utility_Localization::translate($this->getSubjectLocallangKey(), 'PwComments', array(t3lib_div::getHostname())));
        $mail->addPart($this->getMailMessage($comment), $this->settings['sendMailMimeType']);

        return (boolean) $mail->send();
	}

	/**
	 * Gets the message for a notification mail as fluid template
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $comment comment which triggers the mail send method
	 * @return string The rendered fluid template (HTML or plain text)
	 */
	protected function getMailMessage(Tx_PwComments_Domain_Model_Comment $comment) {
		$mailTemplate = t3lib_div::getFileAbsFileName($this->getTemplatePath());
		if (!file_exists($mailTemplate)) {
			throw new Exception('Mail template (' . $mailTemplate . ') not found. ');
		}
		$this->fluidTemplate->setTemplatePathAndFilename($mailTemplate);


		// Assign variables
		$this->fluidTemplate->assign('comment', $comment);
		$this->fluidTemplate->assign('settings', $this->settings);

		$uriBuilder = $this->controllerContext->getUriBuilder();

		$subFolder = ($this->settings['subFolder']) ? $this->settings['subFolder'] : '';

		$articleLink = 'http://' . t3lib_div::getHostname() . $subFolder . '/' .
					   $uriBuilder
							->setTargetPageUid($comment->getPid())
							->setAddQueryString($this->getAddQueryStringToLinks())
							->setArgumentsToBeExcludedFromQueryString(array('id', 'cHash', 'tx_pwcomments_pi1[action]', 'tx_pwcomments_pi1[controller]'))
					   		->setUseCacheHash(FALSE)
							->buildFrontendUri();
		$this->fluidTemplate->assign('articleLink', $articleLink);

		$backendDomain = ($this->settings['overwriteBackendDomain']) ? $this->settings['overwriteBackendDomain'] : t3lib_div::getHostname();
		$backendLink = 'http://' . $backendDomain . $subFolder . '/typo3/alt_doc.php?M=web_list&id=' . $comment->getPid() . '&edit[tx_pwcomments_domain_model_comment][' . $comment->getUid() . ']=edit';
		$this->fluidTemplate->assign('backendLink', $backendLink);

		return $this->fluidTemplate->render();
	}

	/**
	 * @return string
	 */
	public function getReceivers() {
		return $this->receivers;
	}

	/**
	 * @param string $receivers
	 */
	public function setReceivers($receivers) {
		$this->receivers = $receivers;
	}

	/**
	 * @return string
	 */
	public function getTemplatePath() {
		return $this->templatePath;
	}

	/**
	 * @param string $templatePath
	 */
	public function setTemplatePath($templatePath) {
		$this->templatePath = $templatePath;
	}

	/**
	 * @return string
	 */
	public function getSubjectLocallangKey() {
		return $this->subjectLocallangKey;
	}

	/**
	 * @param string $subjectLocallangKey
	 */
	public function setSubjectLocallangKey($subjectLocallangKey) {
		$this->subjectLocallangKey = $subjectLocallangKey;
	}

	/**
	 * @return boolean
	 */
	public function getAddQueryStringToLinks() {
		return $this->addQueryStringToLinks;
	}

	/**
	 * @param boolean $addQueryStringToLinks
	 */
	public function setAddQueryStringToLinks($addQueryStringToLinks) {
		$this->addQueryStringToLinks = $addQueryStringToLinks;
	}

}
?>