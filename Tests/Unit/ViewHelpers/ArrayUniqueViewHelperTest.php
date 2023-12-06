<?php
declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\ViewHelpers;

use T3\PwComments\ViewHelpers\ArrayUniqueViewHelper;
use PHPUnit\Framework\TestCase;

class ArrayUniqueViewHelperTest extends TestCase
{
    private ArrayUniqueViewHelper $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ArrayUniqueViewHelper();
    }

    /**
     * @test
     */
    public function initializeArgumentsShouldRegisterNecessaryArguments(): void
    {
        $arguments = $this->subject->prepareArguments();

        $this->assertArrayHasKey('subject', $arguments);
        $this->assertSame('array', $arguments['subject']->getType());
        $this->assertFalse($arguments['subject']->isRequired());
    }

    /**
     * @test
     */
    public function renderShouldReturnProvidedArrayWithoutDuplicates(): void
    {
        $this->subject->setArguments([
            'subject' => ['foo', 'bar', 'foo']
        ]);

        $this->assertSame(['foo', 'bar'], $this->subject->render());
    }

    /**
     * @test
     */
    public function renderShouldRenderChildrenIfNoValidArrayWasProvided(): void
    {

        $this->subject->setArguments([
            'subject' => null
        ]);
        $this->subject->setRenderChildrenClosure(fn() => ['some list']);

        $this->assertSame(['some list'], $this->subject->render());
    }
}
