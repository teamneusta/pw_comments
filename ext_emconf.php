<?php

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2018 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'pwComments',
    'description' => 'Powerful extension for providing comments, including replies on comments and voting.',
    'category' => 'plugin',
    'author' => 'Armin Vieweg',
    'author_email' => 'armin@v.ieweg.de',
    'author_company' => '',
    'state' => 'stable',
    'uploadfolder' => false,
    'createDirs' => null,
    'modify_tables' => '',
    'clearCacheOnLoad' => false,
    'version' => '5.0.0-dev',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-9.5.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ]
    ]
];
