<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\ViewHelpers\Format;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use T3\PwComments\ViewHelpers\Format\DateViewHelper;

final class DateViewHelperTest extends TestCase
{
    private const FIXED_TIMESTAMP = 1234567890; // 2009-02-13 23:31:30 UTC

    private ?string $previousTimezone = null;

    protected function setUp(): void
    {
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone ?? 'UTC');
    }

    #[Test]
    public function renderEmitsBrokenStrftimeStyleOutputForDefaultArguments(): void
    {
        // KNOWN BUG: render() preg_replaces `%` in front of every letter and then collapses
        // `%%` back to `%`, which is a no-op for already-prefixed strftime input. The result
        // is fed to DateTime::format, which treats `%` literally. Pinned until repaired.
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => self::FIXED_TIMESTAMP,
            'format' => '%Y-%m-%d',
            'get' => '',
        ]);

        self::assertSame('%2009-%02-%13', $viewHelper->render());
    }

    #[Test]
    public function renderUsesCurrentTimeWhenTimestampIsNull(): void
    {
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => null,
            'format' => '%Y-%m-%d',
            'get' => '',
        ]);

        self::assertMatchesRegularExpression('/^%\d{4}-%\d{2}-%\d{2}$/', $viewHelper->render());
    }

    #[Test]
    public function renderAcceptsNumericStringTimestamp(): void
    {
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => (string) self::FIXED_TIMESTAMP,
            'format' => '%Y-%m-%d',
            'get' => '',
        ]);

        self::assertSame('%2009-%02-%13', $viewHelper->render());
    }

    #[Test]
    public function renderAcceptsParseableDateString(): void
    {
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => '2009-02-13 GMT',
            'format' => '%Y-%m-%d',
            'get' => '',
        ]);

        self::assertSame('%2009-%02-%13', $viewHelper->render());
    }

    #[Test]
    public function renderAcceptsDateTimeObject(): void
    {
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => new \DateTime('@' . self::FIXED_TIMESTAMP),
            'format' => '%Y-%m-%d',
            'get' => '',
        ]);

        self::assertSame('%2009-%02-%13', $viewHelper->render());
    }

    #[Test]
    public function renderRejectsDateTimeImmutableWithTypeError(): void
    {
        // DateTimeImmutable is not an instance of DateTime and the parameter signature
        // (DateTime|string|int|null) rejects it via TypeError before the else-branch
        // InvalidArgumentException can fire. Pinned until the signature widens to
        // DateTimeInterface.
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => new \DateTimeImmutable('@' . self::FIXED_TIMESTAMP),
            'format' => '%Y-%m-%d',
            'get' => '',
        ]);

        $this->expectException(\TypeError::class);

        $viewHelper->render();
    }

    #[Test]
    public function renderRejectsUnsupportedTimestampTypeWithTypeError(): void
    {
        // The typed signature also catches bools etc. before the else-branch
        // InvalidArgumentException(3328256120) can fire — that branch is unreachable
        // through the public API today.
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => true,
            'format' => '%Y-%m-%d',
            'get' => '',
        ]);

        $this->expectException(\TypeError::class);

        $viewHelper->render();
    }

    #[Test]
    public function renderAppliesRelativeGetModifier(): void
    {
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => self::FIXED_TIMESTAMP,
            'format' => '%Y-%m-%d',
            'get' => '+1 day',
        ]);

        self::assertSame('%2009-%02-%14', $viewHelper->render());
    }

    #[Test]
    public function renderRendersEpochForZeroTimestamp(): void
    {
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => 0,
            'format' => '%Y-%m-%d',
            'get' => '',
        ]);

        self::assertSame('%1970-%01-%01', $viewHelper->render());
    }

    #[Test]
    public function renderThrowsWhenGetCannotBeParsed(): void
    {
        // KNOWN BUG: strtotime returns false on unparseable input, which is then passed to
        // DateTime::setTimestamp(int) and TypeErrors. Pinned until guarded.
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => self::FIXED_TIMESTAMP,
            'format' => '%Y-%m-%d',
            'get' => 'not-a-recognizable-relative-date',
        ]);

        $this->expectException(\TypeError::class);

        $viewHelper->render();
    }
}
