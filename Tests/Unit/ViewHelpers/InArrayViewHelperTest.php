<?php
declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\ViewHelpers;

use Generator;
use T3\PwComments\ViewHelpers\InArrayViewHelper;
use PHPUnit\Framework\TestCase;

class InArrayViewHelperTest extends TestCase
{
    private InArrayViewHelper $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new InArrayViewHelper();
    }

    /**
     * @test
     */
    public function initializeArgumentsShouldRegisterNecessaryArguments(): void
    {
        $arguments = $this->subject->prepareArguments();

        self::assertArrayHasKey('subject', $arguments);
        self::assertArrayHasKey('needle', $arguments);

        self::assertSame('array', $arguments['subject']->getType());
        self::assertSame('string', $arguments['needle']->getType());
        self::assertNull($arguments['needle']->getDefaultValue());
        self::assertTrue($arguments['needle']->isRequired());
    }

    /**
     * @test
     * @dataProvider renderDataProvider
     */
    public function renderShouldCheckAndReturnIfTheProvidedItemIsInTheProvidedArray(array $subject, $needle, bool $expected): void
    {

        $this->subject->setArguments(['subject' => $subject, 'needle' => $needle]);

        $result = $this->subject->render();

        self::assertEquals($expected, $result);
    }

    public static function renderDataProvider(): Generator
    {
        yield 'entry in array' => [
            ['foo', 'bar', 'baz'],
            'bar',
            true,
        ];
        yield 'entry not in array' => [
            ['foo', 'bar', 'baz'],
            'foobar',
            false,
        ];
        yield 'array empty' => [
            [],
            'foobar',
            false,
        ];
        yield 'entry empty string' => [
            ['foo', 'bar', 'baz'],
            '',
            false,
        ];
        yield 'entry null' => [
            ['foo', 'bar', 'baz'],
            null,
            false,
        ];
    }
    
    /**
     * @test
     */
    public function renderShouldRenderChildrenIfNoSubjectIsProvided(): void
    {
        $this->subject->setArguments(['subject' => null, 'needle' => 'some needle']);
        $this->subject->setRenderChildrenClosure(static fn() => ['some needle']);

        $this->assertTrue($this->subject->render());
    }
}
