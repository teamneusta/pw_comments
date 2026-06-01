<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use T3\PwComments\Utility\Settings;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

final class SettingsTest extends TestCase
{
    #[Test]
    public function renderConfigurationArrayReturnsSettingsUnchangedWhenRequestIsNullAndNotRenderable(): void
    {
        $settings = ['foo' => 'bar', 'baz.' => ['nested' => 'value']];

        self::assertSame($settings, Settings::renderConfigurationArray($settings));
    }

    #[Test]
    public function renderConfigurationArrayReturnsRenderableFormWhenRequestIsNullAndRenderableRequested(): void
    {
        $input = ['level1' => ['leaf' => 'hi', '_typoScriptNodeValue' => 'TEXT']];

        $result = Settings::renderConfigurationArray($input, true);

        self::assertSame(
            [
                'level1' => 'TEXT',
                'level1.' => ['leaf' => 'hi', '_typoScriptNodeValue' => 'TEXT'],
            ],
            $result,
        );
    }

    #[Test]
    public function renderConfigurationArrayBailsWhenRequestHasNoCurrentContentObjectAttribute(): void
    {
        $settings = ['foo' => 'bar'];
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with('currentContentObject')->willReturn(null);

        self::assertSame($settings, Settings::renderConfigurationArray($settings, false, $request));
    }

    #[Test]
    public function renderConfigurationArrayPassesThroughScalarKeyWithoutCompanionDottedEntry(): void
    {
        $request = $this->buildRequestWithCObj($this->createMock(ContentObjectRenderer::class));

        $result = Settings::renderConfigurationArray(['plain' => 'value'], false, $request);

        self::assertSame(['plain' => 'value'], $result);
    }

    #[Test]
    public function renderConfigurationArrayRecursesIntoDottedOnlyKey(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->expects(self::never())->method('cObjGetSingle');
        $request = $this->buildRequestWithCObj($cObj);

        $result = Settings::renderConfigurationArray(
            ['outer.' => ['inner' => 'leaf']],
            false,
            $request,
        );

        self::assertSame(['outer' => ['inner' => 'leaf']], $result);
    }

    #[Test]
    public function renderConfigurationArrayInvokesCObjGetSingleForValuePlusDottedPair(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj
            ->expects(self::once())
            ->method('cObjGetSingle')
            ->with('TEXT', ['value' => 'rendered'])
            ->willReturn('rendered');
        $request = $this->buildRequestWithCObj($cObj);

        $result = Settings::renderConfigurationArray(
            [
                'message' => 'TEXT',
                'message.' => ['value' => 'rendered'],
            ],
            false,
            $request,
        );

        self::assertSame(['message' => 'rendered'], $result);
    }

    #[Test]
    public function renderConfigurationArrayUsesTypoScriptNodeValueAsTypeWhenBareValueIsArray(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj
            ->expects(self::once())
            ->method('cObjGetSingle')
            ->with('TEXT', ['value' => 'rendered'])
            ->willReturn('rendered');
        $request = $this->buildRequestWithCObj($cObj);

        $result = Settings::renderConfigurationArray(
            [
                'message' => ['_typoScriptNodeValue' => 'TEXT', 'irrelevant' => 'x'],
                'message.' => ['value' => 'rendered'],
            ],
            false,
            $request,
        );

        self::assertSame(['message' => 'rendered'], $result);
    }

    #[Test]
    public function renderConfigurationArrayDoesNotPropagateRequestIntoRecursion(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->expects(self::never())->method('cObjGetSingle');
        $request = $this->buildRequestWithCObj($cObj);

        $result = Settings::renderConfigurationArray(
            [
                'outer.' => [
                    'inner' => 'TEXT',
                    'inner.' => ['value' => 'would-render-if-request-were-passed'],
                ],
            ],
            false,
            $request,
        );

        self::assertSame(
            [
                'outer' => [
                    'inner' => 'TEXT',
                    'inner.' => ['value' => 'would-render-if-request-were-passed'],
                ],
            ],
            $result,
        );
    }

    #[Test]
    public function renderConfigurationArrayReturnsEmptyArrayForEmptyInput(): void
    {
        $request = $this->buildRequestWithCObj($this->createMock(ContentObjectRenderer::class));

        self::assertSame([], Settings::renderConfigurationArray([], false, $request));
        self::assertSame([], Settings::renderConfigurationArray([], true, $request));
    }

    #[Test]
    public function renderConfigurationArrayTreatsLoneDotKeyAsDottedAndRecursesWithRawValue(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->expects(self::never())->method('cObjGetSingle');
        $request = $this->buildRequestWithCObj($cObj);

        $result = Settings::renderConfigurationArray(
            ['.' => ['child' => 'leaf']],
            false,
            $request,
        );

        self::assertSame(['' => ['child' => 'leaf']], $result);
    }

    #[Test]
    public function renderConfigurationArrayPassesThroughNumericKeys(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->expects(self::never())->method('cObjGetSingle');
        $request = $this->buildRequestWithCObj($cObj);

        $result = Settings::renderConfigurationArray(
            [0 => 'zero', 1 => 'one'],
            false,
            $request,
        );

        self::assertSame([0 => 'zero', 1 => 'one'], $result);
    }

    #[Test]
    public function makeRenderableEmitsBareAndDottedKeysWhenTypoScriptNodeValuePresent(): void
    {
        $input = ['message' => ['_typoScriptNodeValue' => 'TEXT', 'value' => 'hello']];

        $result = Settings::renderConfigurationArray($input, true);

        self::assertSame(
            [
                'message' => 'TEXT',
                'message.' => ['_typoScriptNodeValue' => 'TEXT', 'value' => 'hello'],
            ],
            $result,
        );
    }

    #[Test]
    public function makeRenderableEmitsOnlyDottedKeyWhenTypoScriptNodeValueAbsent(): void
    {
        $input = ['group' => ['a' => '1', 'b' => '2']];

        $result = Settings::renderConfigurationArray($input, true);

        self::assertSame(['group.' => ['a' => '1', 'b' => '2']], $result);
    }

    #[Test]
    public function makeRenderablePreservesScalarValues(): void
    {
        $input = ['title' => 'plain string', 'count' => 42];

        $result = Settings::renderConfigurationArray($input, true);

        self::assertSame(['title' => 'plain string', 'count' => 42], $result);
    }

    #[Test]
    public function makeRenderableRecursesThreeLevelsDeep(): void
    {
        $input = [
            'level1' => [
                '_typoScriptNodeValue' => 'COA',
                'level2' => [
                    '_typoScriptNodeValue' => 'TEXT',
                    'level3' => ['leaf' => 'deep'],
                ],
            ],
        ];

        $result = Settings::renderConfigurationArray($input, true);

        self::assertSame(
            [
                'level1' => 'COA',
                'level1.' => [
                    '_typoScriptNodeValue' => 'COA',
                    'level2' => 'TEXT',
                    'level2.' => [
                        '_typoScriptNodeValue' => 'TEXT',
                        'level3.' => ['leaf' => 'deep'],
                    ],
                ],
            ],
            $result,
        );
    }

    private function buildRequestWithCObj(ContentObjectRenderer $cObj): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with('currentContentObject')->willReturn($cObj);
        return $request;
    }
}
