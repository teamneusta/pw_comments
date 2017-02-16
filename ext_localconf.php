<?php

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2016 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016 Christian Wolfram <c.wolfram@chriwo.de>
 */

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$boot = function ($extensionKey) {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'PwCommentsTeam.' . $extensionKey,
        'Pi1',
        [
            'Comment' => 'index,new,create,upvote,downvote,confirmComment',
        ],
        [
            'Comment' => 'index,new,create,upvote,downvote,confirmComment',
        ]
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'PwCommentsTeam.' . $extensionKey,
        'Pi2',
        [
            'Comment' => 'sendAuthorMailWhenCommentHasBeenApproved',
        ],
        [
            'Comment' => 'sendAuthorMailWhenCommentHasBeenApproved',
        ]
    );

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['PwComments']['modules']
        = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['PwComments']['plugins'];

        // After save hook
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
        'PwCommentsTeam\PwComments\Hooks\ProcessDatamap';

    if (TYPO3_MODE === 'BE') {
        /** @var \TYPO3\CMS\Core\Imaging\IconRegistry $iconRegistry */
        $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Imaging\IconRegistry::class
        );
        $iconRegistry->registerIcon(
            'ext-pwcomments-type-vote_down',
            \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
            ['source' => 'EXT:pw_comments/Resources/Public/Icons/tx_pwcomments_domain_model_vote_down.png']
        );

        $iconRegistry->registerIcon(
            'ext-pwcomments-type-vote_up',
            \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
            ['source' => 'EXT:pw_comments/Resources/Public/Icons/tx_pwcomments_domain_model_vote_up.png']
        );
    }
};

$boot($_EXTKEY);
unset($boot);
