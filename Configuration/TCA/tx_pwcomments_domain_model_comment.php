<?php

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2015 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 */

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$_EXTKEY = 'pw_comments';
$ll = 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xml:';
$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY);

return array(
    'ctrl' => array(
        'title' => $ll . 'tx_pwcomments_domain_model_comment',
        'label' => 'author_name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => 2,
        'versioning_followPages' => true,
        'origUid' => 't3_origuid',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l18n_parent',
        'transOrigDiffSourceField' => 'l18n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => array(
            'disabled' => 'hidden'
        ),
        'iconfile' => $extensionPath . 'Resources/Public/Icons/tx_pwcomments_domain_model_comment.png'
    ),
    'interface' => array(
        'showRecordFieldList' => 'hidden,author,author_name,author_mail,author_website,author_ident,message,'
                                . 'parent_comment,votes'
    ),
    'types' => array(
        '1' => array('showitem' => 'hidden,author,author_name,author_mail,author_website,author_ident,message,'
                                . 'parent_comment,votes')
    ),
    'palettes' => array(
        '1' => array('showitem' => '')
    ),
    'columns' => array(
        'sys_language_uid' => array(
            'exclude' => 0,
            'label' => $ll . 'general.language',
            'config' => array(
                'type' => 'select',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => array(
                    array('LLL:EXT:lang/locallang_general.php:LGL.allLanguages', -1),
                    array('LLL:EXT:lang/locallang_general.php:LGL.default_value', 0)
                )
            )
        ),
        'l18n_parent' => array(
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => 0,
            'label' => $ll . 'general.l18n_parent',
            'config' => array(
                'type' => 'select',
                'items' => array(
                    array('', 0)
                ),
                'foreign_table' => 'tx_pwcomments_domain_model_comment',
                'foreign_table_where' => 'AND tx_pwcomments_domain_model_comment.uid=###REC_FIELD_l18n_parent###' .
                    ' AND tx_pwcomments_domain_model_comment.sys_language_uid IN (-1,0)'
            )
        ),
        'l18n_diffsource' => array(
            'config' => array(
                'type' => 'passthrough'
            )
        ),
        't3ver_label' => array(
            'displayCond' => 'FIELD:t3ver_label:REQ:true',
            'label' => $ll . 'general.versionLabel',
            'config' => array(
                'type' => 'none',
                'cols' => 27
            )
        ),
        'pid' => array(
            'exclude' => 0,
            'label' => $ll . 'general.pid',
            'config' => array(
                'type' => 'input'
            )
        ),
        'crdate' => array(
            'exclude' => 0,
            'label' => $ll . 'general.crdate',
            'config' => array(
                'type' => 'input'
            )
        ),
        'hidden' => array(
            'exclude' => 0,
            'label' => $ll . 'general.hidden',
            'config' => array(
                'type' => 'check'
            )
        ),
        'entry_uid' => array(
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.entry_uid',
            'config' => array(
                'type' => 'input'
            )
        ),
        'parent_comment' => array(
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.parent_comment',
            'config' => array(
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tx_pwcomments_domain_model_comment',
                'show_thumbs' => 1,
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1
            )
        ),
        'author' => array(
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.author',
            'config' => array(
                'type' => 'select',
                'foreign_table' => 'fe_users',
                'maxitems' => 1,
                'items' => array('')
            )
        ),
        'author_name' => array(
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.author_name',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            )
        ),
        'author_mail' => array(
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.author_mail',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            )
        ),
        'author_ident' => array(
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.author_ident',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            )
        ),
        'message' => array(
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.message',
            'config' => array(
                'type' => 'text',
                'cols' => 30,
                'rows' => 10
            )
        ),
        'votes' => array(
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.votes',
            'config' => array(
                'type' => 'inline',
                'foreign_table' => 'tx_pwcomments_domain_model_vote',
                'MM' => 'tx_pwcomments_comment_vote_mm',
                'size' => 10,
                'autoSizeMax' => 30,
                'maxitems' => 9999,
                'behaviour' => array(
                    'enableCascadingDelete' => true
                ),
                'appearance' => array(
                    'collapseAll' => true,
                    'newRecordLinkPosition' => 'none',
                    'levelLinksPosition' => 'none',
                    'useSortable' => false,
                    'enabledControls' => array(
                        'new' => false,
                        'dragdrop' => false,
                        'sort' => false,
                        'hide' => false,
                        'delete' => false
                    )
                )
            )
        )
    )
);
