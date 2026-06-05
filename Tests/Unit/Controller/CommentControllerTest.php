<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use T3\PwComments\Controller\CommentController;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Domain\Model\FrontendUser;
use T3\PwComments\Domain\Model\Vote;
use T3\PwComments\Domain\Repository\CommentRepository;
use T3\PwComments\Domain\Repository\FrontendUserRepository;
use T3\PwComments\Domain\Repository\VoteRepository;
use T3\PwComments\Service\Moderation\ModerationProviderFactory;
use T3\PwComments\Service\Moderation\ModerationResult;
use T3\PwComments\Service\Moderation\ModerationServiceInterface;
use T3\PwComments\Utility\Cookie;
use T3\PwComments\Utility\HashEncryptionUtility;
use T3\PwComments\Utility\Mail;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Page\PageInformation;

final class CommentControllerTest extends TestCase
{
    private CommentController $controller;
    private MockObject $moderationProviderFactory;
    private MockObject $mailUtility;
    private MockObject $cookieUtility;
    private MockObject $commentRepository;
    private MockObject $frontendUserRepository;
    private MockObject $voteRepository;
    private MockObject $viewFactory;
    private MockObject $request;
    private MockObject $uriBuilder;
    private MockObject $view;
    private MockObject $logger;
    private FlashMessageQueue $flashMessageQueue;
    private ?string $encryptionKeyBackup = null;

    protected function setUp(): void
    {
        $this->moderationProviderFactory = $this->createMock(ModerationProviderFactory::class);
        $this->mailUtility = $this->createMock(Mail::class);
        $this->cookieUtility = $this->createMock(Cookie::class);
        $this->commentRepository = $this->createMock(CommentRepository::class);
        $this->frontendUserRepository = $this->createMock(FrontendUserRepository::class);
        $this->voteRepository = $this->createMock(VoteRepository::class);
        $this->viewFactory = $this->createMock(ViewFactoryInterface::class);
        $this->view = $this->createMock(ViewInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new CommentController(
            $this->moderationProviderFactory,
            $this->mailUtility,
            $this->cookieUtility,
            $this->commentRepository,
            $this->frontendUserRepository,
            $this->voteRepository,
            $this->viewFactory,
        );

        // Inject logger
        $this->controller->setLogger($this->logger);

        // Setup request mock
        $this->request = $this->createMock(Request::class);
        $this->uriBuilder = $this->createMock(UriBuilder::class);

        // Real PSR-17 factories so htmlResponse()/jsonResponse() can build responses
        $responseFactory = new ResponseFactory();
        $streamFactory = new StreamFactory();

        // Use reflection to inject private properties
        $this->injectProperty('request', $this->request);
        $this->injectProperty('uriBuilder', $this->uriBuilder);
        $this->injectProperty('view', $this->view);
        $this->injectProperty('responseFactory', $responseFactory);
        $this->injectProperty('streamFactory', $streamFactory);

        // Capture flash messages emitted via addFlashMessage() so tests can
        // inspect them without standing up the full FlashMessageService stack.
        $this->flashMessageQueue = new FlashMessageQueue('test');
        $flashMessageService = $this->createMock(FlashMessageService::class);
        $flashMessageService->method('getMessageQueueByIdentifier')->willReturn($this->flashMessageQueue);
        $this->injectProperty('internalFlashMessageService', $flashMessageService);

        $extensionService = $this->createMock(ExtensionService::class);
        $extensionService->method('getPluginNamespace')->willReturn('tx_pwcomments_show');
        $this->injectProperty('internalExtensionService', $extensionService);

        // HashEncryptionUtility::createHashForComment() reads this; without it
        // it throws on every confirmCommentAction test.
        $this->encryptionKeyBackup = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'unit-test-key';
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        GeneralUtility::resetSingletonInstances([]);
        if ($this->encryptionKeyBackup === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $this->encryptionKeyBackup;
        }
        parent::tearDown();
    }

    /**
     * Registers a stub `LanguageServiceFactory` so `LocalizationUtility::translate()`
     * returns the lookup key (non-null string) instead of crashing because the
     * factory's real constructor needs Locales / LocalizationFactory.
     *
     * Pass `$times` to register the stub for that many `makeInstance()` calls —
     * each `translate()` consumes one entry.
     */
    private function registerLanguageServiceFactoryStub(int $times = 1): void
    {
        $locale = new Locale('en');

        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('translate')->willReturnArgument(0);
        $languageService->method('sL')->willReturnArgument(0);
        $languageService->method('getLocale')->willReturn($locale);

        $factory = $this->createMock(LanguageServiceFactory::class);
        $factory->method('create')->willReturn($languageService);
        $factory->method('createFromUserPreferences')->willReturn($languageService);
        $factory->method('createFromSiteLanguage')->willReturn($languageService);

        $locales = $this->createMock(Locales::class);
        $locales->method('createLocaleFromRequest')->willReturn($locale);
        $locales->method('createLocale')->willReturn($locale);
        GeneralUtility::setSingletonInstance(Locales::class, $locales);

        for ($i = 0; $i < $times; $i++) {
            GeneralUtility::addInstance(LanguageServiceFactory::class, $factory);
        }
    }

    public function testInitializeActionSetsPageUidFromRequest(): void
    {
        $pageUid = 42;
        $settings = [
            'entryUid' => '123',
            'useEntryUid' => true,
        ];

        $this->configureRequestAttributes($pageUid);

        $this->cookieUtility->expects(self::once())
            ->method('get')
            ->with('ahash')
            ->willReturn(null);

        $this->injectProperty('settings', $settings);
        $this->controller->initializeAction();

        // Verify by checking internal state through indexAction
        $comments = $this->createMock(QueryResult::class);
        $comments->method('count')->willReturn(0);
        $comments->method('toArray')->willReturn([]);

        $this->commentRepository->expects(self::once())
            ->method('findByPidAndEntryUid')
            ->with(
                self::equalTo($pageUid),
                self::equalTo(123),
            )
            ->willReturn($comments);

        $this->view->expects(self::exactly(5))
            ->method('assign')
            ->willReturn($this->view);

        $this->controller->indexAction();
    }

    /**
     * Security guard: a numeric `ahash` cookie on an anonymous request must
     * be wiped so it cannot impersonate a registered FE user (whose
     * `currentAuthorIdent` is the numeric uid). The functional layer can't
     * exercise this — `Cookie::get()` reads `$_COOKIE`, which the testing
     * framework does not propagate into sub-requests.
     */
    public function testInitializeActionClearsNumericCookieAhashWhenUserIsNotLoggedIn(): void
    {
        $pageUid = 1;
        $this->configureRequestAttributes($pageUid);

        $this->cookieUtility->expects(self::once())
            ->method('get')
            ->with('ahash')
            ->willReturn('42');

        $this->injectProperty('settings', ['_skipMakingSettingsRenderable' => true]);
        $this->controller->initializeAction();

        self::assertNull($this->readProperty('currentAuthorIdent'));
    }

    /**
     * Positive twin of the numeric-clearing guard: a non-numeric (hashed)
     * cookie value must survive `initializeAction` untouched so anonymous
     * voting and reply-tracking continue to work across requests.
     */
    public function testInitializeActionPreservesNonNumericCookieAhash(): void
    {
        $pageUid = 1;
        $this->configureRequestAttributes($pageUid);

        $this->cookieUtility->expects(self::once())
            ->method('get')
            ->with('ahash')
            ->willReturn('abc123hashvalue');

        $this->injectProperty('settings', ['_skipMakingSettingsRenderable' => true]);
        $this->controller->initializeAction();

        self::assertSame('abc123hashvalue', $this->readProperty('currentAuthorIdent'));
    }

    /**
     * Security symmetry with testInitializeActionClearsNumericCookieAhash...:
     * a logged-in FE user must have currentAuthorIdent set to their uid as a
     * string, regardless of any prior cookie value.
     */
    #[Test]
    public function initializeActionSetsCurrentAuthorIdentToFrontendUserUidWhenLoggedIn(): void
    {
        $pageUid = 1;
        $this->configureRequestAttributes($pageUid, $this->createFrontendUserAuth(['uid' => 42]));

        $this->cookieUtility->expects(self::any())->method('get')->willReturn(null);

        $this->injectProperty('settings', ['_skipMakingSettingsRenderable' => true]);
        $this->controller->initializeAction();

        self::assertSame('42', $this->readProperty('currentAuthorIdent'));
    }

    #[Test]
    public function indexActionUsesFindByPidAndEntryUidWhenEntryUidIsPositive(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, []);
        $this->injectProperty('entryUid', 7);

        $comments = $this->createMock(QueryResult::class);
        $comments->method('count')->willReturn(0);
        $comments->method('toArray')->willReturn([]);

        $this->commentRepository->expects(self::once())
            ->method('findByPidAndEntryUid')
            ->with($pageUid, 7)
            ->willReturn($comments);
        $this->commentRepository->expects(self::never())->method('findByPid');

        $this->view->method('assign')->willReturnSelf();

        $this->controller->indexAction();
    }

