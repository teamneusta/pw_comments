<?php
/**
 * Definitions for middlewares provided by EXT:pw_comments
 */
return [
    'frontend' => [
        't3/pw-comments/frontend-handler' => [
            'target' => \T3\PwComments\Middleware\FrontendHandler::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute'
            ]
        ]
    ]
];
