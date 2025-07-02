<?php

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 *  |     2023-2024 Malek Olabi <m.olabi@neusta.de>
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Comments for TYPO3 CMS',
    'description' => 'Powerful extension for providing comments, including replies on comments and voting.',
    'category' => 'plugin',
    'author' => 'Malek Olabi',
    'author_email' => 'm.olabi@neusta.de',
    'author_company' => '',
    'state' => 'stable',
    'version' => '7.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99'
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ]
    ]
];