    public function testIndexActionReturnsCommentsForCurrentPage(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, []);

        $comments = $this->createMock(QueryResult::class);
        $comments->method('count')->willReturn(2);
        $comments->method('toArray')->willReturn([
            $this->createComment(1, 'Test comment 1'),
            $this->createComment(2, 'Test comment 2'),
        ]);

        $this->commentRepository->expects(self::once())
            ->method('findByPid')
            ->with($pageUid)
            ->willReturn($comments);

        $this->view->expects(self::exactly(5))
            ->method('assign')
            ->willReturnCallback(function ($key, $value) use ($comments) {
                if ($key === 'comments') {
                    self::assertSame($comments, $value);
                } elseif ($key === 'commentCount') {
                    self::assertSame(2, $value);
                } elseif ($key === 'upvotedCommentUids') {
                    self::assertSame([], $value);
                } elseif ($key === 'downvotedCommentUids') {
                    self::assertSame([], $value);
                } elseif ($key === 'commentToReplyTo') {
                    self::assertNull($value);
                }
                return $this->view;
            });

        $response = $this->controller->indexAction();
        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testIndexActionFiltersVotedComments(): void
    {
        $pageUid = 10;
        $authorIdent = 'test-author-123';

        $this->setupControllerWithSettings($pageUid, [], $authorIdent);

        $comments = $this->createMock(QueryResult::class);
        $comments->method('count')->willReturn(0);
        $comments->method('toArray')->willReturn([]);

        $this->commentRepository->expects(self::once())
            ->method('findByPid')
            ->willReturn($comments);

        $comment1 = $this->createComment(1, 'Test');
        $comment2 = $this->createComment(2, 'Test');

        $upvote = $this->createVote(1, $comment1, Vote::TYPE_UPVOTE);
        $downvote = $this->createVote(2, $comment2, Vote::TYPE_DOWNVOTE);

        $votes = $this->createQueryResult([$upvote, $downvote]);

        $this->voteRepository->expects(self::once())
            ->method('findByPidAndAuthorIdent')
            ->with($pageUid, $authorIdent)
            ->willReturn($votes);

        $this->view->expects(self::exactly(5))
            ->method('assign')
            ->willReturnCallback(function ($key, $value) {
                if ($key === 'upvotedCommentUids') {
                    self::assertSame([1], $value);
                } elseif ($key === 'downvotedCommentUids') {
                    self::assertSame([2], $value);
                }
                return $this->view;
            });

        $this->controller->indexAction();
    }

