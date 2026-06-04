<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\ViewHelpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use T3\PwComments\ViewHelpers\GravatarViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;

final class GravatarViewHelperTest extends TestCase
{
    private const EMAIL = 'user@example.com';
    private const EXPECTED_HASH = 'b58996c504c5638798eb6b511e6f49af';

    #[Test]
    public function renderProducesGravatarSrcAndEmptyAltWithDefaultArguments(): void
    {
        $viewHelper = $this->buildViewHelper();
        $viewHelper->setArguments([
            'email' => self::EMAIL,
            'size' => 100,
            'default' => 'mm',
        ]);

        $output = $viewHelper->render();

        self::assertStringContainsString(
            'src="https://www.gravatar.com/avatar/' . self::EXPECTED_HASH . '?s=100&amp;d=mm"',
            $output,
        );
        self::assertStringContainsString('alt=""', $output);
    }

    #[Test]
    public function renderReflectsCustomSizeAndDefaultArguments(): void
    {
        $viewHelper = $this->buildViewHelper();
        $viewHelper->setArguments([
            'email' => self::EMAIL,
            'size' => 42,
            'default' => 'identicon',
        ]);

        $output = $viewHelper->render();

        self::assertStringContainsString('?s=42&amp;d=identicon', $output);
    }

    #[Test]
    public function renderTrimsAndLowercasesEmailBeforeHashing(): void
    {
        $viewHelper = $this->buildViewHelper();
        $viewHelper->setArguments([
            'email' => '  User@EXAMPLE.com  ',
            'size' => 100,
            'default' => 'mm',
        ]);

        $output = $viewHelper->render();

        self::assertStringContainsString(self::EXPECTED_HASH, $output);
    }

    #[Test]
    public function renderKeepsCallerSuppliedAltAttribute(): void
    {
        $viewHelper = $this->buildViewHelper();
        $viewHelper->setArguments([
            'email' => self::EMAIL,
            'size' => 100,
            'default' => 'mm',
        ]);
        $this->setAdditionalArguments($viewHelper, ['alt' => 'Profile picture']);

        $output = $viewHelper->render();

        self::assertStringNotContainsString('alt=""', $output);
    }

    private function buildViewHelper(): GravatarViewHelper
    {
        $viewHelper = new GravatarViewHelper();
        $viewHelper->setTagBuilder(new TagBuilder('img'));
        return $viewHelper;
    }

    private function setAdditionalArguments(GravatarViewHelper $viewHelper, array $additionalArguments): void
    {
        $reflection = new \ReflectionProperty($viewHelper, 'additionalArguments');
        $reflection->setValue($viewHelper, $additionalArguments);
    }
}
