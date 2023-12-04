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
        'title' => $ll . 'tx_pwcomments_domain_model_vote',
        'label' => 'crdate',
        'hideTable' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'origUid' => 't3_origuid',
        'readOnly' => true,
        'typeicon_column' => 'type',
        'typeicon_classes' => [
            '0' => 'ext-pwcomments-type-vote_down',
            '1' => 'ext-pwcomments-type-vote_up'
        ],
    ],
    'types' => [
        '1' => ['showitem' => 'type,crdate,author,author_ident']
    ],
    'palettes' => [
        '1' => ['showitem' => '']
    ],
    'columns' => [
        'pid' => [
            'exclude' => 0,
            'label' => $ll . 'general.pid',
            'config' => [
                'type' => 'input'
            ]
        ],
        'orig_pid' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_vote.orig_pid',
            'config' => [
                'type' => 'input'
            ]
        ],
        'crdate' => [
            'exclude' => 0,
            'label' => $ll . 'general.crdate',
            'config' => [
                'type' => 'datetime',
                'readOnly' => true,
            ]
        ],
        'type' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_vote.type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $ll . 'tx_pwcomments_domain_model_vote.type.0', 'value' => 0],
                    ['label' => $ll . 'tx_pwcomments_domain_model_vote.type.1', 'value' => 1]
                ],
                'readOnly' => true
            ]
        ],
        'author' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_vote.author',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'maxitems' => 1,
                'items' => [
                    ['label' => '', 'value' => 0]
                ],
                'readOnly' => true
            ]
        ],
        'author_ident' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_vote.author_ident',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'readOnly' => true
            ]
        ],
        'comment' => [
            'exclude' => 0,
            'label' => $ll . 'tx_pwcomments_domain_model_vote.comment',
            'config' => [
                'type' => 'group',
                'allowed' => 'tx_pwcomments_domain_model_comment',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                'readOnly' => true
            ]
        ]
    ]
];
