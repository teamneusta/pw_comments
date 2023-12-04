<?php

use T3\PwComments\Middleware\FrontendHandler;
/**
 * Definitions for middlewares provided by EXT:pw_comments
 */
return [
    'frontend' => [
        't3/pw-comments/frontend-handler' => [
            'target' => FrontendHandler::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute'
            ]
        ]
    ]
];
