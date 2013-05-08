<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$_EXTKEY = 'pw_comments';

t3lib_extMgm::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'pwComments');

$extensionName = t3lib_div::underscoredToUpperCamelCase($_EXTKEY);
$pluginSignature = strtolower($extensionName) . '_pi1';

t3lib_extMgm::addLLrefForTCAdescr('tx_pwcomments_domain_model_comments', 'EXT:pw_comments/Resources/Private/Language/locallang_csh_tx_pwcomments_domain_model_comment.xml');
t3lib_extMgm::allowTableOnStandardPages('tx_pwcomments_domain_model_comment');
t3lib_extMgm::addToInsertRecords('tx_pwcomments_domain_model_comment');

$TCA['tx_pwcomments_domain_model_comment'] = array(
	'ctrl' => array(
		'title'						=> 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_comment',
		'label'						=> 'author_name',
		'tstamp'					=> 'tstamp',
		'crdate'					=> 'crdate',
		'versioningWS'				=> 2,
		'versioning_followPages'	=> TRUE,
		'origUid'					=> 't3_origuid',
		'languageField'				=> 'sys_language_uid',
		'transOrigPointerField'		=> 'l18n_parent',
		'transOrigDiffSourceField'	=> 'l18n_diffsource',
		'delete'					=> 'deleted',
		'enablecolumns'				=> array(
			'disabled'		=> 'hidden'
		),
		'dynamicConfigFile'			=> t3lib_extMgm::extPath($_EXTKEY) . 'Configuration/TCA/Comment.php',
		'iconfile'					=> t3lib_extMgm::extRelPath($_EXTKEY) . 'Resources/Public/Icons/tx_pwcomments_domain_model_comment.gif'
	)
);
?>