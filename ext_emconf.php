<?php

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Comments for TYPO3 CMS',
    'description' => 'Powerful extension for providing comments, including replies on comments and voting.',
    'category' => 'plugin',
    'author' => 'Armin Vieweg',
    'author_email' => 'armin@v.ieweg.de',
    'author_company' => '',
    'state' => 'stable',
    'version' => '6.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.99.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ]
    ]
];
