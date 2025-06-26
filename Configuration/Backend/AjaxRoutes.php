<?php

use T3\PwComments\UserFunc\TCA\AiModerationControl;

return [
    'ajax_pwcomments_recheck_moderation' => [
        'path' => '/ajax/pwcomments/recheck-moderation',
        'target' => AiModerationControl::class . '::recheckModeration'
    ],
];
