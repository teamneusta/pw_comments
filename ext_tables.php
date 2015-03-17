<?php
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
