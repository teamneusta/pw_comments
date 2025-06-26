<?php

declare(strict_types=1);

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
    private MockObject $nodeFactory;
    private MockObject $commentRepository;
    private MockObject $moderationProviderFactory;
    private MockObject $persistenceManager;
    private MockObject $logger;
    private array $singletons;

    protected function setUp(): void
    {
        $this->singletons = GeneralUtility::getSingletonInstances();
        
        $this->nodeFactory = $this->createMock(NodeFactory::class);
        $this->commentRepository = $this->createMock(CommentRepository::class);
        $this->moderationProviderFactory = $this->createMock(ModerationProviderFactory::class);
        $this->persistenceManager = $this->createMock(PersistenceManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Mock GeneralUtility::makeInstance calls
        GeneralUtility::setSingletonInstance(CommentRepository::class, $this->commentRepository);
        GeneralUtility::setSingletonInstance(ModerationProviderFactory::class, $this->moderationProviderFactory);
        GeneralUtility::setSingletonInstance(PersistenceManager::class, $this->persistenceManager);

        $this->subject = new AiModerationControl($this->nodeFactory, []);
        $this->subject->setLogger($this->logger);
    }

    protected function tearDown(): void
    {
        GeneralUtility::resetSingletonInstances($this->singletons);
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    #[Test]
    public function renderReturnsInfoMessageWhenCommentUidIsZero(): void
    {
        $this->subject = new AiModerationControl($this->nodeFactory, [
            'databaseRow' => ['uid' => 0]
        ]);

        $result = $this->subject->render();

        self::assertIsArray($result);
        self::assertArrayHasKey('html', $result);
        self::assertStringContainsString('Save the record first to enable AI moderation controls', $result['html']);
        self::assertStringContainsString('alert-info', $result['html']);
    }

    #[Test]
    public function renderReturnsInfoMessageWhenCommentUidIsNull(): void
    {
        $this->subject = new AiModerationControl($this->nodeFactory, [
            'databaseRow' => ['uid' => null]
        ]);

        $result = $this->subject->render();

        self::assertIsArray($result);
        self::assertArrayHasKey('html', $result);
        self::assertStringContainsString('Save the record first to enable AI moderation controls', $result['html']);
    }

    #[Test]
    public function renderReturnsInfoMessageWhenUidKeyIsMissing(): void
    {
        $this->subject = new AiModerationControl($this->nodeFactory, [
            'databaseRow' => []
        ]);

        $result = $this->subject->render();

        self::assertIsArray($result);
        self::assertArrayHasKey('html', $result);
        self::assertStringContainsString('Save the record first to enable AI moderation controls', $result['html']);
    }

    #[Test]
    public function renderReturnsControlHtmlWhenValidCommentUid(): void
    {
        $iconFactory = $this->createMock(IconFactory::class);
        $icon = $this->createMock(Icon::class);

        $iconFactory->expects($this->once())
            ->method('getIcon')
            ->with('actions-refresh', Icon::SIZE_SMALL)
            ->willReturn($icon);
        
        $icon->expects($this->once())
            ->method('__toString')
            ->willReturn('<span class="icon">refresh</span>');

        $pageRenderer = $this->createMock(PageRenderer::class);

        GeneralUtility::addInstance(IconFactory::class, $iconFactory);
        GeneralUtility::setSingletonInstance(PageRenderer::class, $pageRenderer);

        $this->subject = new AiModerationControl($this->nodeFactory, [
            'databaseRow' => ['uid' => 123]
        ]);

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
        $iconFactory = $this->createMock(IconFactory::class);
        $icon = $this->createMock(Icon::class);

        $iconFactory->expects($this->once())
            ->method('getIcon')
            ->willReturn($icon);
        
        $icon->expects($this->once())
            ->method('__toString')
            ->willReturn('<span class="icon">refresh</span>');

        $pageRenderer = $this->createMock(PageRenderer::class);

        GeneralUtility::addInstance(IconFactory::class, $iconFactory);
        GeneralUtility::setSingletonInstance(PageRenderer::class, $pageRenderer);

        $this->subject = new AiModerationControl($this->nodeFactory, [
            'databaseRow' => ['uid' => $uid]
        ]);

        $result = $this->subject->render();

        self::assertStringContainsString('Re-check AI Moderation', $result['html']);
    }

    #[Test]
    public function renderLoadsJavaScriptModule(): void
    {
        $iconFactory = $this->createMock(IconFactory::class);
        $icon = $this->createMock(Icon::class);

        $iconFactory->expects($this->once())
            ->method('getIcon')
            ->willReturn($icon);
        
        $icon->expects($this->once())
            ->method('__toString')
            ->willReturn('<span>icon</span>');

        // Use a mock object to verify JavaScript module loading
        $pageRenderer = $this->createMock(PageRenderer::class);
        $pageRenderer->expects($this->once())
            ->method('loadJavaScriptModule')
            ->with('@t3/pw-comments/ai-moderation-control.js');
        $pageRenderer->expects($this->once())
            ->method('addInlineLanguageLabelFile')
            ->with('EXT:pw_comments/Resources/Private/Language/locallang_be.xlf');

        GeneralUtility::addInstance(IconFactory::class, $iconFactory);
        GeneralUtility::setSingletonInstance(PageRenderer::class, $pageRenderer);

        $this->subject = new AiModerationControl($this->nodeFactory, [
            'databaseRow' => ['uid' => 123]
        ]);

        $this->subject->render();
    }

    #[Test]
    public function htmlContainsRequiredElements(): void
    {
        $iconFactory = $this->createMock(IconFactory::class);
        $icon = $this->createMock(Icon::class);

        $iconFactory->expects($this->once())
            ->method('getIcon')
            ->with('actions-refresh', Icon::SIZE_SMALL)
            ->willReturn($icon);
        
        $icon->expects($this->once())
            ->method('__toString')
            ->willReturn('<span class="icon-refresh">refresh</span>');

        $pageRenderer = $this->createMock(PageRenderer::class);

        GeneralUtility::addInstance(IconFactory::class, $iconFactory);
        GeneralUtility::setSingletonInstance(PageRenderer::class, $pageRenderer);

        $this->subject = new AiModerationControl($this->nodeFactory, [
            'databaseRow' => ['uid' => 456]
        ]);

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
        $iconFactory = $this->createMock(IconFactory::class);
        $icon = $this->createMock(Icon::class);

        $iconFactory->expects($this->once())
            ->method('getIcon')
            ->willReturn($icon);
        
        $icon->expects($this->once())
            ->method('__toString')
            ->willReturn('<span>icon</span>');

        // Use a mock object to verify method calls
        $pageRenderer = $this->createMock(PageRenderer::class);
        $pageRenderer->expects($this->once())
            ->method('loadJavaScriptModule')
            ->with('@t3/pw-comments/ai-moderation-control.js');
        $pageRenderer->expects($this->once())
            ->method('addInlineLanguageLabelFile')
            ->with('EXT:pw_comments/Resources/Private/Language/locallang_be.xlf');

        GeneralUtility::addInstance(IconFactory::class, $iconFactory);
        GeneralUtility::setSingletonInstance(PageRenderer::class, $pageRenderer);

        $this->subject = new AiModerationControl($this->nodeFactory, [
            'databaseRow' => ['uid' => 789]
        ]);

        $this->subject->render();
    }
}