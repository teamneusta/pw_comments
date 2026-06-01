<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\ViewHelpers\Format;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use T3\PwComments\ViewHelpers\Format\DateViewHelper;

final class DateViewHelperTest extends TestCase
{
    private const FIXED_TIMESTAMP = 1234567890; // 2009-02-13 23:31:30 UTC
    private const UNPARSEABLE_GET_EXCEPTION_CODE = 1780358400;

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
    public function renderFormatsTimestampWithStrftimeStyleDefault(): void
    {
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => self::FIXED_TIMESTAMP,
            'format' => '%Y-%m-%d',
            'get' => '',
        ]);

        self::assertSame('2009-02-13', $viewHelper->render());
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

        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $viewHelper->render());
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

        self::assertSame('2009-02-13', $viewHelper->render());
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

        self::assertSame('2009-02-13', $viewHelper->render());
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

        self::assertSame('2009-02-13', $viewHelper->render());
    }

    #[Test]
    public function renderAcceptsDateTimeImmutable(): void
    {
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => new \DateTimeImmutable('@' . self::FIXED_TIMESTAMP),
            'format' => '%Y-%m-%d',
            'get' => '',
        ]);

        self::assertSame('2009-02-13', $viewHelper->render());
    }

    #[Test]
    public function renderRejectsUnsupportedTimestampTypeWithTypeError(): void
    {
        // Passing an unsupported type triggers a TypeError from the union-type signature.
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

        self::assertSame('2009-02-14', $viewHelper->render());
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

        self::assertSame('1970-01-01', $viewHelper->render());
    }

    #[Test]
    public function renderThrowsInvalidArgumentExceptionWhenGetCannotBeParsed(): void
    {
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => self::FIXED_TIMESTAMP,
            'format' => '%Y-%m-%d',
            'get' => 'not-a-recognizable-relative-date',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(self::UNPARSEABLE_GET_EXCEPTION_CODE);

        $viewHelper->render();
    }

    #[Test]
    public function renderProducesSameOutputForStrftimeAndPhpDateStyleFormats(): void
    {
        $strftimeStyle = new DateViewHelper();
        $strftimeStyle->setArguments([
            'timestamp' => self::FIXED_TIMESTAMP,
            'format' => '%Y-%m-%d',
            'get' => '',
        ]);

        $phpDateStyle = new DateViewHelper();
        $phpDateStyle->setArguments([
            'timestamp' => self::FIXED_TIMESTAMP,
            'format' => 'Y-m-d',
            'get' => '',
        ]);

        self::assertSame('2009-02-13', $strftimeStyle->render());
        self::assertSame('2009-02-13', $phpDateStyle->render());
    }

    #[Test]
    public function renderProducesExpectedOutputForCommentPartialFormatString(): void
    {
        // Regression pin: Resources/Private/Partials/Comment.html:20 passes 'd.m.Y T'
        // for the title attribute on comment dates.
        $viewHelper = new DateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => self::FIXED_TIMESTAMP,
            'format' => 'd.m.Y T',
            'get' => '',
        ]);

        self::assertSame('13.02.2009 UTC', $viewHelper->render());
    }
}
