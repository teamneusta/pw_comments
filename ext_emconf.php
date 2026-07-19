<?php

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 *  |     2023-2026 Malek Olabi <m.olabi@neusta.de>
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Comments for TYPO3 CMS',
    'description' => 'Powerful extension for providing comments, including replies on comments and voting.',
    'category' => 'plugin',
    'author' => 'Malek Olabi',
    'author_email' => 'm.olabi@neusta.de',
    'author_company' => '',
    'state' => 'stable',
    'version' => '8.0.1',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '14.3.0-14.3.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ]
    ]
];
