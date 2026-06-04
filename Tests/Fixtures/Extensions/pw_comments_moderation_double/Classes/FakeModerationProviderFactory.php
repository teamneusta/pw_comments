<?php

declare(strict_types=1);

namespace T3\PwCommentsModerationDouble;

use Psr\Log\LoggerInterface;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Service\Moderation\ModerationProviderFactory;
use T3\PwComments\Service\Moderation\ModerationResult;
use T3\PwComments\Service\Moderation\ModerationServiceInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Functional-test double for ModerationProviderFactory. Tests set
 * `::$nextResult` or `::$nextException` before triggering createAction; the
 * fake then returns/throws that on the next createProvider() call.
 */
final class FakeModerationProviderFactory extends ModerationProviderFactory
{
    public static ?ModerationResult $nextResult = null;
    public static ?\Throwable $nextException = null;

    public function __construct(
        RequestFactory $requestFactory,
        LoggerInterface $logger,
    ) {
        parent::__construct($requestFactory, $logger);
    }

    public function createProvider(string $provider, array $settings): ModerationServiceInterface
    {
        if (self::$nextException !== null) {
            throw self::$nextException;
        }

        $result = self::$nextResult ?? new ModerationResult(false);

        return new class ($result) implements ModerationServiceInterface {
            public function __construct(private readonly ModerationResult $result) {}

            public function moderateComment(Comment $comment): ModerationResult
            {
                return $this->result;
            }
        };
    }

    public static function reset(): void
    {
        self::$nextResult = null;
        self::$nextException = null;
    }
}
