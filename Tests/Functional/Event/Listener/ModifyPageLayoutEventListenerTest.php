<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Functional\Event\Listener;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use T3\PwComments\Event\Listener\ModifyPageLayoutEventListener;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional regression test for issue #61: the BE page-module InfoBox is supposed
 * to report how many comments are still "unreleased" (hidden, awaiting moderation).
 *
 * Unlike the unit test, which mocks the restriction container, this drives the real
 * Doctrine DBAL QueryBuilder against a real database. That is the only way to prove
 * the actual behaviour of the two count queries: query 1 removes HiddenRestriction
 * (counts hidden + visible), query 2 keeps the default DefaultRestrictionContainer
 * (which includes HiddenRestriction, so it counts only visible/released comments).
 * unreleased = total - released therefore equals the number of hidden comments.
 */
final class ModifyPageLayoutEventListenerTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'backend',
        'fluid',
    ];

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/pw_comments',
    ];

    /**
     * @var array<string, ServerRequestInterface|null>
     */
    private array $globalsBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../../Fixtures/Database/ModifyPageLayout/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../../Fixtures/Database/ModifyPageLayout/comments.csv');

        // The listener resolves the InfoBox Fluid template through ViewFactoryInterface,
        // which falls back to the global request when none is passed explicitly.
        $this->globalsBackup['TYPO3_REQUEST'] = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $GLOBALS['TYPO3_REQUEST'] = $this->buildBackendRequest(1);
    }

    protected function tearDown(): void
    {
        if (array_key_exists('TYPO3_REQUEST', $this->globalsBackup)) {
            if ($this->globalsBackup['TYPO3_REQUEST'] === null) {
                unset($GLOBALS['TYPO3_REQUEST']);
            } else {
                $GLOBALS['TYPO3_REQUEST'] = $this->globalsBackup['TYPO3_REQUEST'];
            }
        }

        parent::tearDown();
    }

    /**
     * Page 1 holds 2 visible + 1 hidden comment. The header must report 3 total and
     * surface the single unreleased comment in its own bold block. A version where
     * both count queries behave identically (the bug claimed in #61) would compute
     * unreleased = 0 and omit that block entirely.
     */
    #[Test]
    public function invokeReportsHiddenCommentsAsUnreleased(): void
    {
        $event = $this->dispatchForPage(1);

        $header = $event->getHeaderContent();

        self::assertStringContainsString('This page contains 3 comments.', $header, 'Total must count hidden + visible comments.');
        self::assertStringContainsString('<br><b>', $header, 'The unreleased block must render when hidden comments exist.');
        self::assertStringContainsString('One comment remains unreleased!', $header, 'Exactly one hidden comment must be reported as unreleased.');
    }

    /**
     * Page 2 holds a single visible comment (and proves pid filtering: the hidden
     * comment lives on page 1 and must not leak in). With nothing awaiting
     * moderation, the unreleased block must not render at all.
     */
    #[Test]
    public function invokeOmitsUnreleasedBlockWhenNothingIsHidden(): void
    {
        $event = $this->dispatchForPage(2);

        $header = $event->getHeaderContent();

        self::assertStringContainsString('This page contains one comment.', $header, 'Only the page-2 comment must be counted (pid filtering).');
        self::assertStringNotContainsString('remains unreleased', $header, 'No unreleased text may render without hidden comments.');
        self::assertStringNotContainsString('<br><b>', $header, 'No unreleased block may render without hidden comments.');
    }

    private function dispatchForPage(int $pageId): ModifyPageLayoutContentEvent
    {
        $request = $this->buildBackendRequest($pageId);
        // The listener never dereferences the ModuleTemplate (it only reads the
        // request and calls setHeaderContent); a bare instance avoids having to
        // fake the routed BE request that ModuleTemplateFactory would require.
        $moduleTemplate = (new \ReflectionClass(ModuleTemplate::class))->newInstanceWithoutConstructor();
        $event = new ModifyPageLayoutContentEvent($request, $moduleTemplate);

        $this->buildListener()($event);

        return $event;
    }

    private function buildListener(): ModifyPageLayoutEventListener
    {
        return new ModifyPageLayoutEventListener(
            $this->buildLanguageService(),
            $this->get(ConnectionPool::class),
            $this->get(ViewFactoryInterface::class),
        );
    }

    private function buildLanguageService(): LanguageService
    {
        return $this->get(LanguageServiceFactory::class)->create('default');
    }

    private function buildBackendRequest(int $pageId): ServerRequestInterface
    {
        $request = (new ServerRequest('https://example.com/typo3/', 'GET'))
            ->withQueryParams(['id' => $pageId])
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        // The InfoBox template renders an icon, whose path publishing reads
        // normalizedParams off the (global) request; without it rendering throws.
        return $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));
    }
}
