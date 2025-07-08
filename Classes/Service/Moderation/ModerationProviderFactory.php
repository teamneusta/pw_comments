<?php

declare(strict_types=1);

namespace T3\PwComments\Service\Moderation;

use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Log\Channel;
use TYPO3\CMS\Core\SingletonInterface;
use Psr\Log\LoggerInterface;

#[Channel('pw_comments')]
class ModerationProviderFactory implements SingletonInterface
{
    private RequestFactory $requestFactory;
    private LoggerInterface $logger;

    public function __construct(
        RequestFactory $requestFactory,
        LoggerInterface $logger
    ) {
        $this->requestFactory = $requestFactory;
        $this->logger = $logger;
    }

    public function createProvider(string $provider, array $settings): ModerationServiceInterface
    {
        return match ($provider) {
            'openai' => new OpenAiModerationService(
                $this->requestFactory,
                $this->logger,
                $settings['aiModerationApiKey'] ?? '',
                $settings['aiModerationApiEndpoint'] ?? 'https://api.openai.com/v1/moderations',
                (float)($settings['aiModerationThreshold'] ?? 0.7)
            ),
            default => throw new \InvalidArgumentException('Unknown moderation provider: ' . $provider, 7207721257),
        };
    }
}