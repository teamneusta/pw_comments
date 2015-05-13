<?php

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2015 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 */

if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$boot = function($extensionKey) {
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($extensionKey, 'Configuration/TypoScript', 'pwComments');

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_pwcomments_domain_model_comment');
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToInsertRecords('tx_pwcomments_domain_model_comment');

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_pwcomments_domain_model_vote');
};

$boot($_EXTKEY);
unset($boot);