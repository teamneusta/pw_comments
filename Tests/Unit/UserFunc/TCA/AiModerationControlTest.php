<?php

declare(strict_types = 1);

namespace T3\PwComments\Tests\Unit\UserFunc\TCA;

// Define the LF constant if not already defined
if (!defined('LF')) {
    define('LF', chr(10));
}

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Domain\Repository\CommentRepository;
use T3\PwComments\Service\Moderation\ModerationProviderFactory;
use T3\PwComments\Service\Moderation\ModerationResult;
use T3\PwComments\Service\Moderation\ModerationServiceInterface;
use T3\PwComments\UserFunc\TCA\AiModerationControl;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

#[CoversClass(AiModerationControl::class)]
final class AiModerationControlTest extends TestCase
{
    private AiModerationControl $subject;
    private MockObject $commentRepository;
    private MockObject $moderationProviderFactory;
    private MockObject $persistenceManager;
    private MockObject $logger;
    private array $singletons;
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

        $this->iconFactory->expects($this->once())
            ->method('getIcon')
            ->with('actions-refresh', \TYPO3\CMS\Core\Imaging\IconSize::SMALL)
            ->willReturn($icon);

        $icon->expects($this->once())
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

        $this->iconFactory->expects($this->once())
            ->method('getIcon')
            ->willReturn($icon);

        $icon->expects($this->once())
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

        $this->iconFactory->expects($this->once())
            ->method('getIcon')
            ->willReturn($icon);

        $icon->expects($this->once())
            ->method('__toString')
            ->willReturn('<span>icon</span>');

        $this->pageRenderer->expects($this->once())
            ->method('loadJavaScriptModule')
            ->with('@t3/pw-comments/ai-moderation-control.js');
        $this->pageRenderer->expects($this->once())
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

        $this->iconFactory->expects($this->once())
            ->method('getIcon')
            ->with('actions-refresh', \TYPO3\CMS\Core\Imaging\IconSize::SMALL)
            ->willReturn($icon);

        $icon->expects($this->once())
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

        $this->iconFactory->expects($this->once())
            ->method('getIcon')
            ->willReturn($icon);

        $icon->expects($this->once())
            ->method('__toString')
            ->willReturn('<span>icon</span>');

        $this->pageRenderer->expects($this->once())
            ->method('loadJavaScriptModule')
            ->with('@t3/pw-comments/ai-moderation-control.js');
        $this->pageRenderer->expects($this->once())
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
}
