<?php

########################################################################
# Extension Manager/Repository config file for ext "pw_comments".
#
# Auto generated 26-07-2011 17:06
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'pwComments',
	'description' => 'Simple and powerful extension for providing comments.',
	'category' => 'plugin',
	'author' => 'Armin Ruediger Vieweg',
	'author_email' => 'info@professorweb.de',
	'author_company' => 'Professor Web - Webdesign Blog',
	'shy' => '',
	'dependencies' => 'extbase,fluid',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'version' => '1.0.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '4.5.0-0.0.0',
			'extbase' => '1.3.0-0.0.0',
			'fluid' => '1.3.0-0.0.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
	'_md5_values_when_last_written' => 'a:20:{s:12:"ext_icon.gif";s:4:"c590";s:17:"ext_localconf.php";s:4:"a5e2";s:14:"ext_tables.php";s:4:"d4f8";s:14:"ext_tables.sql";s:4:"b570";s:40:"Classes/Controller/CommentController.php";s:4:"4a91";s:32:"Classes/Domain/Model/Comment.php";s:4:"257c";s:47:"Classes/Domain/Repository/CommentRepository.php";s:4:"4d96";s:45:"Classes/Domain/Validator/CommentValidator.php";s:4:"38fd";s:39:"Classes/Hooks/class.pw_teaser_hooks.php";s:4:"f7f0";s:45:"Classes/ViewHelpers/UserInGroupViewHelper.php";s:4:"4652";s:29:"Configuration/TCA/Comment.php";s:4:"0972";s:34:"Configuration/TypoScript/setup.txt";s:4:"7187";s:40:"Resources/Private/Language/locallang.xml";s:4:"2e25";s:43:"Resources/Private/Language/locallang_db.xml";s:4:"12a6";s:38:"Resources/Private/Layouts/default.html";s:4:"9da8";s:42:"Resources/Private/Partials/formErrors.html";s:4:"f5bc";s:46:"Resources/Private/Templates/Comment/Index.html";s:4:"7b2c";s:44:"Resources/Private/Templates/Comment/New.html";s:4:"e3d4";s:35:"Resources/Public/Icons/relation.gif";s:4:"e615";s:61:"Resources/Public/Icons/tx_pwcomments_domain_model_comment.gif";s:4:"905a";}',
);

?>