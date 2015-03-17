<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

$_EXTKEY = 'pw_comments';

return array(
	'ctrl' => array(
		'title' => 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_vote',
		'label' => 'crdate',
		'hideTable' => TRUE,
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'origUid' => 't3_origuid',
		'readOnly' => TRUE,
		'typeicon_column' => 'type',
		'typeicons' => array(
			'0' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'Resources/Public/Icons/tx_pwcomments_domain_model_vote_down.gif',
			'1' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'Resources/Public/Icons/tx_pwcomments_domain_model_vote_up.gif',
		),
	),
	'interface' => array(
		'showRecordFieldList'	=> 'type,crdate,author,author_ident'
	),
	'types' => array(
		'1' => array('showitem'	=> 'type,crdate,author,author_ident')
	),
	'palettes' => array(
		'1' => array('showitem'	=> '')
	),
	'columns' => array(
		'pid' => array(
			'exclude'	=> 0,
			'label'		=> 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:general.pid',
			'config'	=> array(
				'type' => 'input'
			)
		),
		'crdate' => array(
			'exclude'	=> 0,
			'label'		=> 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:general.crdate',
			'config'	=> array(
				'type' => 'input',
				'eval' => 'datetime',
				'readOnly' => TRUE
			)
		),
		'type' => array(
			'exclude'	=> 0,
			'label'		=> 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_vote.type',
			'config'	=> array(
				'type' => 'select',
				'items' => array(
					array('LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_vote.type.0', 0),
					array('LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_vote.type.1', 1),
				),
				'readOnly' => TRUE
			)
		),
		'author' => array(
			'exclude'	=> 0,
			'label'		=> 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_vote.author',
			'config'  => array(
				'type' => 'select',
				'foreign_table' => 'fe_users',
				'maxitems' => 1,
				'items' => array(''),
				'readOnly' => TRUE
			)
		),
		'author_ident' => array(
			'exclude'	=> 0,
			'label'		=> 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_vote.author_ident',
			'config'	=> array(
				'type' => 'input',
				'size' => 30,
				'eval' => 'trim',
				'readOnly' => TRUE
			)
		),
		'comment' => array(
			'exclude' => 0,
			'label' => 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_vote.comment',
			'config' => array(
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'tx_pwcomments_domain_model_comment',
				'show_thumbs' => 1,
				'size' => 1,
				'minitems' => 0,
				'maxitems' => 1,
				'readOnly' => TRUE
			)
		),
	),
);