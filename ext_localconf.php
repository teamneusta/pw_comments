<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$_EXTKEY = 'pw_comments';

Tx_Extbase_Utility_Extension::configurePlugin(
	$_EXTKEY,
	'Pi1',
	array(
		'Comment' => 'index,new,create,upvote,downvote',
	),
	array(
		'Comment' => 'index,new,create,upvote,downvote',
	)
);

Tx_Extbase_Utility_Extension::configurePlugin(
	$_EXTKEY,
	'Pi2',
	array(
		'Comment' => 'sendAuthorMailWhenCommentHasBeenApproved',
	),
	array(
		'Comment' => 'sendAuthorMailWhenCommentHasBeenApproved',
	)
);

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['PwComments']['modules'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['PwComments']['plugins'];

	// After save hook
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
	'EXT:' . $_EXTKEY . '/Classes/Hooks/ProcessDatamap.php:Tx_PwComments_Hooks_ProcessDatamap';