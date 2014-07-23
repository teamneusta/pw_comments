<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2014 Armin Ruediger Vieweg <armin@v.ieweg.de>
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
 * This class is a domain validator of comment model for attribute
 * comprehensive validation. It checks that at least one of the required fields
 * has been filled.
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Tx_PwComments_Domain_Validator_CommentValidator extends Tx_Extbase_Validation_Validator_AbstractValidator  {
	/**
	 * @var Tx_Extbase_Configuration_ConfigurationManagerInterface
	 */
	protected $configurationManager = NULL;

	/**
	 * @var Tx_PwComments_Utility_Settings
	 */
	protected $settingsUtility = NULL;

	/**
	 * @var array Settings defined in typoscript of pw_comments
	 */
	protected $settings = array();

	/**
	 * Injects the configurationManager
	 *
	 * @param Tx_Extbase_Configuration_ConfigurationManagerInterface $configurationManager
	 * @return void
	 */
	public function injectConfigurationManager(Tx_Extbase_Configuration_ConfigurationManagerInterface $configurationManager) {
		$this->configurationManager = $configurationManager;
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
	 * Initial function to validate
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $comment Comment model to validate
	 * @return boolean returns TRUE if conform to requirements, FALSE otherwise
	 */
	public function isValid($comment) {
		$this->settings = $this->getExtensionSettings();

		$errorNumber = NULL;
		$errorArguments = NULL;

		if (!$this->anyPropertyIsSet($comment)) {
			$errorNumber = 1299628038;
		} elseif (!$this->mailIsValid($comment)) {
			$errorNumber = 1299628371;
		} elseif (!$this->messageIsSet($comment)) {
			$errorNumber = 1299628099;
			$errorArguments = array($this->settings['secondsBetweenTwoComments']);
		} elseif ($this->settings['useBadWordsList'] && !$this->messageHasNoBadWords($comment)) {
			$errorNumber = 1315608355;
		} elseif (!$this->lastCommentRespectsTimer($comment)) {
			$errorNumber = 1300280476;
			$errorArguments = array($this->settings['secondsBetweenTwoComments']);
		}

		if ($errorNumber !== NULL) {
			$errorMessage = Tx_Extbase_Utility_Localization::translate(
				'tx_pwcomments.validation_error.' . $errorNumber, 'PwComments', $errorArguments
			);
			$this->addError($errorMessage, $errorNumber);
		}
		return ($errorNumber === NULL);
	}

	/**
	 * Validator to check that any property has been set in comment
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $comment Comment model to validate
	 * @return boolean returns TRUE if conform to requirements, FALSE otherwise
	 */
	protected function anyPropertyIsSet(Tx_PwComments_Domain_Model_Comment $comment) {
		return ($GLOBALS['TSFE']->fe_user->user['uid'])	|| ($comment->getAuthorName() !== '' && $comment->getAuthorMail() !== '');
	}

	/**
	 * Validator to check that mail is valid
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $comment Comment model to validate
	 * @return boolean returns TRUE if conform to requirements, FALSE otherwise
	 */
	protected function mailIsValid(Tx_PwComments_Domain_Model_Comment $comment) {
		if ($GLOBALS['TSFE']->fe_user->user['uid']) {
			return TRUE;
		}

		if(is_string($comment->getAuthorMail()) && preg_match('
				/
					^[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*
					@
					(?:
						(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+(?:[a-z]{2}|aero|asia|biz|cat|com|edu|coop|gov|info|int|invalid|jobs|localdomain|mil|mobi|museum|name|net|org|pro|tel|travel)|
						localhost|
						(?:(?:\d{1,2}|1\d{1,2}|2[0-5][0-5])\.){3}(?:(?:\d{1,2}|1\d{1,2}|2[0-5][0-5]))
					)
					\b
				/ix', $comment->getAuthorMail())) return TRUE;

 		return FALSE;
	}

	/**
	 * Validator to check that message has been set
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $comment Comment model to validate
	 * @return boolean returns TRUE if conform to requirements, FALSE otherwise
	 */
	protected function messageIsSet(Tx_PwComments_Domain_Model_Comment $comment) {
		return (trim($comment->getMessage()));
	}

	/**
	 * Check the time between last two comments of current user (using its session)
	 *
	 * @return boolean returns TRUE if conform to requirements, FALSE otherwise
	 */
	protected function lastCommentRespectsTimer() {
		if (!$GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_pwcomments_lastComment')) {
			return TRUE;
		}

		$difference = intval(time() - $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_pwcomments_lastComment'));

		if ($difference > $this->settings['secondsBetweenTwoComments']) {
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * Check for badwords in comment message
	 *
	 * @param Tx_PwComments_Domain_Model_Comment $comment the comment to check for
	 * @return boolean returns TRUE if message has no badwords
	 */
	protected function messageHasNoBadWords(Tx_PwComments_Domain_Model_Comment $comment){
		$badWordsListPath = t3lib_div::getFileAbsFileName($this->settings['badWordsList']);

		if (!file_exists($badWordsListPath)) {
			// Skip this validation, if bad word list is missing
			return TRUE;
		}

		$badWordsRegExp = '';
		foreach(file($badWordsListPath) as $badWord) {
			$badWordsRegExp .= trim($badWord) . '|';
		}
		$badWordsRegExp = '/' . substr($badWordsRegExp, 0, -1) . '/i';

		$commentMessage = '-> ' . $comment->getMessage() . ' <-';
		return (boolean)!preg_match($badWordsRegExp, $commentMessage);
	}


	/**
	 * Returns the rendered settings of this extension
	 *
	 * @return array rendered typoscript settings
	 */
	protected function getExtensionSettings() {
		$fullTyposcript = $this->configurationManager->getConfiguration(
			Tx_Extbase_Configuration_ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
		);
		$extensionTyposcript = $fullTyposcript['plugin.']['tx_pwcomments' . '.']['settings.'];
		return $this->settingsUtility->renderConfigurationArray($extensionTyposcript);
	}
}