<?php
declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\ViewHelpers;

use PHPUnit\Framework\MockObject\MockObject;
use T3\PwComments\ViewHelpers\GravatarViewHelper;
use PHPUnit\Framework\TestCase;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;
use function md5;

class GravatarViewHelperTest extends TestCase
{
    private GravatarViewHelper $subject;
    /**
     * @var MockObject|(TagBuilder&MockObject)
     */
    private TagBuilder|MockObject $tag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tag = $this->createMock(TagBuilder::class);
        $this->subject = new GravatarViewHelper();
    }

    /**
     * @test
     */
    public function initializeArgumentsShouldAddNecessaryArgumentsForViewHelper(): void
    {
        $arguments = $this->subject->prepareArguments();

        $this->assertArrayHasKey('email', $arguments);
        $this->assertArrayHasKey('size', $arguments);
        $this->assertArrayHasKey('default', $arguments);
        $this->assertArrayHasKey('alt', $arguments);

        $this->assertSame('string', $arguments['email']->getType());
        $this->assertSame('integer', $arguments['size']->getType());
        $this->assertSame('string', $arguments['default']->getType());
        $this->assertSame('string', $arguments['alt']->getType());

        $this->assertSame('The mail to create Gravatar link for', $arguments['email']->getDescription());
        $this->assertSame('The size of avatar in pixel', $arguments['size']->getDescription());
        $this->assertSame('The image to take if user has no Gravatar', $arguments['default']->getDescription());
        $this->assertSame('Specifies an alternate text for an image', $arguments['alt']->getDescription());

        $this->assertSame(true, $arguments['email']->isRequired());
        $this->assertSame(false, $arguments['size']->isRequired());
        $this->assertSame(false, $arguments['default']->isRequired());
        $this->assertSame(false, $arguments['alt']->isRequired());

        $this->assertSame('mm', $arguments['default']->getDefaultValue());
        $this->assertSame(100, $arguments['size']->getDefaultValue());
    }
    
    /**
     * @test
     */
    public function renderShouldRenderGravatarImageTagWithNecessaryAttribute(): void
    {
        $this->subject->setTagBuilder($this->tag);
        $this->subject->setArguments([
            'email' => 'foo@bar.de',
            'size' => 100,
            'default' => 'mm',
            'alt' => null,
        ]);
        $expectedSrc = 'https://www.gravatar.com/avatar/' . md5('foo@bar.de') . '?s=100&d=mm';

        $this->tag
            ->expects($this->exactly(2))
            ->method('addAttribute')
            ->willReturnCallback(fn(string $property, string $value) => match (true) {
                $property === 'src' => $this->assertSame($expectedSrc, $value),
                $property === 'alt' => $this->assertSame('', $value),
                default => $this->fail('Unexpected property: ' . $property),
            });
        $this->tag
            ->expects($this->once())
            ->method('render')
            ->willReturn('rendered gravatar tag');

        $this->assertSame('rendered gravatar tag', $this->subject->render());
    }

    /**
     * @test
     */
    public function renderShouldRenderGravatarImageTagWithNecessaryAttributeWithExistingAltAttributeIfProvided(): void
    {
        $this->subject->setTagBuilder($this->tag);
        $this->subject->setArguments([
            'email' => 'foo@bar.de',
            'size' => 100,
            'default' => 'mm',
            'alt' => 'some alt text',
        ]);
        $expectedSrc = 'https://www.gravatar.com/avatar/' . md5('foo@bar.de') . '?s=100&d=mm';

        $this->tag
            ->expects($this->exactly(1))
            ->method('addAttribute')
            ->willReturnCallback(fn(string $property, string $value) => match (true) {
                $property === 'src' => $this->assertSame($expectedSrc, $value),
                default => $this->fail('Unexpected property: ' . $property),
            });
        $this->tag
            ->expects($this->once())
            ->method('render')
            ->willReturn('rendered gravatar tag');

        $this->assertSame('rendered gravatar tag', $this->subject->render());
    }
}
