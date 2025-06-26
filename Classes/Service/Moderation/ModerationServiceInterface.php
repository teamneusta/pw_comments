<?php

declare(strict_types=1);

namespace T3\PwComments\Service\Moderation;

use T3\PwComments\Domain\Model\Comment;

interface ModerationServiceInterface
{
    public function moderateComment(Comment $comment): ModerationResult;
}