<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use T3\PwComments\Domain\Model\Comment;

final class CommentTest extends TestCase
{
    /**
     * Regression test for #65: creating a comment as a logged-in frontend user
     * never calls setCrdate(), and setHidden() only runs on the moderation
     * branch. The typed properties $crdate and $hidden must therefore carry
     * defaults, otherwise reading them during persistence throws
     * "Typed property ...::$crdate must not be accessed before initialization".
     */
    #[Test]
    public function freshCommentExposesInitializedCrdateAndHidden(): void
    {
        $comment = new Comment();

        self::assertNull($comment->getCrdate());
        self::assertFalse($comment->getHidden());
    }
}
