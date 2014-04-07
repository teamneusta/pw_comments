<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "pw_comments".
 *
 * Auto generated 07-04-2014 13:16
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array(
	'title' => 'pwComments',
	'description' => 'Powerful extension for providing comments, including replies on comments and voting.',
	'category' => 'plugin',
	'author' => 'Armin Ruediger Vieweg',
	'author_email' => 'armin@v.ieweg.de',
	'author_company' => '',
	'shy' => '',
	'dependencies' => '',
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
	'version' => '2.0.1',
	'constraints' => array(
		'depends' => array(
			'typo3' => '4.5.0-6.2.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
	'_md5_values_when_last_written' => 'a:46:{s:12:"ext_icon.gif";s:4:"8414";s:17:"ext_localconf.php";s:4:"9f8c";s:14:"ext_tables.php";s:4:"6a1a";s:14:"ext_tables.sql";s:4:"9cff";s:24:"ext_typoscript_setup.txt";s:4:"89f6";s:40:"Classes/Controller/CommentController.php";s:4:"aad5";s:32:"Classes/Domain/Model/Comment.php";s:4:"a7f3";s:37:"Classes/Domain/Model/FrontendUser.php";s:4:"c5a1";s:29:"Classes/Domain/Model/Vote.php";s:4:"71d4";s:47:"Classes/Domain/Repository/CommentRepository.php";s:4:"68ba";s:52:"Classes/Domain/Repository/FrontendUserRepository.php";s:4:"07a7";s:44:"Classes/Domain/Repository/VoteRepository.php";s:4:"8240";s:45:"Classes/Domain/Validator/CommentValidator.php";s:4:"bde1";s:32:"Classes/Hooks/ProcessDatamap.php";s:4:"4ac7";s:26:"Classes/Utility/Cookie.php";s:4:"c8ad";s:24:"Classes/Utility/Mail.php";s:4:"3c66";s:28:"Classes/Utility/Settings.php";s:4:"8b5a";s:45:"Classes/ViewHelpers/ArrayUniqueViewHelper.php";s:4:"8622";s:47:"Classes/ViewHelpers/FlashMessagesViewHelper.php";s:4:"9e00";s:42:"Classes/ViewHelpers/GravatarViewHelper.php";s:4:"9f50";s:41:"Classes/ViewHelpers/InArrayViewHelper.php";s:4:"4723";s:38:"Classes/ViewHelpers/Is62ViewHelper.php";s:4:"a888";s:45:"Classes/ViewHelpers/UserInGroupViewHelper.php";s:4:"fe9b";s:45:"Classes/ViewHelpers/Format/DateViewHelper.php";s:4:"4c1d";s:44:"Classes/ViewHelpers/Format/RawViewHelper.php";s:4:"4451";s:53:"Classes/ViewHelpers/Format/RelativeDateViewHelper.php";s:4:"4fc7";s:29:"Configuration/TCA/Comment.php";s:4:"6c2a";s:26:"Configuration/TCA/Vote.php";s:4:"c978";s:34:"Configuration/TypoScript/setup.txt";s:4:"d799";s:39:"Resources/Private/Language/badwords.txt";s:4:"b0b7";s:40:"Resources/Private/Language/locallang.xml";s:4:"5727";s:43:"Resources/Private/Language/locallang_db.xml";s:4:"5e80";s:38:"Resources/Private/Layouts/Default.html";s:4:"9da8";s:39:"Resources/Private/Partials/Comment.html";s:4:"99c5";s:46:"Resources/Private/Partials/Comment/Voting.html";s:4:"0773";s:57:"Resources/Private/Partials/Comment/FormErrors/Errors.html";s:4:"f876";s:68:"Resources/Private/Partials/Comment/FormErrors/ValidationResults.html";s:4:"e20b";s:46:"Resources/Private/Templates/Comment/Index.html";s:4:"95f3";s:44:"Resources/Private/Templates/Comment/New.html";s:4:"e3b3";s:54:"Resources/Private/Templates/MailNotification/mail.html";s:4:"4276";s:74:"Resources/Private/Templates/MailNotification/mailToAuthorAfterPublish.html";s:4:"8381";s:73:"Resources/Private/Templates/MailNotification/mailToAuthorAfterSubmit.html";s:4:"2b63";s:61:"Resources/Public/Icons/tx_pwcomments_domain_model_comment.gif";s:4:"f59d";s:63:"Resources/Public/Icons/tx_pwcomments_domain_model_vote_down.gif";s:4:"21d6";s:61:"Resources/Public/Icons/tx_pwcomments_domain_model_vote_up.gif";s:4:"3d2a";s:14:"doc/manual.sxw";s:4:"e810";}',
);

?>