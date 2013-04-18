<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "pw_comments".
 *
 * Auto generated 18-04-2013 14:17
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array(
	'title' => 'pwComments',
	'description' => 'Simple and powerful extension for providing comments.',
	'category' => 'plugin',
	'author' => 'Armin Ruediger Vieweg',
	'author_email' => 'armin@v.ieweg.de',
	'author_company' => '',
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
	'version' => '1.2.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '4.5.0-6.0.99',
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
	'_md5_values_when_last_written' => 'a:31:{s:12:"ext_icon.gif";s:4:"8414";s:17:"ext_localconf.php";s:4:"27f2";s:14:"ext_tables.php";s:4:"d4f8";s:14:"ext_tables.sql";s:4:"f5f7";s:24:"ext_typoscript_setup.txt";s:4:"89f6";s:40:"Classes/Controller/CommentController.php";s:4:"463a";s:32:"Classes/Domain/Model/Comment.php";s:4:"f4aa";s:37:"Classes/Domain/Model/FrontendUser.php";s:4:"a579";s:47:"Classes/Domain/Repository/CommentRepository.php";s:4:"6901";s:52:"Classes/Domain/Repository/FrontendUserRepository.php";s:4:"c42c";s:45:"Classes/Domain/Validator/CommentValidator.php";s:4:"9612";s:24:"Classes/Utility/Mail.php";s:4:"7775";s:28:"Classes/Utility/Settings.php";s:4:"e7ea";s:47:"Classes/ViewHelpers/FlashMessagesViewHelper.php";s:4:"cdb3";s:42:"Classes/ViewHelpers/GravatarViewHelper.php";s:4:"0509";s:45:"Classes/ViewHelpers/UserInGroupViewHelper.php";s:4:"4652";s:45:"Classes/ViewHelpers/Format/DateViewHelper.php";s:4:"0563";s:44:"Classes/ViewHelpers/Format/RawViewHelper.php";s:4:"4451";s:53:"Classes/ViewHelpers/Format/RelativeDateViewHelper.php";s:4:"344e";s:29:"Configuration/TCA/Comment.php";s:4:"d2d2";s:34:"Configuration/TypoScript/setup.txt";s:4:"3cec";s:39:"Resources/Private/Language/badwords.txt";s:4:"b0b7";s:40:"Resources/Private/Language/locallang.xml";s:4:"0e80";s:43:"Resources/Private/Language/locallang_db.xml";s:4:"0db0";s:38:"Resources/Private/Layouts/Default.html";s:4:"9da8";s:42:"Resources/Private/Partials/formErrors.html";s:4:"f5bc";s:46:"Resources/Private/Templates/Comment/Index.html";s:4:"eb9b";s:44:"Resources/Private/Templates/Comment/New.html";s:4:"b30a";s:54:"Resources/Private/Templates/MailNotification/mail.html";s:4:"4276";s:61:"Resources/Public/Icons/tx_pwcomments_domain_model_comment.gif";s:4:"f59d";s:14:"doc/manual.sxw";s:4:"e810";}',
);

?>