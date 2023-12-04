<?php
declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'ext-pwcomments-type-vote_down' => [
        'provider' => BitmapIconProvider::class,
        'source' => 'EXT:pw_comments/Resources/Public/Icons/tx_pwcomments_domain_model_vote_down.png',
    ],
    'ext-pwcomments-type-vote_up' => [
        'provider' => BitmapIconProvider::class,
        'source' => 'EXT:pw_comments/Resources/Public/Icons/tx_pwcomments_domain_model_vote_up.png',
    ],
    'ext-pwcomments-ext-icon' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:pw_comments/Resources/Public/Icons/Extension.svg',
    ]
];
