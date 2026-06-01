<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\ViewHelpers\Format;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use T3\PwComments\ViewHelpers\Format\RelativeDateViewHelper;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class RelativeDateViewHelperTest extends TestCase
{
    protected function setUp(): void
    {
        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('translate')->willReturnCallback(
            fn(string $id): string => substr($id, strrpos($id, '.') + 1),
        );

        $factory = $this->createMock(LanguageServiceFactory::class);
        $factory->method('create')->willReturn($languageService);
        $factory->method('createFromUserPreferences')->willReturn($languageService);
        $factory->method('createFromSiteLanguage')->willReturn($languageService);
        GeneralUtility::addInstance(LanguageServiceFactory::class, $factory);
        // Re-add for tests that call render() implicitly twice (one per LocalizationUtility::translate call chain);
        // makeDateRelative invokes getLabel up to twice per render (label + plural suffix).
        GeneralUtility::addInstance(LanguageServiceFactory::class, $factory);
        GeneralUtility::addInstance(LanguageServiceFactory::class, $factory);

        $locales = $this->createMock(Locales::class);
        $locales->method('createLocaleFromRequest')->willReturn(new Locale('en'));
        $locales->method('createLocale')->willReturn(new Locale('en'));
        GeneralUtility::setSingletonInstance(Locales::class, $locales);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        GeneralUtility::resetSingletonInstances([]);
    }

    #[Test]
    public function renderReturnsFewSecondsLabelWhenDiffIsUnderAMinute(): void
    {
        $viewHelper = new RelativeDateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => time() - 5,
            'format' => 'Y-m-d',
            'wrap' => '<rel>%s</rel>',
            'wrapAbsolute' => '<abs>%s</abs>',
        ]);

        self::assertSame('<rel>fewSeconds</rel>', $viewHelper->render());
    }

    #[Test]
    public function renderReturnsMinutesWithPluralSuffixForMidBandInput(): void
    {
        $viewHelper = new RelativeDateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => time() - 30 * 60,
            'format' => 'Y-m-d',
            'wrap' => '%s',
            'wrapAbsolute' => '%s',
        ]);

        self::assertSame('30 minutepluralSuffix', $viewHelper->render());
    }

    #[Test]
    public function renderOmitsPluralSuffixForSingularMinute(): void
    {
        $viewHelper = new RelativeDateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => time() - 65,
            'format' => 'Y-m-d',
            'wrap' => '%s',
            'wrapAbsolute' => '%s',
        ]);

        self::assertSame('1 minute', $viewHelper->render());
    }

    #[Test]
    public function renderReturnsHoursWithPluralSuffix(): void
    {
        $viewHelper = new RelativeDateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => time() - 5 * 60 * 60,
            'format' => 'Y-m-d',
            'wrap' => '%s',
            'wrapAbsolute' => '%s',
        ]);

        self::assertSame('5 hourpluralSuffix', $viewHelper->render());
    }

    #[Test]
    public function renderReturnsDaysWithForDayPluralVariant(): void
    {
        $viewHelper = new RelativeDateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => time() - 3 * 24 * 60 * 60,
            'format' => 'Y-m-d',
            'wrap' => '%s',
            'wrapAbsolute' => '%s',
        ]);

        self::assertSame('3 daypluralSuffixForDay', $viewHelper->render());
    }

    #[Test]
    public function renderReturnsWeeksWithPluralSuffix(): void
    {
        $viewHelper = new RelativeDateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => time() - 2 * 7 * 24 * 60 * 60,
            'format' => 'Y-m-d',
            'wrap' => '%s',
            'wrapAbsolute' => '%s',
        ]);

        self::assertSame('2 weekpluralSuffix', $viewHelper->render());
    }

    #[Test]
    public function renderFallsBackToAbsoluteDateAndWrapAbsoluteWhenOlderThanFourWeeks(): void
    {
        $viewHelper = new RelativeDateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => 1234567890,
            'format' => 'Y-m-d',
            'wrap' => '<rel>%s</rel>',
            'wrapAbsolute' => '<abs>%s</abs>',
        ]);

        self::assertSame('<abs>' . date('Y-m-d', 1234567890) . '</abs>', $viewHelper->render());
    }

    #[Test]
    public function renderTreatsNullTimestampAsCurrentTime(): void
    {
        $viewHelper = new RelativeDateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => null,
            'format' => 'Y-m-d',
            'wrap' => '%s',
            'wrapAbsolute' => '%s',
        ]);

        self::assertSame('fewSeconds', $viewHelper->render());
    }

    #[Test]
    public function renderRejectsUnsupportedTimestampTypeWithTypeError(): void
    {
        // The else-branch InvalidArgumentException(5991273415) is unreachable: the typed
        // signature DateTime|string|int|null catches everything else as a TypeError first.
        $viewHelper = new RelativeDateViewHelper();
        $viewHelper->setArguments([
            'timestamp' => true,
            'format' => 'Y-m-d',
            'wrap' => '%s',
            'wrapAbsolute' => '%s',
        ]);

        $this->expectException(\TypeError::class);

        $viewHelper->render();
    }
}