    public function testNewActionAssignsVariablesToView(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, []);

        $newComment = $this->createComment(0, 'New comment');
        $commentToReplyTo = $this->createComment(5, 'Parent comment');

        $frontendUserAuth = $this->createFrontendUserAuth([]);
        $frontendUserAuth->expects(self::any())
            ->method('getKey')
            ->willReturnCallback(function ($type, $key) {
                if ($key === 'tx_pwcomments_unregistredUserName') {
                    return 'John Doe';
                }
                if ($key === 'tx_pwcomments_unregistredUserMail') {
                    return 'john@example.com';
                }
                return null;
            });

        // Override the request mock for this specific test
        $request = $this->createMock(Request::class);
        $request->expects(self::any())
            ->method('getAttribute')
            ->willReturn($frontendUserAuth);
        $this->injectProperty('request', $request);

        $this->view->expects(self::exactly(4))
            ->method('assign')
            ->willReturnCallback(function ($key, $value) use ($newComment, $commentToReplyTo) {
                if ($key === 'newComment') {
                    self::assertSame($newComment, $value);
                } elseif ($key === 'commentToReplyTo') {
                    self::assertSame($commentToReplyTo, $value);
                } elseif ($key === 'unregistredUserName') {
                    self::assertSame('John Doe', $value);
                } elseif ($key === 'unregistredUserMail') {
                    self::assertSame('john@example.com', $value);
                }
                return $this->view;
            });

