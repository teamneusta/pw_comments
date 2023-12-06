<?php
declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\ViewHelpers\Format;

use DateTime;
use Generator;
use T3\PwComments\ViewHelpers\Format\DateViewHelper;
use PHPUnit\Framework\TestCase;
use function array_keys;

class DateViewHelperTest extends TestCase
{
    private DateViewHelper $subject;

    public function setUp(): void {
        $this->subject = new DateViewHelper();
    }

    /**
     * @test
     * @dataProvider renderDataProvider
     */
    public function renderShouldReturnDateForCurrentTimeInSpecifiedFormat(array $arguments, string $expected): void
    {
        $this->subject->setArguments($arguments);

        $this->assertEquals($expected, $this->subject->render());
    }

    public static function renderDataProvider(): Generator
    {
        yield 'without timestamp' => [
            'arguments' => [
                'timestamp' => null,
                'format' => 'Y-m-d',
            ],
            'expected' => (new DateTime())->format('Y-m-d'),
        ];
        yield 'with numeric timestamp' => [
            'arguments' => [
                'timestamp' => 1234567890,
                'format' => 'Y-m-%d',
            ],
            'expected' => '2009-02-13',
        ];
        yield 'with string times' => [
            'arguments' => [
                'timestamp' => '+1 day',
                'format' => '%Y-%m-%d',
            ],
            'expected' => (new DateTime('+1 day'))->format('Y-m-d'),
        ];
        yield 'with timestamp from provided DateTime object' => [
            'arguments' => [
                'timestamp' => new DateTime('+1 day'),
                'format' => 'Y-m-d',
            ],
            'expected' => (new DateTime('+1 day'))->format('Y-m-d'),
        ];
        yield 'with get modifier argument' => [
            'arguments' => [
                'timestamp' => new DateTime('+1 day'),
                'format' => 'Y-m-d',
                'get' => '+1 day',
            ],
            'expected' => (new DateTime('+2 days'))->format('Y-m-d'),
        ];
    }

    /**
     * @test
     */
    public function initializeArgumentsShouldRegisterAllNecessaryViewHelperArguments(): void
    {
        $expectedArguments = [
            'timestamp',
            'format',
            'get',
        ];

        $realArguments = $this->subject->prepareArguments();

        $this->assertCount(3, $realArguments);
        $this->assertEquals($expectedArguments, array_keys($realArguments));
    }
}
