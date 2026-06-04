<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\ViewHelpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use T3\PwComments\ViewHelpers\ArrayUniqueViewHelper;

final class ArrayUniqueViewHelperTest extends TestCase
{
    #[Test]
    public function renderDeduplicatesSubjectArgument(): void
    {
        $viewHelper = new ArrayUniqueViewHelper();
        $viewHelper->setArguments(['subject' => ['a', 'b', 'a', 'c', 'b']]);

        self::assertSame(['a', 'b', 'c'], array_values($viewHelper->render()));
    }

    #[Test]
    public function renderReturnsEmptyArrayForEmptySubjectWithoutFallingThroughToChildren(): void
    {
        $viewHelper = $this->getMockBuilder(ArrayUniqueViewHelper::class)
            ->onlyMethods(['renderChildren'])
            ->getMock();
        $viewHelper->expects(self::never())->method('renderChildren');
        $viewHelper->setArguments(['subject' => []]);

        self::assertSame([], $viewHelper->render());
    }

    #[Test]
    public function renderFallsBackToChildrenWhenSubjectIsNull(): void
    {
        $viewHelper = $this->getMockBuilder(ArrayUniqueViewHelper::class)
            ->onlyMethods(['renderChildren'])
            ->getMock();
        $viewHelper->method('renderChildren')->willReturn(['x', 'y', 'x']);
        $viewHelper->setArguments(['subject' => null]);

        self::assertSame(['x', 'y'], array_values($viewHelper->render()));
    }
}
