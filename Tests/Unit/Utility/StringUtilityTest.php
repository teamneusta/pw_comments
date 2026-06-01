<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use T3\PwComments\Utility\StringUtility;

final class StringUtilityTest extends TestCase
{
    #[Test]
    #[DataProvider('createLinksProvider')]
    public function createLinksWrapsUrlsAccordingToRegex(string $input, string $expected): void
    {
        self::assertSame($expected, StringUtility::createLinks($input));
    }

    public static function createLinksProvider(): array
    {
        return [
            'http url' => [
                'http://example.com',
                '<a href="http://example.com">http://example.com</a>',
            ],
            'https url keeps the s in href' => [
                'https://example.com',
                '<a href="https://example.com">https://example.com</a>',
            ],
            'www url without scheme is prefixed with http in href' => [
                'www.example.com',
                '<a href="http://www.example.com">www.example.com</a>',
            ],
            'trailing period is excluded from the match' => [
                'see https://example.com.',
                'see <a href="https://example.com">https://example.com</a>.',
            ],
            'path and query string are preserved' => [
                'https://example.com/foo?a=1&b=2',
                '<a href="https://example.com/foo?a=1&b=2">https://example.com/foo?a=1&b=2</a>',
            ],
            // Known regex quirk: the tail excludes only `.` and whitespace, so a trailing `)`
            // is swallowed into the href. Pinned to document current behavior.
            'trailing closing paren is swallowed into the anchor' => [
                'visit (https://example.com)',
                'visit (<a href="https://example.com)">https://example.com)</a>',
            ],
            'multiple urls are each wrapped' => [
                'one http://a.test and two https://b.test',
                'one <a href="http://a.test">http://a.test</a> and two <a href="https://b.test">https://b.test</a>',
            ],
            'plain text without url is unchanged' => [
                'just some text without a link',
                'just some text without a link',
            ],
            'non-http schemes are not matched' => [
                'ftp://example.com',
                'ftp://example.com',
            ],
        ];
    }

    #[Test]
    public function convertTrippleLinesCollapsesTripleNewlineToDouble(): void
    {
        $input = "first\r\n\r\n\r\nsecond";

        self::assertSame("first\r\n\r\nsecond", StringUtility::convertTrippleLinesToDoubleLines($input));
    }

    #[Test]
    public function convertTrippleLinesLoopsUntilNoTripleRemains(): void
    {
        $input = "a\r\n\r\n\r\n\r\nb";

        self::assertSame("a\r\n\r\nb", StringUtility::convertTrippleLinesToDoubleLines($input));
    }

    #[Test]
    public function convertTrippleLinesLeavesInputUnchangedWhenNoTriplePresent(): void
    {
        $input = "single line\r\nand another\r\n\r\nwith a double";

        self::assertSame($input, StringUtility::convertTrippleLinesToDoubleLines($input));
    }

    #[Test]
    public function prepareCommentMessageTrimsSurroundingWhitespace(): void
    {
        self::assertSame('hello', StringUtility::prepareCommentMessage("  \t hello \n "));
    }

    #[Test]
    public function prepareCommentMessageCollapsesTrippleNewlinesMidMessage(): void
    {
        $input = "first\r\n\r\n\r\nsecond";

        self::assertSame("first\r\n\r\nsecond", StringUtility::prepareCommentMessage($input));
    }

    #[Test]
    public function prepareCommentMessageEscapesHtmlSpecialChars(): void
    {
        self::assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            StringUtility::prepareCommentMessage('<script>alert(1)</script>'),
        );
    }

    #[Test]
    public function prepareCommentMessageWrapsUrlsOnlyWhenAllowLinksIsTrue(): void
    {
        $input = 'visit https://example.com today';

        self::assertSame(
            'visit https://example.com today',
            StringUtility::prepareCommentMessage($input, false),
        );
        self::assertSame(
            'visit <a href="https://example.com">https://example.com</a> today',
            StringUtility::prepareCommentMessage($input, true),
        );
    }
}
