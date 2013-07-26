<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$TCA['tx_pwcomments_domain_model_comment'] = array(
	'ctrl' => $TCA['tx_pwcomments_domain_model_comment']['ctrl'],
	'interface' => array(
		'showRecordFieldList'	=> 'hidden,author,author_name,author_mail,author_website,message,parent_comment'
	),
	'types' => array(
		'1' => array('showitem'	=> 'hidden,author,author_name,author_mail,author_website,message,parent_comment')
	),
	'palettes' => array(
		'1' => array('showitem'	=> '')
	),
	'columns' => array(
		'sys_language_uid' => array(
			'exclude'			=> 1,
			'label'				=> 'LLL:EXT:lang/locallang_general.php:LGL.language',
			'config'			=> array(
				'type'					=> 'select',
				'foreign_table'			=> 'sys_language',
				'foreign_table_where'	=> 'ORDER BY sys_language.title',
				'items' => array(
					array('LLL:EXT:lang/locallang_general.php:LGL.allLanguages', -1),
					array('LLL:EXT:lang/locallang_general.php:LGL.default_value', 0)
				)
			)
		),
		'l18n_parent' => array(
			'displayCond'	=> 'FIELD:sys_language_uid:>:0',
			'exclude'		=> 1,
			'label'			=> 'LLL:EXT:lang/locallang_general.php:LGL.l18n_parent',
			'config'		=> array(
				'type'			=> 'select',
				'items'			=> array(
					array('', 0),
				),
				'foreign_table' => 'tx_pwcomments_domain_model_comment',
				'foreign_table_where' => 'AND tx_pwcomments_domain_model_comment.uid=###REC_FIELD_l18n_parent### AND tx_pwcomments_domain_model_comment.sys_language_uid IN (-1,0)',
			)
		),
		'l18n_diffsource' => array(
			'config'		=>array(
				'type'		=>'passthrough'
			)
		),
		't3ver_label' => array(
			'displayCond'	=> 'FIELD:t3ver_label:REQ:true',
			'label'			=> 'LLL:EXT:lang/locallang_general.php:LGL.versionLabel',
			'config'		=> array(
				'type'		=>'none',
				'cols'		=> 27
			)
		),
		'pid' => array(
			'exclude'	=> 1,
			'label'		=> 'LLL:EXT:lang/locallang_general.xml:LGL.pid',
			'config'	=> array(
				'type' => 'input'
			)
		),
		'crdate' => array(
			'exclude'	=> 1,
			'label'		=> 'LLL:EXT:lang/locallang_general.xml:LGL.crdate',
			'config'	=> array(
				'type' => 'input'
			)
		),
		'hidden' => array(
			'exclude'	=> 1,
			'label'		=> 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'	=> array(
				'type'	=> 'check'
			)
		),
		'entry_uid' => array(
			'exclude'	=> 1,
			'label'		=> 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_comment.entry_uid',
			'config'	=> array(
				'type' => 'input'
			)
		),
		'parent_comment' => array(
			'exclude'	=> 1,
			'label'		=> 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_comment.parent_comment',
			'config'	=> array(
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'tx_pwcomments_domain_model_comment',
				'show_thumbs' => 1,
				'size' => 1,
				'minitems' => 0,
				'maxitems' => 1,
			)
		),
		'author' => array(
			'exclude'	=> 0,
			'label'		=> 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_comment.author',
			'config'  => array(
				'type' => 'select',
				'foreign_table' => 'fe_users',
				'maxitems' => 1,
				'items' => array(''),
			)
		),
		'author_name' => array(
			'exclude'	=> 0,
			'label'		=> 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_comment.author_name',
			'config'	=> array(
				'type' => 'input',
				'size' => 30,
				'eval' => 'trim'
			)
		),
		'author_mail' => array(
			'exclude'	=> 0,
			'label'		=> 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_comment.author_mail',
			'config'	=> array(
				'type' => 'input',
				'size' => 30,
				'eval' => 'trim'
			)
		),
		'message' => array(
			'exclude'	=> 0,
			'label'		=> 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:tx_pwcomments_domain_model_comment.message',
			'config'	=> array(
				'type' => 'text',
				'cols' => 30,
				'rows' => 10
			)
		),
	),
);
?>