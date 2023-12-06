<?php
declare(strict_types=1);

use T3\PwComments\Hooks\ProcessDatamap;
use TYPO3\CMS\Backend\Controller\PageLayoutController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use T3\PwComments\Controller\CommentController;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use T3\PwComments\Event\Listener\PageLayoutView;
/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 *  |     2023 Malek Olabi <m.olabi@neusta.de>
 */

if (!defined('TYPO3')) {
    die('Access denied.');
}

(static function ($extensionKey): void {
    ExtensionUtility::configurePlugin(
        $extensionKey,
        'show',
        [
            CommentController::class => 'index,upvote,downvote',
        ],
        [
            CommentController::class => 'index,upvote,downvote',
        ]
    );
    ExtensionUtility::configurePlugin(
        $extensionKey,
        'new',
        [
            CommentController::class => 'new,create,confirmComment',
        ],
        [
            CommentController::class => 'new,create,confirmComment',
        ]
    );

    ExtensionUtility::configurePlugin(
        $extensionKey,
        'Pi2',
        [
            CommentController::class => 'sendAuthorMailWhenCommentHasBeenApproved',
        ],
        [
            CommentController::class => 'sendAuthorMailWhenCommentHasBeenApproved',
        ]
    );

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['PwComments']['modules']
        = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['PwComments']['plugins'];

    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] = array_merge(
        $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'],
        [
            'tx_pwcomments_show',
            'tx_pwcomments_show[controller]',
            'tx_pwcomments_show[action]',
            'tx_pwcomments_show[comment]',
            'tx_pwcomments_show[commentToReplyTo]',
            'tx_pwcomments_show[hash]',
            'tx_pwcomments_show[__referrer][@extension]',
            'tx_pwcomments_show[__referrer][@vendor]',
            'tx_pwcomments_show[__referrer][@controller]',
            'tx_pwcomments_show[__referrer][@action]',
            'tx_pwcomments_show[__referrer][arguments]',
            'tx_pwcomments_show[__referrer][@request]',
            'tx_pwcomments_show[__trustedProperties]',
            'tx_pwcomments_show[newComment][authorName]',
            'tx_pwcomments_show[newComment][authorMail]',
            'tx_pwcomments_show[authorWebsite]',
            'tx_pwcomments_show[newComment][message]',
            'tx_pwcomments_show[newComment][parentComment][__identity]',
            'tx_pwcomments_new',
            'tx_pwcomments_new[controller]',
            'tx_pwcomments_new[action]',
            'tx_pwcomments_new[comment]',
            'tx_pwcomments_new[commentToReplyTo]',
            'tx_pwcomments_new[hash]',
            'tx_pwcomments_new[__referrer][@extension]',
            'tx_pwcomments_new[__referrer][@vendor]',
            'tx_pwcomments_new[__referrer][@controller]',
            'tx_pwcomments_new[__referrer][@action]',
            'tx_pwcomments_new[__referrer][arguments]',
            'tx_pwcomments_new[__referrer][@request]',
            'tx_pwcomments_new[__trustedProperties]',
            'tx_pwcomments_new[newComment][authorName]',
            'tx_pwcomments_new[newComment][authorMail]',
            'tx_pwcomments_new[authorWebsite]',
            'tx_pwcomments_new[newComment][message]',
            'tx_pwcomments_new[newComment][parentComment][__identity]'
        ]
    );

    // After save hook
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
        ProcessDatamap::class;
})('pw_comments');
