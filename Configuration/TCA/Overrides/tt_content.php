<?php
declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

(static function (string $extensionKey): void {
    ExtensionUtility::registerPlugin(
        $extensionKey,
        'show',
        'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xlf:plugin.title.show',
        null,
        'Comments'
    );
    ExtensionUtility::registerPlugin(
        $extensionKey,
        'new',
        'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xlf:plugin.title.new',
        null,
        'Comments'
    );


    // Add typoscript static includes
    ExtensionManagementUtility::addStaticFile(
        $extensionKey,
        'Configuration/TypoScript',
        'pw_comments Main Static Template (required)'
    );
    ExtensionManagementUtility::addStaticFile(
        $extensionKey,
        'Configuration/TypoScript/Styling',
        'pw_comments Optional Styles'
    );

    ExtensionManagementUtility::addToInsertRecords('tx_pwcomments_domain_model_comment');
})('pw_comments');
