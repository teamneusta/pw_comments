<?php
declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\ViewHelpers\Format;

use DateTime;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use T3\PwComments\ViewHelpers\Format\RelativeDateViewHelper;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Localization\LanguageService;
use function sprintf;

class RelativeDateViewHelperTest extends TestCase
{
    private RelativeDateViewHelper $subject;
    /**
     * @var MockObject|LanguageService(LanguageService&MockObject)
     */
    private LanguageService|MockObject $languageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->languageService = $this->createStub(LanguageService::class);
        $this->subject = new RelativeDateViewHelper($this->languageService);
    }
    
    /**
     * @test
     */
    public function initializeArgumentsShouldRegisterAllNecessaryArgumentsForViewHelper(): void
    {
        $arguments = $this->subject->prepareArguments();

        $this->assertArrayHasKey('timestamp', $arguments);
        $this->assertArrayHasKey('format', $arguments);
        $this->assertArrayHasKey('wrap', $arguments);
        $this->assertArrayHasKey('wrapAbsolute', $arguments);
    }
    
    /**
     * @test
     * @dataProvider renderDataProvider
     */
    public function renderShouldNormalizeTimestampAndReturnAsRelativeDateTime(array $arguments, string $expected): void
    {
        $this->subject->setArguments($arguments);

        $this->languageService
            ->method('sL')
            ->willReturnCallback(static function(string $key): string {
                $map = [
                    'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:tx_pwcomments.relativeDate.pluralSuffix' => 's',
                    'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:tx_pwcomments.relativeDate.pluralSuffixForDay' => 'sForDay',
                    'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:tx_pwcomments.relativeDate.fewSeconds' => 'few seconds label',
                    'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:tx_pwcomments.relativeDate.minute' => 'minute',
                    'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:tx_pwcomments.relativeDate.minutes' => 'minutes',
                    'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:tx_pwcomments.relativeDate.hour' => 'hour',
                    'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:tx_pwcomments.relativeDate.hours' => 'hours',
                    'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:tx_pwcomments.relativeDate.day' => 'day',
                    'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:tx_pwcomments.relativeDate.days' => 'days',
                    'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:tx_pwcomments.relativeDate.week' => 'week',
                    'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:tx_pwcomments.relativeDate.weeks' => 'weeks',
                ];

                return $map[$key];
            });

        $this->assertEquals($expected, $this->subject->render());
    }

    public static function renderDataProvider(): Generator
    {
        yield 'without timestamp' => [
            'arguments' => [
                'timestamp' => null,
                'wrap' => '%s',
            ],
            'expected' => 'few seconds label',
        ];
        yield 'with numeric timestamp' => [
            'arguments' => [
                'timestamp' => time(),
                'wrap' => '%s',
            ],
            'expected' => 'few seconds label',
        ];
        yield 'with string times' => [
            'arguments' => [
                'timestamp' => '+30 seconds',
                'wrap' => '%s',
            ],
            'expected' => 'few seconds label',
        ];
        yield 'with timestamp from provided DateTime object' => [
            'arguments' => [
                'timestamp' => new DateTime('now'),
                'wrap' => '<span>%s</span>',
            ],
            'expected' => '<span>few seconds label</span>',
        ];
        yield 'with timestamp one minute ago' => [
            'arguments' => [
                'timestamp' => new DateTime('-1 minute'),
                'wrap' => '%s',
            ],
            'expected' => '1 minute',
        ];
        yield 'with timestamp minutes ago' => [
            'arguments' => [
                'timestamp' => new DateTime('-3 minute'),
                'wrap' => '%s',
            ],
            'expected' => '3 minutes',
        ];
        yield 'with timestamp one hour ago' => [
            'arguments' => [
                'timestamp' => new DateTime('-1 hour'),
                'wrap' => '%s',
            ],
            'expected' => '1 hour',
        ];
        yield 'with timestamp hours ago' => [
            'arguments' => [
                'timestamp' => new DateTime('-3 hours'),
                'wrap' => '%s',
            ],
            'expected' => '3 hours',
        ];
        yield 'with timestamp one day ago' => [
            'arguments' => [
                'timestamp' => new DateTime('-1 day'),
                'wrap' => '%s',
            ],
            'expected' => '1 day',
        ];
        yield 'with timestamp days ago' => [
            'arguments' => [
                'timestamp' => new DateTime('-3 days'),
                'wrap' => '%s',
            ],
            'expected' => '3 daysForDay',
        ];
        yield 'with timestamp one week ago' => [
            'arguments' => [
                'timestamp' => new DateTime('-1 week'),
                'wrap' => '%s',
            ],
            'expected' => '1 week',
        ];
        yield 'with timestamp weeks ago' => [
            'arguments' => [
                'timestamp' => new DateTime('-3 weeks'),
                'wrap' => '%s',
            ],
            'expected' => '3 weeks',
        ];
        yield 'with absolute date in provided format and wrap absolute if date is longer ago than 4 weeks' => [
            'arguments' => [
                'timestamp' => new DateTime('-5 weeks'),
                'wrap' => '%s',
                'wrapAbsolute' => '<span>%s</span>',
                'format' => 'Y-m-d'
            ],
            'expected' => sprintf('<span>%s</span>', (new DateTime('-5 weeks'))->format('Y-m-d')),
        ];
    }
}
