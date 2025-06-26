<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Service\Moderation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use T3\PwComments\Service\Moderation\ModerationProviderFactory;
use T3\PwComments\Service\Moderation\OpenAiModerationService;
use TYPO3\CMS\Core\Http\RequestFactory;

final class ModerationProviderFactoryTest extends TestCase
{
    private ModerationProviderFactory $factory;

    protected function setUp(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->factory = new ModerationProviderFactory(
            $requestFactory,
            $logger
        );
    }

    public function testCreatesOpenAiProviderWithDefaultSettings(): void
    {
        $provider = $this->factory->createProvider('openai', [
            'aiModerationApiKey' => 'test-key'
        ]);

        self::assertInstanceOf(OpenAiModerationService::class, $provider);
    }

    public function testCreatesOpenAiProviderWithCustomSettings(): void
    {
        $settings = [
            'aiModerationApiKey' => 'custom-key',
            'aiModerationApiEndpoint' => 'https://custom.api.endpoint',
            'aiModerationThreshold' => 0.5
        ];

        $provider = $this->factory->createProvider('openai', $settings);

        self::assertInstanceOf(OpenAiModerationService::class, $provider);
    }

    public function testCreatesOpenAiProviderWithMissingApiKey(): void
    {
        $provider = $this->factory->createProvider('openai', []);

        self::assertInstanceOf(OpenAiModerationService::class, $provider);
    }

    public static function invalidProviderDataProvider(): \Generator
    {
        yield 'empty provider name' => [''];
        yield 'unknown provider name' => ['unknown-provider'];
    }

    /**
     * @dataProvider invalidProviderDataProvider
     */
    public function testThrowsExceptionForUnknownProvider(string $provider): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown moderation provider: ' . $provider);

        $this->factory->createProvider($provider, []);
    }

    public function testPassesSettingsToOpenAiProvider(): void
    {
        $settings = [
            'aiModerationApiKey' => 'test-key-123',
            'aiModerationApiEndpoint' => 'https://example.com/api',
            'aiModerationThreshold' => 0.9
        ];

        $provider = $this->factory->createProvider('openai', $settings);

        self::assertInstanceOf(OpenAiModerationService::class, $provider);
    }

    public function testHandlesFloatThresholdFromString(): void
    {
        $settings = [
            'aiModerationApiKey' => 'test-key',
            'aiModerationThreshold' => '0.8'
        ];

        $provider = $this->factory->createProvider('openai', $settings);

        self::assertInstanceOf(OpenAiModerationService::class, $provider);
    }
}