        $response = $this->controller->newAction($newComment, $commentToReplyTo);
        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function createActionRedirectsWhenHoneypotFieldIsFilled(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'hiddenFieldSpamProtection' => true,
            'hiddenFieldName' => 'website',
            'writeCommentAnchor' => 'write',
        ]);
        $this->stubUriBuilder();
        $this->request->method('hasArgument')->with('website')->willReturn(true);
        $this->request->method('getArgument')->with('website')->willReturn('http://spam.example');

        $this->commentRepository->expects(self::never())->method('add');
        $this->commentRepository->expects(self::never())->method('persistAll');

        $response = $this->controller->createAction($this->createMock(Comment::class));
        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('#write', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createActionRedirectsWhenNoCommentProvided(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, []);
        $this->stubUriBuilder();

        $this->commentRepository->expects(self::never())->method('add');
        $this->commentRepository->expects(self::never())->method('persistAll');

        $response = $this->controller->createAction(null);
        self::assertSame(303, $response->getStatusCode());
    }

    #[Test]
    public function createActionPersistsCommentAndFlashesThanksOnHappyPath(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'linkUrlsInComments' => false,
            'commentAnchorPrefix' => 'c',
        ], 'existing-ident');
        $this->stubUriBuilder();
        $this->registerLanguageServiceFactoryStub();

        $newComment = new Comment();
        // <b> proves htmlspecialchars ran; leading/trailing whitespace proves trim ran;
        // these together exercise StringUtility::prepareCommentMessage end-to-end.
        $newComment->setMessage('  <b>hi</b>  ');
        $newComment->setAuthorName('Bob');
        $newComment->setAuthorMail('bob@example.com');

        $this->commentRepository->expects(self::once())->method('add')->with($newComment);
        $this->commentRepository->expects(self::once())->method('persistAll');

        $response = $this->controller->createAction($newComment);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame($pageUid, $newComment->getPid());
        self::assertSame($pageUid, $newComment->getOrigPid());
        self::assertSame('existing-ident', $newComment->getAuthorIdent());
        self::assertSame('&lt;b&gt;hi&lt;/b&gt;', $newComment->getMessage());
    }

    #[Test]
    public function createActionHidesAndFlashesModerationNoticeWhenModerationEnabled(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'moderateNewComments' => true,
            'commentAnchorPrefix' => 'c',
            'successfulAnchor' => 'success',
        ], 'existing-ident');
        $this->stubUriBuilder();
        $this->registerLanguageServiceFactoryStub();

        $newComment = new Comment();
        $newComment->setMessage('safe');

        $this->commentRepository->expects(self::once())->method('add');
        $this->commentRepository->expects(self::once())->method('persistAll');

        $response = $this->controller->createAction($newComment);

        self::assertTrue($newComment->getHidden());
        self::assertStringContainsString('#success', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createActionFlagsAndHidesWhenAiModerationDetectsViolation(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'enableAiModeration' => true,
            'aiModerationProvider' => 'openai',
            'commentAnchorPrefix' => 'c',
            'successfulAnchor' => 'success',
        ], 'existing-ident');
        $this->stubUriBuilder();
        $this->registerLanguageServiceFactoryStub();

        $service = $this->createMock(ModerationServiceInterface::class);
        $service->method('moderateComment')->willReturn(
            new ModerationResult(true, ['harassment'], ['harassment' => 0.92], 'harassment', 0.92),
        );
        $this->moderationProviderFactory->expects(self::once())
            ->method('createProvider')
            ->with('openai', self::isType('array'))
            ->willReturn($service);

        $newComment = new Comment();
        $newComment->setMessage('toxic');

        $response = $this->controller->createAction($newComment);

        self::assertTrue($newComment->getHidden());
        self::assertSame('flagged', $newComment->getAiModerationStatus());
        self::assertSame('harassment', $newComment->getAiModerationReason());
        self::assertSame(0.92, $newComment->getAiModerationConfidence());
        self::assertStringContainsString('#success', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createActionMarksApprovedWhenAiModerationPassesAndModerationOff(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'enableAiModeration' => true,
            'aiModerationProvider' => 'openai',
            'commentAnchorPrefix' => 'c',
        ], 'existing-ident');
        $this->stubUriBuilder();
        $this->registerLanguageServiceFactoryStub();

        $service = $this->createMock(ModerationServiceInterface::class);
        $service->method('moderateComment')->willReturn(
            new ModerationResult(false, [], [], '', 0.05),
        );
        $this->moderationProviderFactory->method('createProvider')->willReturn($service);

        $newComment = new Comment();
        $newComment->setMessage('hi');
        $newComment->setHidden(false);

        $response = $this->controller->createAction($newComment);

        self::assertFalse($newComment->getHidden());
        self::assertSame('approved', $newComment->getAiModerationStatus());
        self::assertSame(0.05, $newComment->getAiModerationConfidence());
        self::assertSame(303, $response->getStatusCode());
    }

    #[Test]
    public function createActionFallsBackToNormalFlowWhenAiThrowsAndFallbackEnabled(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'enableAiModeration' => true,
            'aiModerationProvider' => 'openai',
            'aiModerationFallbackToManual' => true,
            'commentAnchorPrefix' => 'c',
        ], 'existing-ident');
        $this->stubUriBuilder();
        $this->registerLanguageServiceFactoryStub();

        $service = $this->createMock(ModerationServiceInterface::class);
        $service->method('moderateComment')->willThrowException(new \RuntimeException('upstream 503'));
        $this->moderationProviderFactory->method('createProvider')->willReturn($service);

        $this->logger->expects(self::once())
            ->method('error')
            ->with('AI moderation service failed', self::callback(
                static fn(array $ctx): bool => $ctx['exception'] instanceof \RuntimeException,
            ));

        $this->commentRepository->expects(self::once())->method('add');
        $this->commentRepository->expects(self::once())->method('persistAll');

        $newComment = new Comment();
        $newComment->setMessage('hi');
        $newComment->setHidden(false);

        $response = $this->controller->createAction($newComment);

        self::assertSame('error', $newComment->getAiModerationStatus());
        self::assertStringContainsString('upstream 503', $newComment->getAiModerationReason());
        self::assertFalse($newComment->getHidden());
        self::assertSame(303, $response->getStatusCode());
    }

    #[Test]
    public function createActionRethrowsWhenAiThrowsAndNoFallback(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'enableAiModeration' => true,
            'aiModerationProvider' => 'openai',
            'aiModerationFallbackToManual' => false,
        ], 'existing-ident');
        $this->stubUriBuilder();

        $service = $this->createMock(ModerationServiceInterface::class);
        $service->method('moderateComment')->willThrowException(new \RuntimeException('upstream 503'));
        $this->moderationProviderFactory->method('createProvider')->willReturn($service);

        $this->commentRepository->expects(self::never())->method('add');

        $newComment = new Comment();
        $newComment->setMessage('hi');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('upstream 503');
        $this->controller->createAction($newComment);
    }

    #[Test]
    public function createActionSetsAuthorFromRepositoryWhenLoggedIn(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'commentAnchorPrefix' => 'c',
        ], 'existing-ident');
        $this->injectProperty('currentUser', ['uid' => 42]);
        $this->stubUriBuilder();
        $this->registerLanguageServiceFactoryStub();

        $author = $this->createMock(FrontendUser::class);
        $this->frontendUserRepository->expects(self::once())
            ->method('findByUid')
            ->with(42)
            ->willReturn($author);

        $newComment = new Comment();
        $newComment->setMessage('hi');

        $this->controller->createAction($newComment);

        self::assertSame($author, $newComment->getAuthor());
    }

    #[Test]
    public function createActionStashesSessionKeysForAnonymousUser(): void
    {
        $pageUid = 10;

        $frontendUser = $this->createFrontendUserAuth([]);
        $sessionKeys = [];
        $frontendUser->method('setKey')
            ->willReturnCallback(function ($scope, $key, $value) use (&$sessionKeys): void {
                $sessionKeys[$key] = $value;
            });

        $this->setupControllerWithSettings($pageUid, [
            'commentAnchorPrefix' => 'c',
        ], 'existing-ident', $frontendUser);
        $this->stubUriBuilder();
        $this->registerLanguageServiceFactoryStub();

        $newComment = new Comment();
        $newComment->setMessage('hi');
        $newComment->setAuthorName('Bob');
        $newComment->setAuthorMail('bob@example.com');

        $this->controller->createAction($newComment);

        self::assertSame('Bob', $sessionKeys['tx_pwcomments_unregistredUserName']);
        self::assertSame('bob@example.com', $sessionKeys['tx_pwcomments_unregistredUserMail']);
        self::assertArrayHasKey('tx_pwcomments_lastComment', $sessionKeys);
        self::assertIsInt($sessionKeys['tx_pwcomments_lastComment']);
    }

    #[Test]
    public function confirmCommentRedirectsWithErrorWhenCommentNotFound(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, []);
        $this->stubUriBuilder();
        $this->registerLanguageServiceFactoryStub();

        $this->commentRepository->expects(self::once())
            ->method('findByCommentUid')
            ->with(99)
            ->willReturn(null);
        $this->commentRepository->expects(self::never())->method('update');
        $this->commentRepository->expects(self::never())->method('persistAll');

        $response = $this->controller->confirmCommentAction(99, 'anything');
        self::assertSame(303, $response->getStatusCode());
    }

    #[Test]
    public function confirmCommentRedirectsWithErrorWhenHashInvalid(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, []);
        $this->stubUriBuilder();
        $this->registerLanguageServiceFactoryStub();

        $comment = new Comment();
        $comment->setMessage('body');
        $comment->setHidden(true);
        $this->commentRepository->method('findByCommentUid')->willReturn($comment);
        $this->commentRepository->expects(self::never())->method('update');
        $this->commentRepository->expects(self::never())->method('persistAll');

        $response = $this->controller->confirmCommentAction(1, 'definitely-not-the-real-hash');
        self::assertSame(303, $response->getStatusCode());
    }

    #[Test]
    public function confirmCommentRedirectsWithErrorWhenAlreadyVisible(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, []);
        $this->stubUriBuilder();
        $this->registerLanguageServiceFactoryStub();

        $comment = new Comment();
        $comment->setMessage('body');
        $comment->setHidden(false);
        $validHash = HashEncryptionUtility::createHashForComment($comment);

        $this->commentRepository->method('findByCommentUid')->willReturn($comment);
        $this->commentRepository->expects(self::never())->method('update');
        $this->commentRepository->expects(self::never())->method('persistAll');

        $response = $this->controller->confirmCommentAction(1, $validHash);
        self::assertSame(303, $response->getStatusCode());
    }

    #[Test]
    public function confirmCommentUnhidesPersistsAndFlashesOnHappyPath(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, []);
        $this->stubUriBuilder();
        $this->registerLanguageServiceFactoryStub();

        $comment = new Comment();
        $comment->setMessage('body');
        $comment->setHidden(true);
        $validHash = HashEncryptionUtility::createHashForComment($comment);

        $this->commentRepository->method('findByCommentUid')->willReturn($comment);
        $this->commentRepository->expects(self::once())->method('update')->with($comment);
        $this->commentRepository->expects(self::once())->method('persistAll');

        $response = $this->controller->confirmCommentAction(1, $validHash);

        self::assertFalse($comment->getHidden());
        self::assertSame(303, $response->getStatusCode());
    }

    // Note: confirmCommentAction's moderate+sendMailToAuthorAfterPublish branch
    // calls makeFluidTemplateObject() which builds a real Fluid view chain via
    // viewFactory. Wiring that into unit-test mocks is brittle; covered by
    // functional tests instead (alongside Tests/Functional/Hooks/ProcessDatamapTest).

    public function testUpvoteActionCallsPerformVoting(): void
    {
        $pageUid = 10;
        $settings = ['enableVoting' => true, 'commentAnchorPrefix' => 'c'];
        $this->setupControllerWithSettings($pageUid, $settings, 'author-123');

        $comment = $this->createComment(5, 'Test comment');

        $this->voteRepository->expects(self::once())
            ->method('findOneByCommentAndAuthorIdent')
            ->with($comment, 'author-123')
            ->willReturn(null);

        $this->commentRepository->expects(self::once())
            ->method('update')
            ->with($comment);

        $this->commentRepository->expects(self::once())
            ->method('persistAll');

        $response = $this->controller->upvoteAction($comment);
        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testDownvoteActionCallsPerformVoting(): void
    {
        $pageUid = 10;
        $settings = ['enableVoting' => true, 'commentAnchorPrefix' => 'c'];
        $this->setupControllerWithSettings($pageUid, $settings, 'author-123');

        $comment = $this->createComment(5, 'Test comment');

        $this->voteRepository->expects(self::once())
            ->method('findOneByCommentAndAuthorIdent')
            ->with($comment, 'author-123')
            ->willReturn(null);

        $this->commentRepository->expects(self::once())
            ->method('update')
            ->with($comment);

        $this->commentRepository->expects(self::once())
            ->method('persistAll');

        $response = $this->controller->downvoteAction($comment);
        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function performVotingRedirectsWithDoNotVoteForYourselfWhenVotingOwnComment(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'enableVoting' => true,
            'ignoreVotingForOwnComments' => true,
            'commentAnchorPrefix' => 'c',
        ], 'me');
        $this->stubUriBuilder();

        $comment = $this->createComment(5, 'mine');
        $comment->method('getAuthorIdent')->willReturn('me');

        $this->voteRepository->expects(self::never())->method('findOneByCommentAndAuthorIdent');
        $this->commentRepository->expects(self::never())->method('update');
        $this->commentRepository->expects(self::never())->method('persistAll');

        $response = $this->controller->upvoteAction($comment);
        self::assertSame(303, $response->getStatusCode());
    }

    #[Test]
    public function performVotingCreatesNewVoteWhenNoneExistsForAuthor(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'enableVoting' => true,
            'commentAnchorPrefix' => 'c',
        ], 'someone');

        $comment = $this->createComment(5, 'whatever');
        $comment->method('getAuthorIdent')->willReturn('someone-else');
        $comment->expects(self::once())->method('addVote')->with(self::isInstanceOf(Vote::class));

        // createNewVote() uses GeneralUtility::makeInstance(Vote::class) — pre-seed the queue.
        GeneralUtility::addInstance(Vote::class, new Vote());

        $this->voteRepository->expects(self::once())
            ->method('findOneByCommentAndAuthorIdent')
            ->with($comment, 'someone')
            ->willReturn(null);

        $this->commentRepository->expects(self::once())->method('update')->with($comment);
        $this->commentRepository->expects(self::once())->method('persistAll');

        $this->controller->upvoteAction($comment);
    }

    #[Test]
    public function performVotingRemovesExistingVoteOfSameTypeWithoutRecursing(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'enableVoting' => true,
            'commentAnchorPrefix' => 'c',
        ], 'someone');

        $comment = $this->createComment(5, 'whatever');
        $comment->method('getAuthorIdent')->willReturn('someone-else');
        $comment->expects(self::never())->method('addVote');
        $existingVote = $this->createVote(99, $comment, Vote::TYPE_UPVOTE);
        $comment->expects(self::once())->method('removeVote')->with($existingVote);

        $this->voteRepository->expects(self::once())
            ->method('findOneByCommentAndAuthorIdent')
            ->willReturn($existingVote);
        $this->voteRepository->expects(self::once())->method('remove')->with($existingVote);

        $this->commentRepository->expects(self::once())->method('update')->with($comment);
        $this->commentRepository->expects(self::once())->method('persistAll');

        $this->controller->upvoteAction($comment);
    }

    public function testVotingIsSkippedWhenDisabled(): void
    {
        $pageUid = 10;
        $settings = ['enableVoting' => false];
        $this->setupControllerWithSettings($pageUid, $settings);

        $comment = $this->createComment(5, 'Test comment');

        $this->commentRepository->expects(self::never())
            ->method('update');

        $response = $this->controller->upvoteAction($comment);
        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testCalculateCommentCountWithoutReplies(): void
    {
        $pageUid = 10;
        $settings = ['countReplies' => false];
        $this->setupControllerWithSettings($pageUid, $settings);

        $comments = $this->createMock(QueryResult::class);
        $comments->method('count')->willReturn(3);
        $comments->method('toArray')->willReturn([
            $this->createComment(1, 'C1'),
            $this->createComment(2, 'C2'),
            $this->createComment(3, 'C3'),
        ]);

        $this->commentRepository->expects(self::once())
            ->method('findByPid')
            ->willReturn($comments);

        $this->view->expects(self::exactly(5))
            ->method('assign')
            ->willReturnCallback(function ($key, $value) {
                if ($key === 'commentCount') {
                    self::assertSame(3, $value);
                }
                return $this->view;
            });

        $this->controller->indexAction();
    }

    public function testCalculateCommentCountWithReplies(): void
    {
        $pageUid = 10;
        $settings = ['countReplies' => true];
        $this->setupControllerWithSettings($pageUid, $settings);

        $comment1 = $this->createComment(1, 'C1', [
            $this->createComment(3, 'R1'),
            $this->createComment(4, 'R2'),
        ]);
        $comment2 = $this->createComment(2, 'C2');

        $comments = $this->createQueryResult([$comment1, $comment2], QueryResult::class);

        $this->commentRepository->expects(self::once())
            ->method('findByPid')
            ->willReturn($comments);

        $this->view->expects(self::exactly(5))
            ->method('assign')
            ->willReturnCallback(function ($key, $value) {
                if ($key === 'commentCount') {
                    self::assertSame(4, $value);
                }
                return $this->view;
            });

        $this->controller->indexAction();
    }

    public function testBuildUriByUidPreservesUntrustedQueryStringAndExcludesPluginParameters(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, []);

        // Issue #40: links must carry over foreign GET parameters. Since TYPO3 v12
        // arbitrary query parameters are only preserved with "untrusted", and the
        // plugin's own parameters to exclude are derived from the active plugin name.
        $this->request->method('getPluginName')->willReturn('show');

        $this->uriBuilder->expects(self::once())
            ->method('reset')
            ->willReturnSelf();
        $this->uriBuilder->expects(self::once())
            ->method('setTargetPageUid')
            ->with($pageUid)
            ->willReturnSelf();
        $this->uriBuilder->expects(self::once())
            ->method('setAddQueryString')
            ->with(self::identicalTo('untrusted'))
            ->willReturnSelf();
        $this->uriBuilder->expects(self::once())
            ->method('setArgumentsToBeExcludedFromQueryString')
            ->with([
                'tx_pwcomments_show[action]',
                'tx_pwcomments_show[controller]',
                'tx_pwcomments_show[hash]',
                'cHash',
            ])
            ->willReturnSelf();
        $this->uriBuilder->expects(self::once())
            ->method('setArguments')
            ->with([])
            ->willReturnSelf();
        $this->uriBuilder->expects(self::once())
            ->method('build')
            ->willReturn('/page-10');

        // Trigger buildUriByUid through createAction with null comment (early return)
        $this->controller->createAction();
    }

    #[Test]
    public function sendAuthorMailWhenCommentHasBeenApprovedActionIsNoOpWhenAfterPublishFlagOff(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'moderateNewComments' => true,
            'sendMailToAuthorAfterPublish' => false,
            '_commentUid' => 7,
        ]);

        $this->commentRepository->expects(self::once())
            ->method('findByCommentUid')
            ->with(7)
            ->willReturn(new Comment());

        $this->mailUtility->expects(self::never())->method('sendMail');

        $response = $this->controller->sendAuthorMailWhenCommentHasBeenApprovedAction();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function createNewVoteSetsAuthorFromRepositoryWhenFrontendUserPresent(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [], 'ident-x');
        $this->injectProperty('currentUser', ['uid' => 42]);

        $author = $this->createMock(FrontendUser::class);
        $this->frontendUserRepository->expects(self::once())
            ->method('findByUid')
            ->with(42)
            ->willReturn($author);

        GeneralUtility::addInstance(Vote::class, new Vote());
        $comment = new Comment();

        $method = new \ReflectionMethod($this->controller, 'createNewVote');
        /** @var Vote $vote */
        $vote = $method->invoke($this->controller, Vote::TYPE_UPVOTE, $comment);

        self::assertSame($author, $vote->getAuthor());
        self::assertSame('ident-x', $vote->getAuthorIdent());
        self::assertSame(Vote::TYPE_UPVOTE, $vote->getType());
        self::assertSame($comment, $vote->getComment());
    }

    #[Test]
    public function createNewVoteLeavesAuthorUnsetWhenNoFrontendUser(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [], 'ident-x');
        $this->injectProperty('currentUser', []);

        $this->frontendUserRepository->expects(self::never())->method('findByUid');

        GeneralUtility::addInstance(Vote::class, new Vote());

        $method = new \ReflectionMethod($this->controller, 'createNewVote');
        /** @var Vote $vote */
        $vote = $method->invoke($this->controller, Vote::TYPE_DOWNVOTE, new Comment());

        self::assertNull($vote->getAuthor());
        self::assertSame(Vote::TYPE_DOWNVOTE, $vote->getType());
    }

    #[Test]
    public function handleCustomMessagesFlashesDoNotVoteForYourselfWhenSettingAndParamMatch(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'ignoreVotingForOwnComments' => true,
        ]);
        $this->registerLanguageServiceFactoryStub();

        $this->request->method('getParsedBody')->willReturn(['doNotVoteForYourself' => 1]);
        $this->request->method('getQueryParams')->willReturn([]);

        $this->view->expects(self::once())
            ->method('assign')
            ->with('hasCustomMessages', true);

        $method = new \ReflectionMethod($this->controller, 'handleCustomMessages');
        $method->invoke($this->controller);
    }

    #[Test]
    public function handleCustomMessagesFlashesVotingDisabledWhenSettingAndParamMatch(): void
    {
        $pageUid = 10;
        $this->setupControllerWithSettings($pageUid, [
            'enableVoting' => false,
        ]);
        $this->registerLanguageServiceFactoryStub();

        $this->request->method('getParsedBody')->willReturn([]);
        $this->request->method('getQueryParams')->willReturn(['votingDisabled' => 1]);

        $this->view->expects(self::once())
            ->method('assign')
            ->with('hasCustomMessages', true);

        $method = new \ReflectionMethod($this->controller, 'handleCustomMessages');
        $method->invoke($this->controller);
    }

    /**
     * Helper method to inject private/protected properties. Walks the class
     * hierarchy so it can set parent-class private properties (e.g.
     * ActionController::$internalFlashMessageService).
     */
    private function injectProperty(string $propertyName, $value): void
    {
        $this->resolveProperty($propertyName)->setValue($this->controller, $value);
    }

    private function readProperty(string $propertyName): mixed
    {
        return $this->resolveProperty($propertyName)->getValue($this->controller);
    }

    private function resolveProperty(string $propertyName): \ReflectionProperty
    {
        $class = new \ReflectionClass($this->controller);
        while ($class !== false) {
            if ($class->hasProperty($propertyName)) {
                return $class->getProperty($propertyName);
            }
            $class = $class->getParentClass();
        }
        throw new \RuntimeException(sprintf('Property "%s" not found on %s or any parent class', $propertyName, $this->controller::class));
    }

    /**
     * Helper method to setup controller with common settings
     */
    private function setupControllerWithSettings(int $pageUid, array $additionalSettings = [], ?string $authorIdent = null, ?MockObject $frontendUser = null): void
    {
        $settings = array_merge([
            'storagePid' => $pageUid,
            'useEntryUid' => false,
            'enableVoting' => false,
            'commentAnchorPrefix' => 'c',
            'hideVoteButtons' => false,
            'invertCommentSorting' => false,
            'invertReplySorting' => false,
            'linkUrlsInComments' => false,
        ], $additionalSettings);

        $this->injectProperty('settings', $settings);
        $this->injectProperty('pageUid', $pageUid);
        $this->injectProperty('commentStorageUid', $pageUid);
        $this->injectProperty('currentUser', []);
        $this->injectProperty('currentAuthorIdent', $authorIdent);

        $this->configureRequestAttributes($pageUid, $frontendUser);

        if ($authorIdent === null) {
            $this->cookieUtility->expects(self::any())
                ->method('get')
                ->with('ahash')
                ->willReturn(null);
        }
    }

    /**
     * Stubs every UriBuilder method that buildUriByUid()/redirectToUri() chain
     * through, returning a fixed URI from build(). Use in tests that exercise a
     * redirect path but don't need to assert on the URI construction itself.
     */
    private function stubUriBuilder(string $builtUri = '/page-10'): void
    {
        $this->uriBuilder->method('reset')->willReturnSelf();
        $this->uriBuilder->method('setTargetPageUid')->willReturnSelf();
        $this->uriBuilder->method('setAddQueryString')->willReturnSelf();
        $this->uriBuilder->method('setArgumentsToBeExcludedFromQueryString')->willReturnSelf();
        $this->uriBuilder->method('setArguments')->willReturnSelf();
        $this->uriBuilder->method('build')->willReturn($builtUri);
        $this->request->method('getPluginName')->willReturn('show');
    }

    /**
     * Wires the shared `$this->request->getAttribute()` callback so it returns a
     * `PageInformation` for `frontend.page.information` and a
     * `FrontendUserAuthentication` for `frontend.user`. Callers can override the
     * FE user by passing one in; otherwise an empty (anonymous) FE user is used.
     */
    private function configureRequestAttributes(int $pageUid, ?MockObject $frontendUser = null): void
    {
        $serverRequest = $this->createServerRequestWithPageInfo($pageUid);
        $frontendUser ??= $this->createFrontendUserAuth([]);

        $this->request->expects(self::any())
            ->method('getAttribute')
            ->willReturnCallback(function ($attribute) use ($serverRequest, $frontendUser) {
                if ($attribute === 'frontend.page.information') {
                    return $serverRequest->getAttribute('frontend.page.information');
                }
                if ($attribute === 'frontend.user') {
                    return $frontendUser;
                }
                return null;
            });
    }

    /**
     * Helper method to create a mock Comment
     *
     * @param array<int, Comment|MockObject> $replies
     */
    private function createComment(int $uid, string $message, array $replies = []): MockObject
    {
        $comment = $this->createMock(Comment::class);
        $comment->method('getUid')->willReturn($uid);
        $comment->method('getMessage')->willReturn($message);
        $comment->method('getReplies')->willReturn($replies);
        $comment->method('getOrigPid')->willReturn(10);
        return $comment;
    }

    /**
     * Creates a QueryResult/QueryResultInterface test double that iterates over
     * the given items so foreach loops in the controller behave like a real
     * result. `calculateCommentCount()` typehints the concrete `QueryResult`, so
     * pass `QueryResult::class` for that path; other paths accept the interface.
     *
     * @param array<int, mixed> $items
     * @param class-string $class
     */
    private function createQueryResult(array $items, string $class = QueryResultInterface::class): MockObject
    {
        $items = array_values($items);
        $position = 0;

        $queryResult = $this->createMock($class);
        $queryResult->method('count')->willReturn(count($items));
        $queryResult->method('toArray')->willReturn($items);
        $queryResult->method('rewind')->willReturnCallback(function () use (&$position): void {
            $position = 0;
        });
        $queryResult->method('valid')->willReturnCallback(function () use (&$position, &$items): bool {
            return isset($items[$position]);
        });
        $queryResult->method('current')->willReturnCallback(function () use (&$position, &$items) {
            return $items[$position];
        });
        $queryResult->method('key')->willReturnCallback(function () use (&$position): int {
            return $position;
        });
        $queryResult->method('next')->willReturnCallback(function () use (&$position): void {
            $position++;
        });

        return $queryResult;
    }

    /**
     * Helper method to create a mock Vote
     */
    private function createVote(int $uid, Comment $comment, int $type): MockObject
    {
        $vote = $this->createMock(Vote::class);
        $vote->method('getUid')->willReturn($uid);
        $vote->method('getComment')->willReturn($comment);
        $vote->method('getType')->willReturn($type);
        $vote->method('isDownvote')->willReturn($type === Vote::TYPE_DOWNVOTE);
        return $vote;
    }

    /**
     * Helper method to create ServerRequest with PageInformation
     */
    private function createServerRequestWithPageInfo(int $pageUid): ServerRequest
    {
        $pageInfo = new PageInformation();
        $pageInfoReflection = new \ReflectionClass($pageInfo);
        $idProperty = $pageInfoReflection->getProperty('id');
        $idProperty->setValue($pageInfo, $pageUid);

        $serverRequest = new ServerRequest();
        return $serverRequest->withAttribute('frontend.page.information', $pageInfo);
    }

    /**
     * Helper method to create FrontendUserAuthentication mock
     */
    private function createFrontendUserAuth(array $userData): MockObject
    {
        $frontendUser = $this->createMock(FrontendUserAuthentication::class);
        $frontendUser->user = $userData;
        return $frontendUser;
    }
}
