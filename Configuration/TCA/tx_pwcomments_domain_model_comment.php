<?php

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 */

if (!defined('TYPO3')) {
    die('Access denied.');
}

$ll = 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xlf:';

return [
    'ctrl' => [
        'title' => $ll . 'tx_pwcomments_domain_model_comment',
        'label' => 'author_name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'origUid' => 't3_origuid',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l18n_parent',
        'transOrigDiffSourceField' => 'l18n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden'
        ],
        'iconfile' => 'EXT:pw_comments/Resources/Public/Icons/tx_pwcomments_domain_model_comment.png'
    ],
    'types' => [
        '1' => ['showitem' => 'hidden,author,author_name,author_mail,author_website,author_ident,terms_accepted,' .
                              'message,parent_comment,votes,rating,' .
                              '--div--;AI Moderation,ai_moderation_status,ai_moderation_reason,ai_moderation_confidence,ai_moderation_control']
    ],
    'palettes' => [
        '1' => ['showitem' => '']
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 0,
            'label' => $ll . 'general.language',
            'config' => ['type' => 'language']
        ],
        'l18n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => $ll . 'general.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0]
                ],
                'foreign_table' => 'tx_pwcomments_domain_model_comment',
                'foreign_table_where' => 'AND tx_pwcomments_domain_model_comment.uid=###REC_FIELD_l18n_parent###' .
                    ' AND tx_pwcomments_domain_model_comment.sys_language_uid IN (-1,0)'
            ]
        ],
        'l18n_diffsource' => [
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        't3ver_label' => [
            'displayCond' => 'FIELD:t3ver_label:REQ:true',
            'label' => $ll . 'general.versionLabel',
            'config' => [
                'type' => 'none',
                'size' => 27
            ]
        ],
        'pid' => [
            'exclude' => 0,
            'label' => $ll . 'general.pid',
            'config' => [
                'type' => 'input'
            ]
        ],
        'crdate' => [
            'exclude' => 0,
            'label' => $ll . 'general.crdate',
            'config' => [
                'type' => 'datetime'
            ]
        ],
        'hidden' => [
            'exclude' => 0,
            'label' => $ll . 'general.hidden',
            'config' => [
                'type' => 'check'
            ]
        ],
        'orig_pid' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.orig_pid',
            'config' => [
                'type' => 'input'
            ]
        ],
        'entry_uid' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.entry_uid',
            'config' => [
                'type' => 'input'
            ]
        ],
        'parent_comment' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.parent_comment',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_pwcomments_domain_model_comment',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1
            ]
        ],
        'author' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.author',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'maxitems' => 1,
                'items' => [
                    ['label' => '', 'value' => 0]
                ],
            ]
        ],
        'author_name' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.author_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ]
        ],
        'author_mail' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.author_mail',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ]
        ],
        'author_ident' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.author_ident',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim'
            ]
        ],
        'message' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.message',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 10
            ]
        ],
        'votes' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.votes',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_pwcomments_domain_model_vote',
                'foreign_field' => 'comment',
                'size' => 10,
                'autoSizeMax' => 30,
                'maxitems' => 9999,
                'behaviour' => [
                    'enableCascadingDelete' => true
                ],
                'appearance' => [
                    'collapseAll' => true,
                    'newRecordLinkPosition' => 'none',
                    'showNewRecordLink' => false,
                    'useSortable' => false,
                    'enabledControls' => [
                        'new' => false,
                        'dragdrop' => false,
                        'sort' => false,
                        'hide' => false,
                        'delete' => false
                    ]
                ]
            ]
        ],
        'terms_accepted' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.terms_accepted',
            'config' => [
                'type' => 'check'
            ]
        ],
        'rating' => [
            'exclude' => 0,
            'label' => 'rating',
            'displayCond' => 'USER:T3\\PwComments\\UserFunc\\TCA\\DisplayCondition->isRatingEnabled',
            'config' => [
                'type' => 'input'
            ]
        ],
        'ai_moderation_status' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.ai_moderation_status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => ''],
                    ['label' => 'Approved', 'value' => 'approved'],
                    ['label' => 'Flagged', 'value' => 'flagged'],
                    ['label' => 'Error', 'value' => 'error'],
                ],
                'readOnly' => true,
            ]
        ],
        'ai_moderation_reason' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.ai_moderation_reason',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 3,
                'readOnly' => true,
            ]
        ],
        'ai_moderation_confidence' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.ai_moderation_confidence',
            'config' => [
                'type' => 'input',
                'eval' => 'double2',
                'range' => [
                    'lower' => 0,
                    'upper' => 1
                ],
                'readOnly' => true,
            ]
        ],
        'ai_moderation_control' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_comment.ai_moderation_control',
            'config' => [
                'type' => 'user',
                'renderType' => 'aiModerationControl',
            ]
        ],
    ]
];
