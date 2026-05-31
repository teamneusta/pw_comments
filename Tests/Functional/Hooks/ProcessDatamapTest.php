<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use T3\PwComments\Hooks\ProcessDatamap;
use T3\PwComments\Utility\HashEncryptionUtility;
use T3\PwCommentsRequestFactoryDouble\FakeRequestRegistry;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for the BE-save trigger that pairs with the FE middleware
 * tested in Tests/Functional/Controller/CommentControllerTest.php. Workspaces
 * and translation overlays are out of scope - this hook fires on the default
 * language record.
 */
final class ProcessDatamapTest extends FunctionalTestCase
{
    private const COMMENTS_TABLE = 'tx_pwcomments_domain_model_comment';
    private const STATUS_UPDATE = 'update';

    protected array $coreExtensionsToLoad = [
        'install',
    ];

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/pw_comments',
        'typo3conf/ext/pw_comments/Tests/Fixtures/Extensions/pw_comments_request_factory_double',
    ];

    /**
     * @var array<string, ServerRequestInterface|null>
     */
    private array $globalsBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        FakeRequestRegistry::reset();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Database/ProcessDatamap/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Database/ProcessDatamap/comments.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Database/ProcessDatamap/be_users.csv');

        // The hook constructs FlashMessages with storeInSession=true; without a
        // BE user FlashMessageQueue::enqueue silently no-ops. A logged-in BE
        // user is the production precondition for this hook anyway - it only
        // fires during a BE save.
        $this->setUpBackendUser(1);

        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, 'https://example.com/');

        $this->activateTypoScript(['EXT:pw_comments/Tests/Fixtures/Frontend/ProcessDatamap/Active.typoscript']);

        $this->globalsBackup['TYPO3_REQUEST'] = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $GLOBALS['TYPO3_REQUEST'] = $this->buildRequestForPage(1);
    }

    protected function tearDown(): void
    {
        $this->flushFlashQueue();
        FakeRequestRegistry::reset();

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
     * The hook bails immediately for any of these guard conditions: no request
     * in globals, the saved record is still hidden, the wrong table or status
     * is in play, the comment cannot be loaded, or either TS flag is off. None
     * of them may produce an HTTP call, throw, or enqueue a flash.
     *
     * @return array<string, array{
     *     fieldArray: array<string, int|string>,
     *     table: string,
     *     status: string,
     *     uid: int,
     *     typoScript: list<string>,
     *     dropRequest: bool,
     * }>
     */
    public static function guardProvider(): array
    {
        $active = ['EXT:pw_comments/Tests/Fixtures/Frontend/ProcessDatamap/Active.typoscript'];

        return [
            'no TYPO3_REQUEST in globals' => [
                'fieldArray' => ['hidden' => 0],
                'table' => self::COMMENTS_TABLE,
                'status' => self::STATUS_UPDATE,
                'uid' => 10,
                'typoScript' => $active,
                'dropRequest' => true,
            ],
            'fieldArray hidden=1 keeps the row hidden' => [
                'fieldArray' => ['hidden' => 1],
                'table' => self::COMMENTS_TABLE,
                'status' => self::STATUS_UPDATE,
                'uid' => 10,
                'typoScript' => $active,
                'dropRequest' => false,
            ],
            'fieldArray hidden missing defaults to hidden' => [
                'fieldArray' => [],
                'table' => self::COMMENTS_TABLE,
                'status' => self::STATUS_UPDATE,
                'uid' => 10,
                'typoScript' => $active,
                'dropRequest' => false,
            ],
            'wrong table is ignored' => [
                'fieldArray' => ['hidden' => 0],
                'table' => 'tt_content',
                'status' => self::STATUS_UPDATE,
                'uid' => 10,
                'typoScript' => $active,
                'dropRequest' => false,
            ],
            'new status is ignored (only update fires the hook)' => [
                'fieldArray' => ['hidden' => 0],
                'table' => self::COMMENTS_TABLE,
                'status' => 'new',
                'uid' => 10,
                'typoScript' => $active,
                'dropRequest' => false,
            ],
            'unknown comment uid is silently dropped' => [
                'fieldArray' => ['hidden' => 0],
                'table' => self::COMMENTS_TABLE,
                'status' => self::STATUS_UPDATE,
                'uid' => 99999,
                'typoScript' => $active,
                'dropRequest' => false,
            ],
            'moderateNewComments=0 short-circuits' => [
                'fieldArray' => ['hidden' => 0],
                'table' => self::COMMENTS_TABLE,
                'status' => self::STATUS_UPDATE,
                'uid' => 10,
                'typoScript' => ['EXT:pw_comments/Tests/Fixtures/Frontend/ProcessDatamap/AuthorMailOnly.typoscript'],
                'dropRequest' => false,
            ],
            'sendMailToAuthorAfterPublish=0 short-circuits' => [
                'fieldArray' => ['hidden' => 0],
                'table' => self::COMMENTS_TABLE,
                'status' => self::STATUS_UPDATE,
                'uid' => 10,
                'typoScript' => ['EXT:pw_comments/Tests/Fixtures/Frontend/ProcessDatamap/ModerationOnly.typoscript'],
                'dropRequest' => false,
            ],
        ];
    }

    /**
     * @param array<string, int|string> $fieldArray
     * @param list<string> $typoScript
     */
    #[Test]
    #[DataProvider('guardProvider')]
    public function hookIsANoopForAnyGuardCondition(
        array $fieldArray,
        string $table,
        string $status,
        int $uid,
        array $typoScript,
        bool $dropRequest,
    ): void {
        $this->activateTypoScript($typoScript);
        if ($dropRequest) {
            unset($GLOBALS['TYPO3_REQUEST']);
        }

        $this->invokeHook($status, $table, $uid, $fieldArray);

        self::assertSame([], FakeRequestRegistry::$calls, 'No outbound HTTP request must be issued.');
        self::assertSame(0, $this->flashQueue()->count(), 'No flash message must be enqueued.');
    }

    /**
     * Happy path: when both TS flags are on and the saved comment flips to
     * visible, the hook builds the middleware URL (carrying the action, uid,
     * pid and HashEncryptionUtility hash) and enqueues an OK flash containing
     * the resolved (not raw LLL:) message with the author mail address.
     */
    #[Test]
    public function hookCallsMiddlewareUrlAndEnqueuesFlashOnSuccess(): void
    {
        $this->invokeHook(self::STATUS_UPDATE, self::COMMENTS_TABLE, 10, ['hidden' => 0]);

        self::assertCount(1, FakeRequestRegistry::$calls, 'Exactly one middleware call expected.');
        $call = FakeRequestRegistry::$calls[0];
        self::assertSame('GET', $call['method']);

        $expectedHash = HashEncryptionUtility::createHashForComment(
            $this->loadComment(10),
        );

        $query = $this->parseQueryString($call['url']);
        self::assertSame('sendAuthorMailWhenCommentHasBeenApproved', $query['tx_pwcomments']['action']);
        self::assertSame('10', $query['tx_pwcomments']['uid']);
        self::assertSame('1', $query['tx_pwcomments']['pid']);
        self::assertSame($expectedHash, $query['tx_pwcomments']['hash']);

        $messages = $this->flashQueue()->getAllMessages();
        self::assertCount(1, $messages);
        self::assertSame(ContextualFeedbackSeverity::OK, $messages[0]->getSeverity());
        self::assertStringContainsString('alice@example.com', $messages[0]->getMessage());
        self::assertStringNotContainsString('LLL:', $messages[0]->getMessage(), 'Translation key must resolve to actual text.');
    }

    /**
     * `getOrigPid() ?: getPid()` is the line most likely to regress when this
     * code is touched. Comment 11 has orig_pid=2 (child page) distinct from
     * pid=1 (root), so the link must route via orig_pid (where the comment
     * was created) rather than the current pid (where it now lives after a
     * move).
     *
     * Edge case orig_pid=0 is deliberately not covered: typoLink_URL with
     * `parameter=0` returns an empty string regardless of the fallback in
     * tx_pwcomments[pid], so that combination produces an unusable URL. This
     * is a latent bug not in scope for these tests.
     */
    #[Test]
    public function hookUsesOrigPidForLinkWhenItDiffersFromPid(): void
    {
        $this->invokeHook(self::STATUS_UPDATE, self::COMMENTS_TABLE, 11, ['hidden' => 0]);

        self::assertCount(1, FakeRequestRegistry::$calls);
        $query = $this->parseQueryString(FakeRequestRegistry::$calls[0]['url']);

        self::assertSame('2', $query['tx_pwcomments']['pid'], 'Link must carry orig_pid in tx_pwcomments[pid].');
        self::assertStringContainsString('/comments', FakeRequestRegistry::$calls[0]['url'], 'Link must route to the orig_pid page.');
    }

    /**
     * Replies have their own author distinct from the parent's. The hash and
     * link must be built for the reply itself; a refactor that walked up to
     * `parentComment` would notify the wrong person.
     */
    #[Test]
    public function hookNotifiesReplyAuthorNotParentAuthor(): void
    {
        $this->invokeHook(self::STATUS_UPDATE, self::COMMENTS_TABLE, 13, ['hidden' => 0]);

        self::assertCount(1, FakeRequestRegistry::$calls);
        $query = $this->parseQueryString(FakeRequestRegistry::$calls[0]['url']);

        self::assertSame('13', $query['tx_pwcomments']['uid'], 'Link must carry the reply uid, not the parent.');

        $expectedHash = HashEncryptionUtility::createHashForComment($this->loadComment(13));
        self::assertSame($expectedHash, $query['tx_pwcomments']['hash']);

        $messages = $this->flashQueue()->getAllMessages();
        self::assertCount(1, $messages);
        self::assertStringContainsString('dave@example.com', $messages[0]->getMessage());
        self::assertStringNotContainsString('alice@example.com', $messages[0]->getMessage(), 'Flash must mention the reply author, not the parent.');
    }

    /**
     * DataHandler serializes form values to strings, so `hidden` arrives as
     * the string '0'. The `(int)` cast in the guard must accept this -
     * otherwise a real BE save would silently no-op.
     */
    #[Test]
    public function hookAcceptsStringHiddenZeroFromDataHandler(): void
    {
        $this->invokeHook(self::STATUS_UPDATE, self::COMMENTS_TABLE, 10, ['hidden' => '0']);

        self::assertCount(1, FakeRequestRegistry::$calls, 'String "0" must be treated as unhide.');
    }

    /**
     * A non-200 middleware response (e.g. the comment was already visible by
     * the time the middleware checked) bubbles up as a RuntimeException with
     * the documented error code so the BE save aborts visibly.
     */
    #[Test]
    public function hookThrowsRuntimeExceptionWhenMiddlewareReturnsNon200(): void
    {
        FakeRequestRegistry::$nextStatus = 400;
        FakeRequestRegistry::$nextBody = '400';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(4620589602);

        $this->invokeHook(self::STATUS_UPDATE, self::COMMENTS_TABLE, 10, ['hidden' => 0]);
    }

    /**
     * Same exception when the middleware returned 200 but the body is empty
     * - the hook treats only the literal '200' body as success.
     */
    #[Test]
    public function hookThrowsRuntimeExceptionWhenResponseBodyIsEmpty(): void
    {
        FakeRequestRegistry::$nextStatus = 200;
        FakeRequestRegistry::$nextBody = '';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(4620589602);

        $this->invokeHook(self::STATUS_UPDATE, self::COMMENTS_TABLE, 10, ['hidden' => 0]);
    }

    /**
     * Smoke test for the SC_OPTIONS registration in ext_localconf.php: drives
     * a real DataHandler::process_datamap() to unhide fixture comment 14, and
     * asserts the fake RequestFactory was hit exactly once. This is the only
     * test that exercises the full TYPO3 plumbing - everything else invokes
     * the hook method directly.
     */
    #[Test]
    public function hookFiresThroughDataHandlerWhenCommentIsUnhidden(): void
    {
        $backendUser = $GLOBALS['BE_USER'];
        $GLOBALS['LANG'] = $this->get(\TYPO3\CMS\Core\Localization\LanguageServiceFactory::class)
            ->createFromUserPreferences($backendUser);

        $dataHandler = $this->get(DataHandler::class);
        $dataHandler->start(
            [self::COMMENTS_TABLE => [14 => ['hidden' => 0]]],
            [],
            $backendUser,
        );
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog, 'DataHandler reported errors: ' . implode("\n", $dataHandler->errorLog));
        self::assertCount(1, FakeRequestRegistry::$calls, 'Hook must fire exactly once through DataHandler.');

        $query = $this->parseQueryString(FakeRequestRegistry::$calls[0]['url']);
        self::assertSame('14', $query['tx_pwcomments']['uid']);
    }

    /**
     * @param array<string, int|string> $fieldArray
     */
    private function invokeHook(string $status, string $table, int $uid, array $fieldArray): void
    {
        $hook = $this->get(ProcessDatamap::class);
        $hook->processDatamap_postProcessFieldArray($status, $table, $uid, $fieldArray);
    }

    /**
     * @param list<string> $typoScriptFiles
     */
    private function activateTypoScript(array $typoScriptFiles): void
    {
        $this->setUpFrontendRootPage(1, $typoScriptFiles);
        $runtimeCache = $this->get(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('runtime');
        $runtimeCache->flush();
    }

    private function buildRequestForPage(int $pageId): ServerRequestInterface
    {
        return (new ServerRequest('https://example.com/typo3/', 'GET'))
            ->withQueryParams(['id' => $pageId])
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    private function loadComment(int $uid): \T3\PwComments\Domain\Model\Comment
    {
        $repository = $this->get(\T3\PwComments\Domain\Repository\CommentRepository::class);
        $comment = $repository->findByCommentUid($uid);
        self::assertNotNull($comment, 'Fixture comment ' . $uid . ' missing.');
        return $comment;
    }

    private function flashQueue(): FlashMessageQueue
    {
        return $this->get(FlashMessageService::class)
            ->getMessageQueueByIdentifier(FlashMessageQueue::NOTIFICATION_QUEUE);
    }

    private function flushFlashQueue(): void
    {
        $this->flashQueue()->getAllMessagesAndFlush();
    }

    /**
     * @return array<string, array<string, string>|string>
     */
    private function parseQueryString(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY) ?: '';
        parse_str($query, $parsed);
        return $parsed;
    }
}
