<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Utility\HashEncryptionUtility;

final class HashEncryptionUtilityTest extends TestCase
{
    private const ENCRYPTION_KEY = 'test-encryption-key';
    private const ALTERNATE_KEY = 'a-very-different-key';
    private const MESSAGE = 'Hello world';

    private ?string $encryptionKeyBackup = null;

    protected function setUp(): void
    {
        $this->encryptionKeyBackup = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = self::ENCRYPTION_KEY;
    }

    protected function tearDown(): void
    {
        if ($this->encryptionKeyBackup === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $this->encryptionKeyBackup;
        }
    }

    #[Test]
    public function createHashForCommentMessageReturnsDeterministicTwentyCharHash(): void
    {
        $first = HashEncryptionUtility::createHashForCommentMessage(self::MESSAGE);
        $second = HashEncryptionUtility::createHashForCommentMessage(self::MESSAGE);

        self::assertSame($first, $second);
        self::assertSame(20, strlen($first));
        self::assertMatchesRegularExpression('/^[0-9a-f]{20}$/', $first);
    }

    #[Test]
    public function differentEncryptionKeysProduceDifferentHashesForTheSameMessage(): void
    {
        $withFirstKey = HashEncryptionUtility::createHashForCommentMessage(self::MESSAGE);

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = self::ALTERNATE_KEY;
        $withSecondKey = HashEncryptionUtility::createHashForCommentMessage(self::MESSAGE);

        self::assertNotSame($withFirstKey, $withSecondKey);
    }

    #[Test]
    public function createHashForCommentUsesTheCommentsMessage(): void
    {
        $comment = new Comment();
        $comment->setMessage(self::MESSAGE);

        self::assertSame(
            HashEncryptionUtility::createHashForCommentMessage(self::MESSAGE),
            HashEncryptionUtility::createHashForComment($comment),
        );
    }

    #[Test]
    public function validCommentMessageHashReturnsTrueWhenHashMatches(): void
    {
        $hash = HashEncryptionUtility::createHashForCommentMessage(self::MESSAGE);

        self::assertTrue(HashEncryptionUtility::validCommentMessageHash($hash, self::MESSAGE));
    }

    #[Test]
    public function validCommentMessageHashReturnsFalseWhenHashDoesNotMatch(): void
    {
        self::assertFalse(
            HashEncryptionUtility::validCommentMessageHash('not-the-real-hash', self::MESSAGE),
        );
    }

    #[Test]
    public function validCommentHashReturnsTrueForMatchingHash(): void
    {
        $comment = new Comment();
        $comment->setMessage(self::MESSAGE);
        $hash = HashEncryptionUtility::createHashForComment($comment);

        self::assertTrue(HashEncryptionUtility::validCommentHash($hash, $comment));
    }

    #[Test]
    public function validCommentHashReturnsFalseForMismatchedHash(): void
    {
        $comment = new Comment();
        $comment->setMessage(self::MESSAGE);

        self::assertFalse(HashEncryptionUtility::validCommentHash('not-the-real-hash', $comment));
    }

    #[Test]
    public function getEncryptionKeyThrowsWhenGlobalKeyIsEmpty(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(6508770623);

        HashEncryptionUtility::createHashForCommentMessage(self::MESSAGE);
    }
}
