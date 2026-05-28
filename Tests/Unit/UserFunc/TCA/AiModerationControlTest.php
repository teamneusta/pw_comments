<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\UserFunc\TCA;

use TYPO3\CMS\Core\Imaging\IconSize;

// Define the LF constant if not already defined
if (!defined('LF')) {
    define('LF', chr(10));
}

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Domain\Repository\CommentRepository;
use T3\PwComments\Service\Moderation\ModerationProviderFactory;
use T3\PwComments\Service\Moderation\ModerationResult;
use T3\PwComments\Service\Moderation\ModerationServiceInterface;
use T3\PwComments\UserFunc\TCA\AiModerationControl;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

#[CoversClass(AiModerationControl::class)]
final class AiModerationControlTest extends TestCase
{
    private AiModerationControl $subject;
    private MockObject $commentRepository;
    private MockObject $moderationProviderFactory;
    private MockObject $persistenceManager;
    private MockObject $logger;
    private IconFactory|MockObject $iconFactory;
    private PageRenderer|MockObject $pageRenderer;

    protected function setUp(): void
    {
        $this->commentRepository = $this->createMock(CommentRepository::class);
        $this->moderationProviderFactory = $this->createMock(ModerationProviderFactory::class);
        $this->persistenceManager = $this->createMock(PersistenceManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->iconFactory = $this->createMock(IconFactory::class);
        $this->pageRenderer = $this->createMock(PageRenderer::class);

        $this->subject = new AiModerationControl(
            $this->commentRepository,
            $this->moderationProviderFactory,
            $this->persistenceManager,
            $this->iconFactory,
            $this->pageRenderer,
        );
        $this->subject->setData([]);
        $this->subject->setLogger($this->logger);
    }

    #[Test]
    public function renderReturnsInfoMessageWhenCommentUidIsZero(): void
    {
        $this->subject = new AiModerationControl(
            $this->commentRepository,
            $this->moderationProviderFactory,
            $this->persistenceManager,
            $this->iconFactory,
            $this->pageRenderer,
        );
        $this->subject->setData(['databaseRow' => ['uid' => 0]]);

        $result = $this->subject->render();

        self::assertIsArray($result);
        self::assertArrayHasKey('html', $result);
        self::assertStringContainsString('Save the record first to enable AI moderation controls', $result['html']);
        self::assertStringContainsString('alert-info', $result['html']);
    }

    #[Test]
    public function renderReturnsInfoMessageWhenCommentUidIsNull(): void
    {
        $this->subject = new AiModerationControl(
            $this->commentRepository,
            $this->moderationProviderFactory,
            $this->persistenceManager,
            $this->iconFactory,
            $this->pageRenderer,
        );
        $this->subject->setData(['databaseRow' => ['uid' => null]]);

        $result = $this->subject->render();

        self::assertIsArray($result);
        self::assertArrayHasKey('html', $result);
        self::assertStringContainsString('Save the record first to enable AI moderation controls', $result['html']);
    }

    #[Test]
    public function renderReturnsInfoMessageWhenUidKeyIsMissing(): void
    {
        $this->subject = new AiModerationControl(
            $this->commentRepository,
            $this->moderationProviderFactory,
            $this->persistenceManager,
            $this->iconFactory,
            $this->pageRenderer,
        );
        $this->subject->setData(['databaseRow' => []]);

        $result = $this->subject->render();

        self::assertIsArray($result);
        self::assertArrayHasKey('html', $result);
        self::assertStringContainsString('Save the record first to enable AI moderation controls', $result['html']);
    }

    #[Test]
    public function renderReturnsControlHtmlWhenValidCommentUid(): void
    {
        $icon = $this->createMock(Icon::class);

        $this->iconFactory->expects(self::once())
            ->method('getIcon')
            ->with('actions-refresh', IconSize::SMALL)
            ->willReturn($icon);

        $icon->expects(self::once())
            ->method('__toString')
            ->willReturn('<span class="icon">refresh</span>');

        $this->subject = new AiModerationControl(
            $this->commentRepository,
            $this->moderationProviderFactory,
            $this->persistenceManager,
            $this->iconFactory,
            $this->pageRenderer,
        );
        $this->subject->setData(['databaseRow' => ['uid' => 123]]);

        $result = $this->subject->render();

        self::assertIsArray($result);
        self::assertArrayHasKey('html', $result);
        self::assertStringContainsString('btn-group', $result['html']);
        self::assertStringContainsString('Re-check AI Moderation', $result['html']);
    }

    public static function commentUidDataProvider(): \Generator
    {
        yield 'valid uid as string' => ['123', 123];
        yield 'valid uid as integer' => [456, 456];
        yield 'valid uid as float' => [789.0, 789];
    }

    #[Test]
    #[DataProvider('commentUidDataProvider')]
    public function renderHandlesDifferentUidTypes(mixed $uid, int $expectedUid): void
    {
        $icon = $this->createMock(Icon::class);

        $this->iconFactory->expects(self::once())
            ->method('getIcon')
            ->willReturn($icon);

        $icon->expects(self::once())
            ->method('__toString')
            ->willReturn('<span class="icon">refresh</span>');

        $this->subject = new AiModerationControl(
            $this->commentRepository,
            $this->moderationProviderFactory,
            $this->persistenceManager,
            $this->iconFactory,
            $this->pageRenderer,
        );
        $this->subject->setData(['databaseRow' => ['uid' => $uid]]);

        $result = $this->subject->render();

        self::assertStringContainsString('Re-check AI Moderation', $result['html']);
    }

    #[Test]
    public function renderLoadsJavaScriptModule(): void
    {
        $icon = $this->createMock(Icon::class);

        $this->iconFactory->expects(self::once())
            ->method('getIcon')
            ->willReturn($icon);

        $icon->expects(self::once())
            ->method('__toString')
            ->willReturn('<span>icon</span>');

        $this->pageRenderer->expects(self::once())
            ->method('loadJavaScriptModule')
            ->with('@t3/pw-comments/ai-moderation-control.js');
        $this->pageRenderer->expects(self::once())
            ->method('addInlineLanguageLabelFile')
            ->with('EXT:pw_comments/Resources/Private/Language/locallang_be.xlf');

        $this->subject = new AiModerationControl(
            $this->commentRepository,
            $this->moderationProviderFactory,
            $this->persistenceManager,
            $this->iconFactory,
            $this->pageRenderer,
        );
        $this->subject->setData(['databaseRow' => ['uid' => 123]]);

        $this->subject->render();
    }

    #[Test]
    public function htmlContainsRequiredElements(): void
    {
        $icon = $this->createMock(Icon::class);

        $this->iconFactory->expects(self::once())
            ->method('getIcon')
            ->with('actions-refresh', IconSize::SMALL)
            ->willReturn($icon);

        $icon->expects(self::once())
            ->method('__toString')
            ->willReturn('<span class="icon-refresh">refresh</span>');

        $this->subject = new AiModerationControl(
            $this->commentRepository,
            $this->moderationProviderFactory,
            $this->persistenceManager,
            $this->iconFactory,
            $this->pageRenderer,
        );
        $this->subject->setData(['databaseRow' => ['uid' => 456]]);

        $result = $this->subject->render();
        $html = $result['html'];

        self::assertStringContainsString('btn-group', $html);
        self::assertStringContainsString('btn-primary', $html);
        self::assertStringContainsString('Re-check AI Moderation', $html);
        self::assertStringContainsString('Re-run AI moderation check', $html);
        self::assertStringContainsString('icon-refresh', $html);
    }

    #[Test]
    public function pageRendererIsConfiguredCorrectly(): void
    {
        $icon = $this->createMock(Icon::class);

        $this->iconFactory->expects(self::once())
            ->method('getIcon')
            ->willReturn($icon);

        $icon->expects(self::once())
            ->method('__toString')
            ->willReturn('<span>icon</span>');

        $this->pageRenderer->expects(self::once())
            ->method('loadJavaScriptModule')
            ->with('@t3/pw-comments/ai-moderation-control.js');
        $this->pageRenderer->expects(self::once())
            ->method('addInlineLanguageLabelFile')
            ->with('EXT:pw_comments/Resources/Private/Language/locallang_be.xlf');

        $this->subject = new AiModerationControl(
            $this->commentRepository,
            $this->moderationProviderFactory,
            $this->persistenceManager,
            $this->iconFactory,
            $this->pageRenderer,
        );
        $this->subject->setData(['databaseRow' => ['uid' => 789]]);

        $this->subject->render();
    }

    #[Test]
    public function recheckModerationReturnsErrorWhenCommentUidIsZero(): void
    {
        $payload = $this->decodePayload(
            $this->subject->recheckModeration($this->buildRequest(['commentUid' => 0])),
        );

        self::assertFalse($payload['success']);
        self::assertSame('Invalid comment ID', $payload['message']);
    }

    #[Test]
    public function recheckModerationReturnsErrorWhenCommentUidIsMissing(): void
    {
        $payload = $this->decodePayload(
            $this->subject->recheckModeration($this->buildRequest([])),
        );

        self::assertFalse($payload['success']);
        self::assertSame('Invalid comment ID', $payload['message']);
    }

    #[Test]
    public function recheckModerationReturnsErrorWhenCommentNotFound(): void
    {
        $this->commentRepository->method('findByCommentUid')->with(42)->willReturn(null);

        $payload = $this->decodePayload(
            $this->subject->recheckModeration($this->buildRequest(['commentUid' => 42])),
        );

        self::assertFalse($payload['success']);
        self::assertSame('Comment not found', $payload['message']);
    }

    #[Test]
    public function recheckModerationReturnsErrorWhenAiModerationDisabled(): void
    {
        $this->commentRepository->method('findByCommentUid')->willReturn($this->createMock(Comment::class));
        $subject = $this->buildSubjectWithSettings(['enableAiModeration' => false]);

        $payload = $this->decodePayload(
            $subject->recheckModeration($this->buildRequest(['commentUid' => 42])),
        );

        self::assertFalse($payload['success']);
        self::assertSame('AI moderation is disabled', $payload['message']);
    }

    #[Test]
    public function recheckModerationApprovesNonViolatingComment(): void
    {
        $comment = $this->createMock(Comment::class);
        $comment->method('getAiModerationStatus')->willReturn('approved');
        $comment->method('getAiModerationReason')->willReturn('');
        $comment->method('getAiModerationConfidence')->willReturn(0.1);
        $comment->expects(self::once())->method('setAiModerationStatus')->with('approved');
        $comment->expects(self::once())->method('setAiModerationReason')->with('');
        $comment->expects(self::never())->method('setHidden');

        $this->commentRepository->method('findByCommentUid')->willReturn($comment);
        $this->commentRepository->expects(self::once())->method('update')->with($comment);
        $this->persistenceManager->expects(self::once())->method('persistAll');
        $this->logger->expects(self::once())->method('info')
            ->with('Manual AI moderation recheck completed', self::callback(
                static fn(array $ctx): bool => $ctx['comment_uid'] === 42 && $ctx['result'] === 'approved',
            ));

        $service = $this->createMock(ModerationServiceInterface::class);
        $service->method('moderateComment')->willReturn(new ModerationResult(false, [], [], '', 0.1));
        $this->moderationProviderFactory->method('createProvider')->willReturn($service);

        $subject = $this->buildSubjectWithSettings([
            'enableAiModeration' => true,
            'aiModerationProvider' => 'openai',
        ]);

        $payload = $this->decodePayload(
            $subject->recheckModeration($this->buildRequest(['commentUid' => 42])),
        );

        self::assertTrue($payload['success']);
        self::assertSame('AI moderation check completed', $payload['message']);
        self::assertSame('approved', $payload['result']['status']);
    }

    #[Test]
    public function recheckModerationFlagsViolatingComment(): void
    {
        $comment = $this->createMock(Comment::class);
        $comment->method('getAiModerationStatus')->willReturn('flagged');
        $comment->method('getAiModerationReason')->willReturn('harassment');
        $comment->method('getAiModerationConfidence')->willReturn(0.92);
        $comment->expects(self::once())->method('setAiModerationStatus')->with('flagged');
        $comment->expects(self::once())->method('setAiModerationReason')->with('harassment');
        $comment->expects(self::once())->method('setHidden')->with(true);

        $this->commentRepository->method('findByCommentUid')->willReturn($comment);

        $service = $this->createMock(ModerationServiceInterface::class);
        $service->method('moderateComment')->willReturn(
            new ModerationResult(true, ['harassment'], ['harassment' => 0.92], 'harassment', 0.92),
        );
        $this->moderationProviderFactory->method('createProvider')->willReturn($service);

        $subject = $this->buildSubjectWithSettings([
            'enableAiModeration' => true,
            'aiModerationProvider' => 'openai',
        ]);

        $payload = $this->decodePayload(
            $subject->recheckModeration($this->buildRequest(['commentUid' => 42])),
        );

        self::assertTrue($payload['success']);
        self::assertSame('flagged', $payload['result']['status']);
        self::assertSame('harassment', $payload['result']['reason']);
    }

    #[Test]
    public function recheckModerationReturnsErrorAndLogsOnProviderException(): void
    {
        $this->commentRepository->method('findByCommentUid')->willReturn($this->createMock(Comment::class));

        $service = $this->createMock(ModerationServiceInterface::class);
        $service->method('moderateComment')->willThrowException(new \RuntimeException('upstream down'));
        $this->moderationProviderFactory->method('createProvider')->willReturn($service);

        $this->logger->expects(self::once())->method('error')
            ->with('Manual AI moderation recheck failed', self::callback(
                static fn(array $ctx): bool => $ctx['comment_uid'] === 42 && $ctx['exception'] instanceof \RuntimeException,
            ));

        $subject = $this->buildSubjectWithSettings([
            'enableAiModeration' => true,
            'aiModerationProvider' => 'openai',
        ]);

        $response = $subject->recheckModeration($this->buildRequest(['commentUid' => 42]));
        $payload = $this->decodePayload($response);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertFalse($payload['success']);
        self::assertStringContainsString('upstream down', $payload['message']);
    }

    private function buildRequest(array $parsedBody): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getParsedBody')->willReturn($parsedBody);
        return $request;
    }

    private function decodePayload(ResponseInterface $response): array
    {
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        $decoded = json_decode((string) $response->getBody(), true);
        self::assertIsArray($decoded);
        return $decoded;
    }

    private function buildSubjectWithSettings(array $settings): AiModerationControl
    {
        $subject = $this->getMockBuilder(AiModerationControl::class)
            ->setConstructorArgs([
                $this->commentRepository,
                $this->moderationProviderFactory,
                $this->persistenceManager,
                $this->iconFactory,
                $this->pageRenderer,
            ])
            ->onlyMethods(['getAiModerationSettings'])
            ->getMock();
        $subject->method('getAiModerationSettings')->willReturn($settings);
        $subject->setLogger($this->logger);
        return $subject;
    }
}
