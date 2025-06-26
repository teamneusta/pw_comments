<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Service\Moderation;

use PHPUnit\Framework\TestCase;
use T3\PwComments\Service\Moderation\ModerationResult;

final class ModerationResultTest extends TestCase
{
    public function testCanBeCreatedWithMinimalData(): void
    {
        $result = new ModerationResult(false);

        self::assertFalse($result->isViolation());
        self::assertSame([], $result->getCategories());
        self::assertSame([], $result->getCategoryScores());
        self::assertSame('', $result->getReason());
        self::assertSame(0.0, $result->getMaxScore());
    }

    public function testCanBeCreatedWithFullData(): void
    {
        $categories = ['harassment', 'violence'];
        $categoryScores = ['harassment' => 0.8, 'violence' => 0.3];
        $reason = 'Content flagged for harassment';
        $maxScore = 0.8;

        $result = new ModerationResult(
            true,
            $categories,
            $categoryScores,
            $reason,
            $maxScore
        );

        self::assertTrue($result->isViolation());
        self::assertSame($categories, $result->getCategories());
        self::assertSame($categoryScores, $result->getCategoryScores());
        self::assertSame($reason, $result->getReason());
        self::assertSame($maxScore, $result->getMaxScore());
    }

    public static function formattedReasonDataProvider(): \Generator
    {
        yield 'returns custom reason when provided' => [
            'reason' => 'Custom violation reason',
            'categories' => ['harassment'],
            'expected' => 'Custom violation reason'
        ];

        yield 'formats categories when no custom reason' => [
            'reason' => '',
            'categories' => ['harassment', 'violence'],
            'expected' => 'Content flagged for: harassment, violence'
        ];

        yield 'returns default message when no reason or categories' => [
            'reason' => '',
            'categories' => [],
            'expected' => 'AI moderation flagged this content'
        ];
    }

    /**
     * @dataProvider formattedReasonDataProvider
     */
    public function testGetFormattedReason(string $reason, array $categories, string $expected): void
    {
        $result = new ModerationResult(true, $categories, [], $reason);

        self::assertSame($expected, $result->getFormattedReason());
    }

    public function testFormattedReasonWithSingleCategory(): void
    {
        $result = new ModerationResult(true, ['harassment'], [], '');

        self::assertSame('Content flagged for: harassment', $result->getFormattedReason());
    }
}