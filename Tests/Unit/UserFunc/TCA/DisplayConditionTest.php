<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\UserFunc\TCA;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use T3\PwComments\UserFunc\TCA\DisplayCondition;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

final class DisplayConditionTest extends TestCase
{
    protected function tearDown(): void
    {
        GeneralUtility::resetSingletonInstances([]);
    }

    #[Test]
    public function isRatingEnabledReturnsTrueWhenEnableRatingIsOne(): void
    {
        $this->registerSettings(['enableRating' => '1']);

        self::assertTrue((new DisplayCondition())->isRatingEnabled([]));
    }

    #[Test]
    public function isRatingEnabledReturnsFalseWhenEnableRatingIsZero(): void
    {
        $this->registerSettings(['enableRating' => '0']);

        self::assertFalse((new DisplayCondition())->isRatingEnabled([]));
    }

    #[Test]
    public function isRatingEnabledReturnsFalseWhenEnableRatingIsEmptyString(): void
    {
        $this->registerSettings(['enableRating' => '']);

        self::assertFalse((new DisplayCondition())->isRatingEnabled([]));
    }

    #[Test]
    public function isRatingEnabledReturnsFalseWhenEnableRatingKeyIsMissing(): void
    {
        $this->registerSettings(['somethingElse' => 'value']);

        self::assertFalse((new DisplayCondition())->isRatingEnabled([]));
    }

    #[Test]
    public function isRatingEnabledReturnsFalseWhenSettingsLookupThrowsException(): void
    {
        $manager = $this->createMock(ConfigurationManagerInterface::class);
        $manager
            ->method('getConfiguration')
            ->willThrowException(new \Exception('boom'));
        GeneralUtility::setSingletonInstance(ConfigurationManagerInterface::class, $manager);

        self::assertFalse((new DisplayCondition())->isRatingEnabled([]));
    }

    #[Test]
    public function isRatingEnabledReturnsFalseWhenTypoScriptChainIsEmpty(): void
    {
        // Regression: Settings::getExtensionSettings() guards a broken/empty
        // TypoScript chain and returns []. enableRating is therefore missing
        // and the method returns false instead of bubbling a TypeError that
        // the catch(\Exception) wouldn't have intercepted.
        $manager = $this->createMock(ConfigurationManagerInterface::class);
        $manager
            ->method('getConfiguration')
            ->with(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT)
            ->willReturn([]);
        GeneralUtility::setSingletonInstance(ConfigurationManagerInterface::class, $manager);

        self::assertFalse((new DisplayCondition())->isRatingEnabled([]));
    }

    private function registerSettings(array $settings): void
    {
        $manager = $this->createMock(ConfigurationManagerInterface::class);
        $manager
            ->method('getConfiguration')
            ->with(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT)
            ->willReturn([
                'plugin.' => [
                    'tx_pwcomments.' => [
                        'settings.' => $settings,
                    ],
                ],
            ]);
        GeneralUtility::setSingletonInstance(ConfigurationManagerInterface::class, $manager);
    }
}
