<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2013 Armin Rüdiger Vieweg <armin@v.ieweg.de>
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
 * processDatamap Hook
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Tx_PwComments_Hooks_ProcessDatamap {
	/** @var array */
	protected $enabledTables = array('tx_pwcomments_domain_model_comment');

	/** @var array */
	protected $enabledStatus = array('update');

	/**
	 * After Save hook
	 *
	 * @param $status
	 * @param $table
	 * @param $id
	 * @param array $fieldArray
	 * @param t3lib_TCEmain $pObj
	 *
	 * @return void
	 */
	public function processDatamap_postProcessFieldArray($status, $table, $id, $fieldArray, $pObj) {
		if (in_array($table, $this->enabledTables) && in_array($status, $this->enabledStatus)) {
			if (isset($fieldArray['hidden']) && $fieldArray['hidden'] == 0) {
				$row = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'tx_pwcomments_domain_model_comment', 'uid=' . $id);

				$_POST['tx_pwcomments_pi2']['action'] = 'sendAuthorMailWhenCommentHasBeenApproved';
				$_POST['tx_pw_comments_pi2']['action'] = 'sendAuthorMailWhenCommentHasBeenApproved';
				$this->runExtbaseController('PwComments', 'Comment', 'sendAuthorMailWhenCommentHasBeenApproved', 'Pi2', array('_commentUid' => $row['uid'], '_skipMakingSettingsRenderable' => TRUE), $row['pid']);
			}
		}
	}



	/**
	 * Initializes and runs an extbase controller
	 *
	 * @param string $extensionName Name of extension, in UpperCamelCase
	 * @param string $controller Name of controller, in UpperCamelCase
	 * @param string $action Optional name of action, in lowerCamelCase (without 'Action' suffix). Default is 'index'.
	 * @param string $pluginName Optional name of plugin. Default is 'Pi1'.
	 * @param array $settings Optional array of settings to use in controller and fluid template. Default is array().
	 * @param integer $pageUid Uid of current page
	 *
	 * @return string output of controller's action
	 */
	protected function runExtbaseController($extensionName, $controller, $action = 'index', $pluginName = 'Pi1', $settings = array(), $pageUid = 0) {
		$GLOBALS['TT'] = t3lib_div::makeInstance('t3lib_timeTrack');
		$GLOBALS['TSFE'] = t3lib_div::makeInstance('tslib_fe', $GLOBALS['TYPO3_CONF_VARS'], $pageUid, 0);
		$GLOBALS['TSFE']->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
		$GLOBALS['TSFE']->initTemplate();
		$rootline = $GLOBALS['TSFE']->sys_page->getRootLine($pageUid);
		$GLOBALS['TSFE']->tmpl->start($rootline);
		$GLOBALS['TSFE']->getConfigArray();

		$pluginSettings = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_pwcomments.'];
		$pwCommentsTypoScript = $pluginSettings['settings.'];

		tslib_eidtools::connectDB();
		tslib_eidtools::initLanguage('de');
		tslib_eidtools::initTCA();
		tslib_eidtools::initExtensionTCA('pw_comments');

		if (unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pw_comments'])) {
			$settings = array_merge($settings, unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pw_comments']));
		}
		$settings = array_merge($settings, $pwCommentsTypoScript);

		$bootstrap = new Tx_Extbase_Core_Bootstrap();
		$bootstrap->cObj = t3lib_div::makeInstance('tslib_cObj');

		$extensionTyposcriptSetup = $this->getExtensionTyposcriptSetup();

		$localLangArray = array();
		if (is_array($pluginSettings['_LOCAL_LANG.'])) {
			if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) < 6000000) {
				$localLangArray = Tx_Extbase_Utility_TypoScript::convertTypoScriptArrayToPlainArray($pluginSettings['_LOCAL_LANG.']);
			} else {
				$typoScriptService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Service\TypoScriptService');
				$localLangArray = $typoScriptService->convertTypoScriptArrayToPlainArray($pluginSettings['_LOCAL_LANG.']);
			}
		}
		$configuration = array(
			'pluginName' => $pluginName,
			'extensionName' => $extensionName,
			'controller' => $controller,
			'controllerConfiguration' => array($controller),
			'action' => $action,
			'mvc' => array('requestHandlers' => array('Tx_Extbase_MVC_Web_FrontendRequestHandler'=>'Tx_Extbase_MVC_Web_FrontendRequestHandler')),
			'settings' => $settings,
			'persistence' => $extensionTyposcriptSetup['plugin']['tx_pwcomments']['persistence'],
			'_LOCAL_LANG' => $localLangArray
		);

		return $bootstrap->run('', $configuration);
	}

	/**
	 * Gets the typoscript setup defined in ext_typoscript_setup.txt as array
	 * @return array
	 */
	protected function getExtensionTyposcriptSetup() {
		/** @var $TSparser t3lib_TSparser */
		$TSparser = t3lib_div::makeInstance('t3lib_TSparser');
		$TSparser->parse(file_get_contents(t3lib_extMgm::extPath('pw_comments') . 'ext_typoscript_setup.txt'));
		if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) < 6000000) {
			return Tx_Extbase_Utility_TypoScript::convertTypoScriptArrayToPlainArray($TSparser->setup);
		}
		$typoScriptService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Service\TypoScriptService');
		return $typoScriptService->convertTypoScriptArrayToPlainArray($TSparser->setup);
	}

}
?>