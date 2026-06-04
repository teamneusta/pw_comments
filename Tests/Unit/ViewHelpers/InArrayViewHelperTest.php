<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\ViewHelpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use T3\PwComments\ViewHelpers\InArrayViewHelper;

final class InArrayViewHelperTest extends TestCase
{
    #[Test]
    public function renderReturnsTrueWhenNeedleIsPresent(): void
    {
        $viewHelper = new InArrayViewHelper();
        $viewHelper->setArguments(['subject' => ['a', 'b', 'c'], 'needle' => 'b', 'strict' => false]);

        self::assertTrue($viewHelper->render());
    }

    #[Test]
    public function renderReturnsFalseWhenNeedleIsAbsent(): void
    {
        $viewHelper = new InArrayViewHelper();
        $viewHelper->setArguments(['subject' => ['a', 'b', 'c'], 'needle' => 'z', 'strict' => false]);

        self::assertFalse($viewHelper->render());
    }

    #[Test]
    public function strictComparisonRejectsTypeMismatchedNeedle(): void
    {
        $viewHelper = new InArrayViewHelper();
        $viewHelper->setArguments(['subject' => [1, 2, 3], 'needle' => '1', 'strict' => true]);

        self::assertFalse($viewHelper->render());
    }

    #[Test]
    public function looseComparisonAcceptsTypeMismatchedNeedle(): void
    {
        $viewHelper = new InArrayViewHelper();
        $viewHelper->setArguments(['subject' => [1, 2, 3], 'needle' => '1', 'strict' => false]);

        self::assertTrue($viewHelper->render());
    }

    #[Test]
    public function renderFallsBackToChildrenWhenSubjectIsNull(): void
    {
        $viewHelper = $this->getMockBuilder(InArrayViewHelper::class)
            ->onlyMethods(['renderChildren'])
            ->getMock();
        $viewHelper->method('renderChildren')->willReturn(['x', 'y', 'z']);
        $viewHelper->setArguments(['subject' => null, 'needle' => 'y', 'strict' => false]);

        self::assertTrue($viewHelper->render());
    }
}
