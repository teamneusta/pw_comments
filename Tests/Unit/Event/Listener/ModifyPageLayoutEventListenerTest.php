<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Event\Listener;

use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use T3\PwComments\Event\Listener\ModifyPageLayoutEventListener;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;

final class ModifyPageLayoutEventListenerTest extends TestCase
{
    private const LLL_PREFIX = 'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:';
    private const RENDER_MARKER = '<rendered/>';
    private const STUB_BACKEND_PACKAGE_PATH = '/stub/backend/';

    protected function setUp(): void
    {
        $package = $this->createMock(PackageInterface::class);
        $package->method('getPackagePath')->willReturn(self::STUB_BACKEND_PACKAGE_PATH);

        $packageManager = $this->createMock(PackageManager::class);
        $packageManager->method('extractPackageKeyFromPackagePath')->willReturn('backend');
        $packageManager->method('getPackage')->willReturn($package);

        ExtensionManagementUtility::setPackageManager($packageManager);
    }

    public static function renderingMatrix(): array
    {
        return [
            'singular total, no unreleased' => [
                1, 1,
                self::LLL_PREFIX . 'totalCommentsAmountOne',
                null,
            ],
            'plural total, no unreleased' => [
                5, 5,
                self::LLL_PREFIX . 'totalCommentsAmount',
                null,
            ],
            'plural total, singular unreleased' => [
                2, 1,
                self::LLL_PREFIX . 'totalCommentsAmount',
                self::LLL_PREFIX . 'unreleasedCommentsAmountOne',
            ],
            'plural total, plural unreleased' => [
                5, 2,
                self::LLL_PREFIX . 'totalCommentsAmount',
                self::LLL_PREFIX . 'unreleasedCommentsAmount',
            ],
        ];
    }

    #[Test]
    #[DataProvider('renderingMatrix')]
    public function invokeRendersExpectedLabelsAndSetsHeaderContent(
        int $total,
        int $released,
        string $expectedTotalKey,
        ?string $expectedUnreleasedKey,
    ): void {
        $languageService = $this->createLanguageServiceReturningKeys();
        [$qb1, $qb2] = $this->createQueryBuilders($total, $released);
        $connectionPool = $this->createConnectionPool($qb1, $qb2);
        $capturedAssign = null;
        $capturedFactoryData = null;
        $viewFactory = $this->createViewFactoryCaptures($capturedAssign, $capturedFactoryData);

        $event = $this->buildEvent(['id' => 17]);
        $listener = new ModifyPageLayoutEventListener($languageService, $connectionPool, $viewFactory);

        $listener($event);

        self::assertSame(self::RENDER_MARKER, $event->getHeaderContent());
        self::assertNotNull($capturedAssign);
        self::assertSame('pw_comments', $capturedAssign['title'] ?? null);
        self::assertSame(ContextualFeedbackSeverity::INFO, $capturedAssign['state'] ?? null);

        $message = $capturedAssign['message'] ?? '';
        self::assertStringContainsString($expectedTotalKey, $message);
        if ($expectedUnreleasedKey !== null) {
            self::assertStringContainsString($expectedUnreleasedKey, $message);
            self::assertStringContainsString('<br><b>', $message);
        } else {
            self::assertStringNotContainsString('unreleasedCommentsAmount', $message);
            self::assertStringNotContainsString('<br><b>', $message);
        }
        self::assertStringContainsString(self::LLL_PREFIX . 'showComments', $message);

        self::assertNotNull($capturedFactoryData);
        self::assertSame(
            self::STUB_BACKEND_PACKAGE_PATH . 'Resources/Private/Templates/InfoBox.fluid.html',
            $capturedFactoryData->templatePathAndFilename,
        );
    }

    #[Test]
    public function invokeReturnsEarlyWithoutSettingHeaderContentWhenTotalIsZero(): void
    {
        $languageService = $this->createMock(LanguageService::class);
        $languageService->expects(self::never())->method('sL');

        [$qb1, $qb2] = $this->createQueryBuilders(0, 0);
        $qb2->expects(self::never())->method('count');

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool
            ->expects(self::once())
            ->method('getQueryBuilderForTable')
            ->with('tx_pwcomments_domain_model_comment')
            ->willReturn($qb1);

        $viewFactory = $this->createMock(ViewFactoryInterface::class);
        $viewFactory->expects(self::never())->method('create');

        $event = $this->buildEvent(['id' => 17]);
        $listener = new ModifyPageLayoutEventListener($languageService, $connectionPool, $viewFactory);

        $listener($event);

        self::assertSame('', $event->getHeaderContent());
    }

    public static function invalidPageIdInputs(): array
    {
        return [
            'missing id' => [[]],
            'string id' => [['id' => 'abc']],
            'null id' => [['id' => null]],
            'negative integer id' => [['id' => -5]],
        ];
    }

