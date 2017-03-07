<?php

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2016 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016 Christian Wolfram <c.wolfram@chriwo.de>
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
    'version' => '4.0.0-dev',
    'constraints' => [
        'depends' => [
            'typo3' => '7.6.0-8.9.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ]
    ]
];