    #[Test]
    #[DataProvider('invalidPageIdInputs')]
    public function invokeCoercesMissingOrInvalidQueryParamToZero(array $queryParams): void
    {
        $languageService = $this->createMock(LanguageService::class);
        [$qb1, $qb2] = $this->createQueryBuilders(0, 0);

        $observedPageUid = null;
        $qb1
            ->method('setParameter')
            ->willReturnCallback(function (string $key, mixed $value) use (&$observedPageUid, $qb1) {
                if ($key === 'pageUid') {
                    $observedPageUid = $value;
                }
                return $qb1;
            });

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')->willReturn($qb1);

        $viewFactory = $this->createMock(ViewFactoryInterface::class);

        $event = $this->buildEvent($queryParams);
        $listener = new ModifyPageLayoutEventListener($languageService, $connectionPool, $viewFactory);

        $listener($event);

        self::assertSame(0, $observedPageUid);
    }

    #[Test]
    public function invokeRemovesHiddenRestrictionForTotalQueryButNotForReleasedQuery(): void
    {
        $languageService = $this->createLanguageServiceReturningKeys();
        [$qb1, $qb2, $restrictions1, $restrictions2] = $this->createQueryBuilders(3, 2);

        $restrictions1
            ->expects(self::once())
            ->method('removeByType')
            ->with(HiddenRestriction::class);
        $restrictions2
            ->expects(self::never())
            ->method('removeByType');

        $connectionPool = $this->createConnectionPool($qb1, $qb2);
        $viewFactory = $this->createViewFactoryCaptures();

        $event = $this->buildEvent(['id' => 17]);
        $listener = new ModifyPageLayoutEventListener($languageService, $connectionPool, $viewFactory);

        $listener($event);
    }

    private function createLanguageServiceReturningKeys(): LanguageService
    {
        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('sL')->willReturnArgument(0);
        return $languageService;
    }

    /**
     * @return array{0: QueryBuilder, 1: QueryBuilder, 2: QueryRestrictionContainerInterface, 3: QueryRestrictionContainerInterface}
     */
    private function createQueryBuilders(int $total, int $released): array
    {
        $result1 = $this->createMock(Result::class);
        $result1->method('fetchOne')->willReturn($total);
        $result2 = $this->createMock(Result::class);
        $result2->method('fetchOne')->willReturn($released);

        $restrictions1 = $this->createMock(QueryRestrictionContainerInterface::class);
        $restrictions2 = $this->createMock(QueryRestrictionContainerInterface::class);

        $qb1 = $this->createMock(QueryBuilder::class);
        $qb1->method('getRestrictions')->willReturn($restrictions1);
        $qb1->method('count')->willReturnSelf();
        $qb1->method('from')->willReturnSelf();
        $qb1->method('where')->willReturnSelf();
        $qb1->method('setParameter')->willReturnSelf();
        $qb1->method('executeQuery')->willReturn($result1);

        $qb2 = $this->createMock(QueryBuilder::class);
        $qb2->method('getRestrictions')->willReturn($restrictions2);
        $qb2->method('count')->willReturnSelf();
        $qb2->method('from')->willReturnSelf();
        $qb2->method('where')->willReturnSelf();
        $qb2->method('setParameter')->willReturnSelf();
        $qb2->method('executeQuery')->willReturn($result2);

        return [$qb1, $qb2, $restrictions1, $restrictions2];
    }

    private function createConnectionPool(QueryBuilder $qb1, QueryBuilder $qb2): ConnectionPool
    {
        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool
            ->method('getQueryBuilderForTable')
            ->with('tx_pwcomments_domain_model_comment')
            ->willReturnOnConsecutiveCalls($qb1, $qb2);
        return $connectionPool;
    }

    private function createViewFactoryCaptures(
        ?array &$capturedAssign = null,
        ?ViewFactoryData &$capturedFactoryData = null,
    ): ViewFactoryInterface {
        $view = $this->createMock(ViewInterface::class);
        $view
            ->method('assignMultiple')
            ->willReturnCallback(function (array $vars) use (&$capturedAssign, $view) {
                $capturedAssign = $vars;
                return $view;
            });
        $view->method('render')->willReturn(self::RENDER_MARKER);

        $viewFactory = $this->createMock(ViewFactoryInterface::class);
        $viewFactory
            ->method('create')
            ->willReturnCallback(function (ViewFactoryData $data) use (&$capturedFactoryData, $view) {
                $capturedFactoryData = $data;
                return $view;
            });

        return $viewFactory;
    }

    private function buildEvent(array $queryParams): ModifyPageLayoutContentEvent
    {
        $request = (new ServerRequest())->withQueryParams($queryParams);
        $moduleTemplate = (new \ReflectionClass(ModuleTemplate::class))->newInstanceWithoutConstructor();
        return new ModifyPageLayoutContentEvent($request, $moduleTemplate);
    }
}
