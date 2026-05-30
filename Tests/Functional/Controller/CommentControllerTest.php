<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LogLevel;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Domain\Model\Vote;
use T3\PwComments\Service\Moderation\ModerationResult;
use T3\PwComments\Utility\HashEncryptionUtility;
use T3\PwCommentsModerationDouble\FakeModerationProviderFactory;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Log\Writer\FileWriter;
use TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfigurationService;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional test for CommentController.
 *
 * Renders the "show" plugin through a real frontend request so the links the
 * plugin generates can be inspected. The central case is issue #40: when the
 * plugin is shown on a foreign record (e.g. a news detail page), the host
 * record's GET parameters must be carried over into the generated links.
 */
final class CommentControllerTest extends FunctionalTestCase
{
    private const MBOX_FILE = '/tmp/pw_comments_test_mail.mbox';
    private const AI_LOG_FILE = '/tmp/pw_comments_test_ai.log';

    protected array $coreExtensionsToLoad = [
        'install',
    ];

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/pw_comments',
        'typo3conf/ext/pw_comments/Tests/Fixtures/Extensions/pw_comments_moderation_double',
    ];

    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport' => 'mbox',
            'transport_mbox_file' => self::MBOX_FILE,
        ],
        'LOG' => [
            // LogManager::getLogger() rewrites underscores in channel names to
            // dots, so #[Channel('pw_comments')] resolves to 'pw.comments'.
            'pw' => [
                'comments' => [
                    'writerConfiguration' => [
                        LogLevel::WARNING => [
                            FileWriter::class => [
                                'logFile' => self::AI_LOG_FILE,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        @unlink(self::MBOX_FILE);
        @unlink(self::AI_LOG_FILE);
        $this->resetFileWriterHandleCache(self::AI_LOG_FILE);
        FakeModerationProviderFactory::reset();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Database/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Database/fe_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Database/tx_pwcomments_domain_model_comment.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Database/tx_pwcomments_domain_model_vote.csv');

        $this->get(SiteWriter::class)->createNewBasicSite('main', 1, 'https://example.com/');

        $this->setUpFrontendRootPage(
            1,
            ['EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript'],
        );
    }

    protected function tearDown(): void
    {
        @unlink(self::MBOX_FILE);
        @unlink(self::AI_LOG_FILE);
        FakeModerationProviderFactory::reset();
        parent::tearDown();
    }

    /**
     * FileWriter caches `fopen()` handles in a static array keyed by log file
     * path. Between tests, GC of the previous FileWriter is non-deterministic,
     * so the next test's lazily-constructed writer may reuse a stale handle
     * pointing to the now-unlinked inode — writes succeed into the deleted
     * inode and `assertFileExists` fails. We only touch the handle entry; the
     * sibling counter is left alone so the previous writer's destructor can
     * still decrement it without tripping an "Undefined array key" warning.
     */
    private function resetFileWriterHandleCache(string $logFile): void
    {
        $handles = (new \ReflectionClass(FileWriter::class))->getProperty('logFileHandles');
        $cache = $handles->getValue();
        if (isset($cache[$logFile]) && is_resource($cache[$logFile])) {
            @fclose($cache[$logFile]);
        }
        unset($cache[$logFile]);
        $handles->setValue(null, $cache);
    }

    #[Test]
    public function indexActionRendersStoredComments(): void
    {
        $body = $this->renderPage();

        self::assertStringContainsString('Test comment 1', $body);
        self::assertStringContainsString('Test comment 2', $body);
    }

    /**
     * Issue #41: comments by a registered frontend user render the user's
     * username (via {comment.author.username}). This only works once the
     * FrontendUser model exposes the username property, otherwise the author
     * name stayed blank.
     */
    #[Test]
    public function indexActionRendersRegisteredUserUsername(): void
    {
        $body = $this->renderPage();

        self::assertStringContainsString('testuser', $body);
    }

    #[Test]
    public function votingLinksArePresentForComments(): void
    {
        $body = $this->renderPage();

        self::assertStringContainsString('tx_pwcomments_show%5Baction%5D=upvote', $body);
        self::assertStringContainsString('tx_pwcomments_show%5Baction%5D=downvote', $body);
    }

    /**
     * Issue #40: foreign GET parameters from the host record (here the news
     * article id) must survive in the generated voting link. Before the fix
     * (addQueryString="true") TYPO3 v12+ only kept the route arguments and
     * dropped these "untrusted" query parameters.
     */
    #[Test]
    public function generatedVotingLinkPreservesForeignQueryParameters(): void
    {
        $body = $this->renderPage(['tx_news_pi1[news]' => 99]);

        self::assertStringContainsString(
            'tx_news_pi1%5Bnews%5D=99',
            $this->firstUpvoteHref($body),
            'Foreign GET parameter was dropped from the generated voting link (issue #40).',
        );
    }

    /**
     * Hidden comments (enable-field `hidden=1`) must not appear in the rendered
     * list. Fixture comment uid 4 carries `hidden=1`.
     */
    #[Test]
    public function indexActionExcludesHiddenComments(): void
    {
        $body = $this->renderPage();

        self::assertStringNotContainsString('Pending comment awaiting confirmation', $body);
    }

    /**
     * Replies (parent_comment > 0) render nested inside their parent's reply
     * list, not as top-level entries. The repository filters top-level by
     * `parentComment = 0` (see CommentRepository::findByPid).
     */
    #[Test]
    public function indexActionRendersRepliesNestedUnderParent(): void
    {
        $body = $this->renderPage();

        self::assertStringContainsString('class="comments-list reply-list"', $body);
        self::assertStringContainsString('First reply to comment 1', $body);
        self::assertStringContainsString('Second reply to comment 1', $body);

        // Four visible top-level comments (uids 1, 2, 3, 5) — uid 4 is hidden,
        // uids 6 and 7 are replies and must not contribute to the top level.
        self::assertSame(
            4,
            substr_count($body, 'class="comment-main-level'),
            'Replies must not render as top-level <div class="comment-main-level">.',
        );
    }

    /**
     * The comment count rendered in `<h1>Comments (N)</h1>` counts only
     * top-level comments by default and additionally includes replies when
     * `countReplies` is enabled (CommentController::calculateCommentCount).
     */
    #[Test]
    public function indexActionShowsCommentCountIncludingRepliesWhenCountRepliesEnabled(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/CountReplies.typoscript',
            ],
        );

        $body = $this->renderPage();

        // 4 visible top-level comments (uids 1, 2, 3, 5) + 2 replies (uids 6, 7).
        self::assertMatchesRegularExpression('/Comments\s*\(6\)/', $body);
    }

    /**
     * With `useEntryUid=1` and `entryUid=123` only comments matching that
     * entry are rendered. Fixture comment uid 3 carries entry_uid=123; uids
     * 1/2/5 have entry_uid=0 and must be excluded.
     */
    #[Test]
    public function indexActionFiltersByEntryUidWhenUseEntryUidEnabled(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/UseEntryUid.typoscript',
            ],
        );

        $body = $this->renderPage();

        self::assertStringContainsString('Comment on news article', $body);
        self::assertStringNotContainsString('Test comment 1', $body);
        self::assertStringNotContainsString('Test comment 2', $body);
        self::assertStringNotContainsString('Comment by registered user', $body);
    }

    /**
     * Empty result renders the `tx_pwcomments.noComments` translation. Driven
     * here via `useEntryUid` pointing at an entry that has no comments — same
     * code path as a generally empty list.
     */
    #[Test]
    public function indexActionRendersNoCommentsTranslationWhenListIsEmpty(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/UseEntryUidEmpty.typoscript',
            ],
        );

        $body = $this->renderPage();

        self::assertStringContainsString('No comments found!', $body);
    }

    /**
     * `invertCommentSorting=1` flips top-level comment order from ascending
     * `crdate` to descending. With ascending the fixture order in the body is
     * uid 1 → 2 → 5; with descending it must be 5 → 2 → 1.
     */
    #[Test]
    public function indexActionInvertsCommentOrderWhenInvertCommentSortingEnabled(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/InvertCommentSorting.typoscript',
            ],
        );

        $body = $this->renderPage();

        $posFirst = strpos($body, 'Test comment 1');
        $posSecond = strpos($body, 'Test comment 2');
        $posFifth = strpos($body, 'Comment by registered user');

        self::assertNotFalse($posFirst);
        self::assertNotFalse($posSecond);
        self::assertNotFalse($posFifth);
        self::assertGreaterThan($posFifth, $posSecond, 'Comment 2 must render after comment 5 in inverted order.');
        self::assertGreaterThan($posSecond, $posFirst, 'Comment 1 must render after comment 2 in inverted order.');
    }

    /**
     * `?doNotVoteForYourself=1` combined with the `ignoreVotingForOwnComments`
     * setting surfaces the matching flash message (set in handleCustomMessages).
     */
    #[Test]
    public function indexActionRendersDoNotVoteForYourselfFlashMessage(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/IgnoreOwnVotes.typoscript',
            ],
        );

        $body = $this->renderPage(['doNotVoteForYourself' => 1]);

        self::assertStringContainsString("You can't vote for your own comment.", $body);
    }

    /**
     * `?votingDisabled=1` with `enableVoting=0` surfaces the matching flash
     * message (set in handleCustomMessages).
     */
    #[Test]
    public function indexActionRendersVotingDisabledFlashMessage(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/VotingDisabled.typoscript',
            ],
        );

        $body = $this->renderPage(['votingDisabled' => 1]);

        self::assertStringContainsString('To vote for comments, please login.', $body);
    }

    /**
     * Passing `tx_pwcomments_show[commentToReplyTo]=1` rehydrates the Comment
     * via Extbase property mapping and marks the matching rendered comment
     * with the `replyingToThisComment` class (see Index.html).
     */
    #[Test]
    public function indexActionMarksCommentToReplyToWithReplyingClass(): void
    {
        $body = $this->renderPage(['tx_pwcomments_show[commentToReplyTo]' => 1]);

        self::assertStringContainsString('replyingToThisComment', $body);
    }

    /**
     * `invertReplySorting=1` flips replies of a parent comment from ascending
     * `crdate` to descending. Replies of comment 1 are "First reply..." (uid 6,
     * older) and "Second reply..." (uid 7, newer); with inverted sorting the
     * newer one must render before the older one.
     */
    #[Test]
    public function indexActionInvertsReplyOrderWhenInvertReplySortingEnabled(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/InvertReplySorting.typoscript',
            ],
        );

        $body = $this->renderPage();

        $posFirst = strpos($body, 'First reply to comment 1');
        $posSecond = strpos($body, 'Second reply to comment 1');

        self::assertNotFalse($posFirst);
        self::assertNotFalse($posSecond);
        self::assertGreaterThan($posSecond, $posFirst, 'Reply 6 must render after reply 7 when reply sorting is inverted.');
    }

    /**
     * For a logged-in frontend user the controller queries votes for
     * `authorIdent = (string) userUid` and populates `upvotedCommentUids` /
     * `downvotedCommentUids`. The Voting partial then appends a ` voted` class
     * to the matching link. Fixture votes 3 and 4 give FE user 1 an upvote on
     * comment 1 and a downvote on comment 2.
     */
    #[Test]
    public function indexActionMarksUpvotedAndDownvotedCommentsForLoggedInUser(): void
    {
        $context = (new InternalRequestContext())->withFrontendUserId(1);

        $request = (new InternalRequest('https://example.com/'))->withPageId(1);
        $body = (string) $this->executeFrontendSubRequest($request, $context)->getBody();

        $commentOne = $this->commentSection($body, 1);
        $commentTwo = $this->commentSection($body, 2);

        self::assertStringContainsString('class="upvote voted"', $commentOne);
        self::assertStringNotContainsString('class="downvote voted"', $commentOne);

        self::assertStringContainsString('class="downvote voted"', $commentTwo);
        self::assertStringNotContainsString('class="upvote voted"', $commentTwo);
    }

    /**
     * confirmCommentAction unhides a moderated comment when the supplied hash
     * matches the one derived from the comment's message (+ encryption key).
     */
    #[Test]
    public function confirmCommentActionUnhidesCommentWithValidHash(): void
    {
        $this->requestConfirmComment(4, $this->validHashForComment('Pending comment awaiting confirmation'));

        self::assertSame(0, $this->hiddenFlagForComment(4), 'Comment should be unhidden after confirmation.');
    }

    /**
     * An invalid hash must not unhide the comment.
     */
    #[Test]
    public function confirmCommentActionKeepsCommentHiddenWithInvalidHash(): void
    {
        $this->requestConfirmComment(4, 'definitely-not-the-hash');

        self::assertSame(1, $this->hiddenFlagForComment(4), 'Comment must stay hidden when the hash is invalid.');
    }

    /**
     * The other two branches of the `!$resolvedComment || !validHash || !hidden`
     * guard in `confirmCommentAction` (line 422):
     *
     * - Replay protection: the confirm hash is stable (derived from message +
     *   encryption key), so a valid link can be re-clicked indefinitely. The
     *   `!$resolvedComment->getHidden()` guard is the only thing preventing a
     *   replayed click from re-firing the unhide write (and any downstream
     *   side effects) after an admin manually re-hid the comment.
     * - Unknown uid: `findByCommentUid()` returning null must short-circuit to
     *   the same redirect, not throw and bubble up.
     */
    #[Test]
    public function confirmCommentActionRejectsReplayAndUnknownUidWithoutDbWrite(): void
    {
        // Scenario A: already-visible comment, valid hash.
        $beforeHidden = $this->hiddenFlagForComment(1);
        self::assertSame(0, $beforeHidden, 'Fixture invariant: comment 1 starts visible.');

        $this->requestConfirmComment(1, $this->validHashForComment('Test comment 1'));

        self::assertSame(0, $this->hiddenFlagForComment(1), 'Replayed confirm on an already visible comment must be a no-op.');

        // Scenario B: uid that does not exist.
        $beforeRows = $this->countComments();

        $this->requestConfirmComment(99999, 'any-hash-here');

        self::assertSame($beforeRows, $this->countComments(), 'Unknown uid must not insert or otherwise mutate any row.');
    }

    /**
     * With `moderateNewComments=1` and `sendMailToAuthorAfterPublish=1`, a
     * successful confirm unhides the comment AND dispatches a mail to the
     * comment author. Fixture comment 4 has `author_mail=pending@example.com`.
     *
     * Flash messages (`mailSentToAuthorAfterPublish`, `commentPublished`) are
     * not asserted here: they live in the FE session, and
     * `executeFrontendSubRequest` does not propagate the session cookie from
     * the confirm GET to a follow-up render — the mbox and the hidden flag
     * are the observable contract.
     */
    #[Test]
    public function confirmCommentActionDispatchesAuthorMailWhenConfigured(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/Moderation.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/Mail.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/AuthorMailAfterPublish.typoscript',
            ],
        );

        self::assertSame(1, $this->hiddenFlagForComment(4));

        $this->requestConfirmComment(4, $this->validHashForComment('Pending comment awaiting confirmation'));

        self::assertSame(0, $this->hiddenFlagForComment(4));

        $mbox = $this->capturedMail();
        self::assertStringContainsString('To: pending@example.com', $mbox, 'Author notification mail must be sent on successful confirm.');
    }

    /**
     * End-to-end test of `sendAuthorMailWhenCommentHasBeenApprovedAction`,
     * covering the full chain: the BE-driven GET → `FrontendHandler`
     * middleware → `MailNotificationController::sendMail` → Extbase Bootstrap
     * → `CommentController::sendAuthorMailWhenCommentHasBeenApprovedAction`.
     *
     * Two sub-requests guard a contract the controller method itself does
     * NOT enforce: the action sends mail unconditionally when the moderation
     * settings line up, trusting its caller (the middleware) to gate on the
     * `row.hidden` flag. Without the second assertion, a refactor that moves
     * the hidden-check out of `MailNotificationController` would silently
     * turn this action into "send unlimited author mails to anyone whose
     * hash you can guess."
     */
    #[Test]
    public function sendAuthorMailWhenCommentHasBeenApprovedActionDispatchesMailOnlyWhileCommentIsHidden(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/Moderation.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/Mail.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/AuthorMailAfterPublish.typoscript',
            ],
        );

        $messageHash = $this->validHashForComment('Pending comment awaiting confirmation');

        // First call: comment 4 is still hidden — middleware passes the guard,
        // bootstrap runs the action, the author mail is dispatched.
        self::assertSame(1, $this->hiddenFlagForComment(4));

        $okResponse = $this->requestSendAuthorMailAfterApproved(4, $messageHash);

        self::assertSame(200, $okResponse->getStatusCode(), 'Hidden comment must take the OK path through MailNotificationController.');
        self::assertStringContainsString(
            'To: pending@example.com',
            $this->capturedMail(),
            'Author mail must be sent when the comment is still hidden and approval settings match.',
        );

        // Pin the load-bearing pre-condition: when the comment is no longer
        // hidden, the middleware short-circuits to the bad-request branch and
        // no further mail is dispatched.
        $this->setHiddenFlagForComment(4, false);
        $mboxBefore = (string) file_get_contents(self::MBOX_FILE);

        $rejectedResponse = $this->requestSendAuthorMailAfterApproved(4, $messageHash);

        self::assertSame(400, $rejectedResponse->getStatusCode(), 'Visible comment must take the bad-request path through MailNotificationController.');
        self::assertSame(
            $mboxBefore,
            (string) file_get_contents(self::MBOX_FILE),
            'No additional mail may be dispatched once the hidden guard has flipped.',
        );
    }

    /**
     * Happy path: a valid anonymous submission is stored as a visible comment
     * and the controller redirects to the new comment's anchor.
     */
    #[Test]
    public function createActionStoresValidCommentAndRedirectsToCommentAnchor(): void
    {
        $before = $this->countComments();

        $response = $this->postCreateComment([
            'authorName' => 'Alice',
            'authorMail' => 'alice@example.com',
            'message'    => 'A perfectly fine comment.',
        ]);

        self::assertSame($before + 1, $this->countComments());

        $latest = $this->latestComment();
        self::assertSame('Alice', $latest['author_name']);
        self::assertSame('alice@example.com', $latest['author_mail']);
        self::assertSame('A perfectly fine comment.', $latest['message']);
        self::assertSame(0, (int) $latest['hidden']);
        self::assertSame(1, (int) $latest['pid']);
        self::assertSame(1, (int) $latest['orig_pid']);
        self::assertNotSame('', (string) $latest['author_ident']);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringEndsWith('#comment-' . $latest['uid'], $response->getHeaderLine('location'));
    }

    /**
     * When `moderateNewComments` is enabled the new comment is stored hidden
     * and the user is redirected to the `successfulAnchor` instead of the
     * comment anchor (the comment is not yet visible).
     */
    #[Test]
    public function createActionStoresHiddenCommentWhenModerationIsEnabled(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/Moderation.typoscript',
            ],
        );

        $before = $this->countComments();

        $response = $this->postCreateComment([
            'authorName' => 'Bob',
            'authorMail' => 'bob@example.com',
            'message'    => 'A comment that goes through moderation.',
        ]);

        self::assertSame($before + 1, $this->countComments());
        self::assertSame(1, (int) $this->latestComment()['hidden']);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringEndsWith('#success', $response->getHeaderLine('location'));
    }

    /**
     * The honeypot field (default name `authorWebsite`) is a hidden form field
     * that no human ever fills in. When a POST carries any value for it the
     * controller silently bails out without persisting anything and sends the
     * bot back to the `writeCommentAnchor`.
     */
    #[Test]
    public function createActionRejectsSubmissionWhenHoneypotFieldIsFilled(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/Honeypot.typoscript',
            ],
        );

        $before = $this->countComments();

        $response = $this->postCreateComment(
            [
                'authorName' => 'Spammer',
                'authorMail' => 'spammer@example.com',
                'message'    => 'Buy cheap things at example.org!',
            ],
            ['authorWebsite' => 'http://spammer.example.org'],
        );

        self::assertSame($before, $this->countComments(), 'No comment may be stored when the honeypot was triggered.');
        self::assertSame(303, $response->getStatusCode());
        self::assertStringEndsWith('#write-comment', $response->getHeaderLine('location'));
    }

    /**
     * Validation failures are observed via the HTTP 400 status that Extbase's
     * default `errorAction()` returns. The specific error code can't be read
     * from the response body because `CommentController::getErrorFlashMessage()`
     * deliberately suppresses the flash message and the default flattened
     * message is a canned "Validation failed" string; without a valid
     * `__referrer` HMAC the framework also cannot forward back to `newAction`
     * to re-render the form with `<f:form.validationResults>`. The provider
     * still exercises each branch of the validator end-to-end.
     *
     * @return array<string, array{commentArguments: array<string, string>, extraTypoScript: list<string>}>
     */
    public static function invalidCommentProvider(): array
    {
        return [
            'missing message' => [
                'commentArguments' => [
                    'authorName' => 'Carol',
                    'authorMail' => 'carol@example.com',
                    'message'    => '',
                ],
                'extraTypoScript' => [],
            ],
            'invalid email for anonymous author' => [
                'commentArguments' => [
                    'authorName' => 'Dave',
                    'authorMail' => 'not-an-email',
                    'message'    => 'A valid message.',
                ],
                'extraTypoScript' => [],
            ],
            'message contains a banned word' => [
                'commentArguments' => [
                    'authorName' => 'Eve',
                    'authorMail' => 'eve@example.com',
                    'message'    => 'Hello spamword.',
                ],
                'extraTypoScript' => ['EXT:pw_comments/Tests/Fixtures/Frontend/BadWords.typoscript'],
            ],
        ];
    }

    /**
     * @param array<string, string> $commentArguments
     * @param list<string> $extraTypoScript
     */
    #[Test]
    #[DataProvider('invalidCommentProvider')]
    public function createActionRejectsInvalidComment(array $commentArguments, array $extraTypoScript): void
    {
        if ($extraTypoScript !== []) {
            $this->setUpFrontendRootPage(
                1,
                array_merge(
                    ['EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript'],
                    $extraTypoScript,
                ),
            );
        }

        $before = $this->countComments();

        $response = $this->postCreateComment($commentArguments);

        self::assertSame($before, $this->countComments(), 'No comment may be stored when validation fails.');
        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Validation failed', (string) $response->getBody());
    }

    /**
     * For a logged-in frontend user the comment record stores the FE user UID
     * as `author` and the validator no longer requires `authorName`/`authorMail`
     * (the user is identified via session).
     */
    #[Test]
    public function createActionAssignsLoggedInFrontendUserAsAuthor(): void
    {
        $before = $this->countComments();

        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->postCreateComment(
            ['message' => 'A comment posted by the logged-in user.'],
            [],
            $context,
        );

        self::assertSame($before + 1, $this->countComments());

        $latest = $this->latestComment();
        self::assertSame(1, (int) $latest['author'], 'The logged-in fe_user uid must be set as author.');
        self::assertSame('A comment posted by the logged-in user.', $latest['message']);
        self::assertSame(0, (int) $latest['hidden']);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringEndsWith('#comment-' . $latest['uid'], $response->getHeaderLine('location'));
    }

    /**
     * When `sendMailOnNewCommentsTo` is configured and the author submits a
     * non-empty email, two notification mails go out: one to the configured
     * admin address, one to the comment author. Both are captured via the
     * `mbox` mail transport configured at the top of this class.
     */
    #[Test]
    public function createActionDispatchesAdminAndAuthorMails(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/Mail.typoscript',
            ],
        );

        $before = $this->countComments();

        $this->postCreateComment([
            'authorName' => 'Frank',
            'authorMail' => 'frank@example.com',
            'message'    => 'Notify everyone, please.',
        ]);

        self::assertSame($before + 1, $this->countComments());

        $mbox = $this->capturedMail();
        self::assertStringContainsString('To: admin@example.com', $mbox, 'Admin notification mail not captured.');
        self::assertStringContainsString('To: frank@example.com', $mbox, 'Author notification mail not captured.');
        self::assertStringContainsString('From: pw_comments Tests <no-reply@example.com>', $mbox);
    }

    /**
     * Guard against half-built comments hitting persistence: a POST that does
     * not carry a `newComment` payload (and therefore property-maps to null)
     * must redirect to the page without the `writeCommentAnchor` fragment —
     * that anchor is the spam-trap path on line 223, not the no-payload path.
     */
    #[Test]
    public function createActionRedirectsWithoutWritingWhenNewCommentIsMissing(): void
    {
        $before = $this->countComments();

        $response = $this->postCreateComment([]);

        self::assertSame($before, $this->countComments(), 'No row may be inserted when newComment is null.');
        self::assertSame(303, $response->getStatusCode());

        $location = $response->getHeaderLine('location');
        self::assertStringNotContainsString('#write-comment', $location, 'Null-payload must use the plain page URL, not the spam-trap anchor.');
        self::assertStringNotContainsString('#success', $location);
        self::assertStringNotContainsString('#comment-', $location);
    }

    /**
     * `linkUrlsInComments=1` routes the message through
     * `StringUtility::prepareCommentMessage` with `allowLinks=true`, which
     * wraps bare URLs in anchor elements. The exact transformer output is
     * the unit-tested surface of `StringUtility`; the controller test only
     * pins that the setting actually flows into the call.
     */
    #[Test]
    public function createActionWrapsUrlsInAnchorTagsWhenLinkUrlsInCommentsEnabled(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/LinkUrls.typoscript',
            ],
        );

        $this->postCreateComment([
            'authorName' => 'Liam',
            'authorMail' => 'liam@example.com',
            'message'    => 'Visit https://example.com today.',
        ]);

        $stored = (string) $this->latestComment()['message'];
        self::assertMatchesRegularExpression(
            '/<a [^>]*href="https:\/\/example\.com[^"]*"[^>]*>/',
            $stored,
            'URL must be wrapped in an anchor element when linkUrlsInComments is enabled.',
        );
    }

    /**
     * Negative twin of the linkUrls test: with `linkUrlsInComments` unset
     * (default 0 in `BasicSetup.typoscript`), the message stores the URL as
     * plain text — no anchor element is emitted. Together with the on-path
     * test this pins the *branch*, not the transformer.
     */
    #[Test]
    public function createActionStoresUrlAsPlainTextWhenLinkUrlsInCommentsDisabled(): void
    {
        $this->postCreateComment([
            'authorName' => 'Mia',
            'authorMail' => 'mia@example.com',
            'message'    => 'Visit https://example.com today.',
        ]);

        $stored = (string) $this->latestComment()['message'];
        self::assertStringContainsString('https://example.com', $stored);
        self::assertStringNotContainsString('<a ', $stored, 'No anchor element may be emitted when linkUrlsInComments is disabled.');
    }

    /**
     * `useEntryUid=1` plus `entryUid=123` makes `initializeAction` populate
     * `$this->entryUid`, which `createAction` then writes onto the new row.
     * The matching index-filter side is covered by
     * `indexActionFiltersByEntryUidWhenUseEntryUidEnabled`; this pins the
     * write side.
     */
    #[Test]
    public function createActionPersistsEntryUidWhenUseEntryUidEnabled(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/UseEntryUid.typoscript',
            ],
        );

        $this->postCreateComment([
            'authorName' => 'Nora',
            'authorMail' => 'nora@example.com',
            'message'    => 'A comment scoped to entry 123.',
        ]);

        self::assertSame(123, (int) $this->latestComment()['entry_uid']);
    }

    /**
     * Default (no `useEntryUid`) must persist `entry_uid=0`. Pins the default
     * so a future refactor that always reads `$settings['entryUid']` cannot
     * silently set every comment to the same scope.
     */
    #[Test]
    public function createActionPersistsZeroEntryUidByDefault(): void
    {
        $this->postCreateComment([
            'authorName' => 'Owen',
            'authorMail' => 'owen@example.com',
            'message'    => 'An unscoped comment.',
        ]);

        self::assertSame(0, (int) $this->latestComment()['entry_uid']);
    }

    /**
     * Happy path for upvoting: a logged-in FE user clicks upvote on a comment
     * they have not voted on yet. The controller inserts a Vote row, forwards
     * to indexAction, and the re-rendered list marks the comment as voted.
     */
    #[Test]
    public function upvoteActionInsertsNewVoteAndMarksCommentAsVoted(): void
    {
        self::assertSame(0, $this->countVotesFor(3, '1'));

        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->requestVote('upvote', 3, $context);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $this->countVotesFor(3, '1', Vote::TYPE_UPVOTE));

        $commentThree = $this->commentSection((string) $response->getBody(), 3);
        self::assertStringContainsString('class="upvote voted"', $commentThree);
    }

    /**
     * Mirror of the upvote case for `downvoteAction` — the inserted row must
     * carry `type=0` (Vote::TYPE_DOWNVOTE).
     */
    #[Test]
    public function downvoteActionInsertsNewVoteWithDownvoteType(): void
    {
        self::assertSame(0, $this->countVotesFor(3, '1'));

        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $this->requestVote('downvote', 3, $context);

        self::assertSame(1, $this->countVotesFor(3, '1', Vote::TYPE_DOWNVOTE));
    }

    /**
     * Voting again with the same type on a comment the user already voted on
     * removes the existing row (toggle off). Fixture row uid 3 carries
     * `(comment=1, author_ident="1", type=1)`.
     */
    #[Test]
    public function votingSameTypeAgainRemovesTheExistingVote(): void
    {
        self::assertSame(1, $this->countVotesFor(1, '1', Vote::TYPE_UPVOTE));

        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $this->requestVote('upvote', 1, $context);

        self::assertSame(0, $this->countVotesFor(1, '1'));
    }

    /**
     * Voting with the opposite type triggers the recursive `performVoting` at
     * controller line 500: the original row is removed and a brand-new row is
     * inserted with the flipped type. The new row's uid must therefore be
     * higher than the original uid 3 — a no-op would leave uid 3 in place.
     * The outer call's `ForwardResponse('index')` must still re-render index
     * so a future refactor that propagates the inner return value cannot
     * silently change the response shape.
     */
    #[Test]
    public function votingOppositeTypeFlipsTheVoteViaRecursiveCall(): void
    {
        $original = $this->findSingleVote(1, '1');
        self::assertSame(3, (int) $original['uid']);
        self::assertSame(Vote::TYPE_UPVOTE, (int) $original['type']);

        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->requestVote('downvote', 1, $context);

        $survivor = $this->findSingleVote(1, '1');
        self::assertSame(Vote::TYPE_DOWNVOTE, (int) $survivor['type']);
        self::assertGreaterThan(
            3,
            (int) $survivor['uid'],
            'Recursive performVoting must remove uid 3 and insert a fresh row.',
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<ul class="comments-list', (string) $response->getBody());
    }

    /**
     * With `enableVoting=0` `performVoting` returns `new ForwardResponse('index')`
     * before any DB write. The DB must stay untouched and the response must
     * carry the index template (not a redirect — that would be a different
     * branch).
     */
    #[Test]
    public function votingIsShortCircuitedWhenEnableVotingIsOff(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/VotingDisabled.typoscript',
            ],
        );

        self::assertSame(0, $this->countVotesFor(3, '1'));

        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->requestVote('upvote', 3, $context);

        self::assertSame(0, $this->countVotesFor(3, '1'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(
            '<ul class="comments-list',
            (string) $response->getBody(),
            'Disabled voting must forward to indexAction, not redirect.',
        );
    }

    /**
     * With `ignoreVotingForOwnComments=1`, voting on a comment whose
     * `authorIdent` matches the current user is rejected with a redirect that
     * carries `doNotVoteForYourself=1` so `handleCustomMessages()` can surface
     * the flash message on the next render. Fixture comment 5 has
     * `author_ident="1"`, matching FE user 1.
     */
    #[Test]
    public function votingOnOwnCommentRedirectsWhenIgnoreVotingForOwnCommentsEnabled(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/IgnoreOwnVotes.typoscript',
            ],
        );

        self::assertSame(0, $this->countVotesFor(5, '1'));

        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $response = $this->requestVote('upvote', 5, $context);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('doNotVoteForYourself=1', $response->getHeaderLine('location'));
        self::assertSame(0, $this->countVotesFor(5, '1'));
    }

    /**
     * Case C: AI moderation enabled, the provider throws, and
     * `aiModerationFallbackToManual=1`. The controller swallows the
     * exception, persists the comment with `ai_moderation_status='error'`,
     * logs an error to the `pw_comments` channel, and falls through to the
     * no-moderation branch — comment stays visible and the user is
     * redirected to its anchor.
     */
    #[Test]
    public function createActionFallsBackToManualWhenAiModerationProviderThrows(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/AiModeration.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/AiModerationFallback.typoscript',
            ],
        );

        FakeModerationProviderFactory::$nextException = new \InvalidArgumentException('API key missing');

        $before = $this->countComments();

        $response = $this->postCreateComment([
            'authorName' => 'Grace',
            'authorMail' => 'grace@example.com',
            'message'    => 'A comment that the moderation provider cannot reach.',
        ]);

        self::assertSame($before + 1, $this->countComments());

        $latest = $this->latestComment();
        self::assertSame(0, (int) $latest['hidden']);
        self::assertSame('error', $latest['ai_moderation_status']);
        self::assertStringStartsWith('AI moderation failed: ', (string) $latest['ai_moderation_reason']);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringEndsWith('#comment-' . $latest['uid'], $response->getHeaderLine('location'));

        self::assertFileExists(self::AI_LOG_FILE, 'Provider failure must be logged on the pw_comments channel.');
        self::assertStringContainsString('AI moderation service failed', (string) file_get_contents(self::AI_LOG_FILE));
    }

    /**
     * Case D: AI moderation enabled, provider throws, no fallback configured.
     * The exception escapes `createAction`; `executeFrontendSubRequest` in v14
     * re-throws it to the test rather than rendering a 5xx page. The contract
     * we pin is therefore: the exception bubbles up unchanged, and no comment
     * is persisted (controller never reaches `persistAll`).
     */
    #[Test]
    public function createActionRethrowsWhenAiModerationFailsWithoutFallback(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/AiModeration.typoscript',
            ],
        );

        FakeModerationProviderFactory::$nextException = new \InvalidArgumentException('API key missing');

        $before = $this->countComments();

        try {
            $this->postCreateComment([
                'authorName' => 'Henry',
                'authorMail' => 'henry@example.com',
                'message'    => 'A comment with no fallback configured.',
            ]);
            self::fail('createAction must re-throw the moderation exception when fallback is disabled.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('API key missing', $e->getMessage());
        }

        self::assertSame($before, $this->countComments(), 'No comment must be persisted when the AI provider crashes without fallback.');
    }

    /**
     * Case A: AI moderation marks the comment as a violation. The comment is
     * persisted hidden with `ai_moderation_status='flagged'`, the provider's
     * reason and confidence are stored verbatim, the user is redirected to
     * `successfulAnchor`, and the flash message uses the hardcoded English
     * fallback (the `tx_pwcomments.aiModerationNotice` xlf key is missing —
     * separate bug, not in scope for these tests).
     */
    #[Test]
    public function createActionHidesCommentAndStoresReasonWhenAiModerationFlagsViolation(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/AiModeration.typoscript',
            ],
        );

        FakeModerationProviderFactory::$nextResult = new ModerationResult(
            true,
            ['hate'],
            ['hate' => 0.95],
            'hate',
            0.95,
        );

        $before = $this->countComments();

        $response = $this->postCreateComment([
            'authorName' => 'Ivy',
            'authorMail' => 'ivy@example.com',
            'message'    => 'A comment the AI flagged.',
        ]);

        self::assertSame($before + 1, $this->countComments());

        $latest = $this->latestComment();
        self::assertSame(1, (int) $latest['hidden']);
        self::assertSame('flagged', $latest['ai_moderation_status']);
        self::assertSame('hate', $latest['ai_moderation_reason']);
        self::assertEqualsWithDelta(0.95, (float) $latest['ai_moderation_confidence'], 0.001);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringEndsWith('#success', $response->getHeaderLine('location'));
        // Flash message assertion deliberately omitted: FE flash storage is
        // session-scoped, and `executeFrontendSubRequest` does not propagate
        // the session cookie from the POST to a follow-up GET, so the next
        // render cannot see the flash a real browser would.
    }

    /**
     * Case B: AI moderation approves the comment. Status is set to
     * 'approved', the comment is visible, and the user is redirected to the
     * comment's anchor with the thanks flash message.
     */
    #[Test]
    public function createActionStoresApprovedCommentVisibleWhenAiModerationDoesNotFlag(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/AiModeration.typoscript',
            ],
        );

        FakeModerationProviderFactory::$nextResult = new ModerationResult(
            false,
            [],
            [],
            '',
            0.05,
        );

        $before = $this->countComments();

        $response = $this->postCreateComment([
            'authorName' => 'Jack',
            'authorMail' => 'jack@example.com',
            'message'    => 'A perfectly fine comment.',
        ]);

        self::assertSame($before + 1, $this->countComments());

        $latest = $this->latestComment();
        self::assertSame(0, (int) $latest['hidden']);
        self::assertSame('approved', $latest['ai_moderation_status']);
        self::assertEqualsWithDelta(0.05, (float) $latest['ai_moderation_confidence'], 0.001);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringEndsWith('#comment-' . $latest['uid'], $response->getHeaderLine('location'));
    }

    /**
     * Case B': AI approves but manual moderation is still active. Manual
     * moderation wins on the hidden flag (`moderateNewComments=1` forces it),
     * but the AI's verdict still persists as `approved`. This locks down the
     * precedence between the two moderation systems against accidental
     * "simplifications" of the moderation block.
     */
    #[Test]
    public function createActionKeepsHiddenWhenAiApprovesButManualModerationIsActive(): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/AiModeration.typoscript',
                'EXT:pw_comments/Tests/Fixtures/Frontend/Moderation.typoscript',
            ],
        );

        FakeModerationProviderFactory::$nextResult = new ModerationResult(false, [], [], '', 0.05);

        $response = $this->postCreateComment([
            'authorName' => 'Kim',
            'authorMail' => 'kim@example.com',
            'message'    => 'A comment under double moderation.',
        ]);

        $latest = $this->latestComment();
        self::assertSame(1, (int) $latest['hidden'], 'Manual moderation must still force hidden when AI approves.');
        self::assertSame('approved', $latest['ai_moderation_status'], 'AI verdict must be preserved even when manual moderation overrides visibility.');

        self::assertSame(303, $response->getStatusCode());
        self::assertStringEndsWith('#success', $response->getHeaderLine('location'));
    }

    /**
     * Baseline render for `newAction`: no rehydrated `newComment`, no
     * `commentToReplyTo`. The form renders with empty author fields, the
     * "Write new comment" heading, and no `parentComment` hidden field.
     */
    #[Test]
    public function newActionRendersEmptyFormByDefault(): void
    {
        $body = (string) $this->requestNewAction()->getBody();

        self::assertStringContainsString('Write new comment', $body);
        self::assertStringNotContainsString('Write new comment reply', $body);

        self::assertStringContainsString(
            'name="tx_pwcomments_new[newComment][authorName]"',
            $body,
        );
        self::assertStringContainsString(
            'name="tx_pwcomments_new[newComment][authorMail]"',
            $body,
        );
        self::assertStringContainsString(
            'name="tx_pwcomments_new[newComment][message]"',
            $body,
        );
        self::assertStringNotContainsString(
            'name="tx_pwcomments_new[newComment][parentComment]"',
            $body,
        );
    }

    /**
     * `?tx_pwcomments_new[commentToReplyTo]=N` switches the heading to the
     * reply variant and adds a hidden `parentComment` field carrying the
     * parent's uid (rendered by New.html only when commentToReplyTo is set).
     */
    #[Test]
    public function newActionWithCommentToReplyToRendersReplyHeadingAndParentField(): void
    {
        $body = (string) $this->requestNewAction([
            'tx_pwcomments_new[commentToReplyTo]' => 1,
        ])->getBody();

        self::assertStringContainsString('Write new comment reply', $body);
        self::assertMatchesRegularExpression(
            '/<input type="hidden"[^>]*name="tx_pwcomments_new\[newComment\]\[parentComment\]\[__identity\]"[^>]*value="1"/',
            $body,
        );
    }

    /**
     * Simulates the post-validation-failure forward: equivalent to the
     * request Extbase rebuilds on errorAction → newAction. With a rehydrated
     * `newComment` in the request, the form fields render the submitted
     * values (Extbase form binding via `<f:form object="{newComment}">`).
     */
    #[Test]
    public function newActionPrefillsFormFieldsFromRehydratedNewComment(): void
    {
        $trustedProperties = $this->get(MvcPropertyMappingConfigurationService::class)
            ->generateTrustedPropertiesToken(
                [
                    'tx_pwcomments_new[newComment][authorName]',
                    'tx_pwcomments_new[newComment][authorMail]',
                    'tx_pwcomments_new[newComment][message]',
                ],
                'tx_pwcomments_new',
            );

        $body = (string) $this->requestNewAction([
            'tx_pwcomments_new[newComment][authorName]' => 'Carol',
            'tx_pwcomments_new[newComment][authorMail]' => 'carol@example.com',
            'tx_pwcomments_new[newComment][message]' => 'Hello',
            'tx_pwcomments_new[__trustedProperties]' => $trustedProperties,
        ])->getBody();

        self::assertMatchesRegularExpression(
            '/name="tx_pwcomments_new\[newComment\]\[authorName\]"[^>]*value="Carol"/',
            $body,
        );
        self::assertMatchesRegularExpression(
            '/name="tx_pwcomments_new\[newComment\]\[authorMail\]"[^>]*value="carol@example\.com"/',
            $body,
        );
        self::assertStringContainsString('>Hello</textarea>', $body);
    }

    /**
     * Locks down the independence of the two prefill branches: when the
     * rehydrated `newComment` carries only `authorName`, the name field is
     * prefilled and the mail field stays empty (the truthy guard at controller
     * line 381 fails on the empty mail, so the session fallback is used —
     * which returns null in tests).
     */
    #[Test]
    public function newActionPrefillsOnlyFieldsSetOnRehydratedNewComment(): void
    {
        $trustedProperties = $this->get(MvcPropertyMappingConfigurationService::class)
            ->generateTrustedPropertiesToken(
                [
                    'tx_pwcomments_new[newComment][authorName]',
                    'tx_pwcomments_new[newComment][authorMail]',
                    'tx_pwcomments_new[newComment][message]',
                ],
                'tx_pwcomments_new',
            );

        $body = (string) $this->requestNewAction([
            'tx_pwcomments_new[newComment][authorName]' => 'Carol',
            'tx_pwcomments_new[newComment][authorMail]' => '',
            'tx_pwcomments_new[newComment][message]' => 'Hello',
            'tx_pwcomments_new[__trustedProperties]' => $trustedProperties,
        ])->getBody();

        self::assertMatchesRegularExpression(
            '/name="tx_pwcomments_new\[newComment\]\[authorName\]"[^>]*value="Carol"/',
            $body,
        );
        self::assertDoesNotMatchRegularExpression(
            '/name="tx_pwcomments_new\[newComment\]\[authorMail\]"[^>]*value="[^"]+"/',
            $body,
            'authorMail must stay empty when not provided on the rehydrated comment.',
        );
    }

    private function capturedMail(): string
    {
        self::assertFileExists(self::MBOX_FILE, 'Mbox file was never written - no mail was sent.');
        return (string) file_get_contents(self::MBOX_FILE);
    }

    private function requestSendAuthorMailAfterApproved(int $commentUid, string $messageHash): \Psr\Http\Message\ResponseInterface
    {
        // `tx_pwcomments[*]` is the namespace the FrontendHandler middleware
        // listens on. These params are *not* in the cacheHash exclude list
        // (see ext_localconf.php), and the middleware is registered after
        // `PageArgumentValidator` in the v14 chain, so a missing cHash 404s
        // before the handler even runs — we have to compute a valid one.
        $request = (new InternalRequest('https://example.com/'))
            ->withPageId(1)
            ->withQueryParameter('tx_pwcomments[action]', 'sendAuthorMailWhenCommentHasBeenApproved')
            ->withQueryParameter('tx_pwcomments[uid]', $commentUid)
            ->withQueryParameter('tx_pwcomments[pid]', 1)
            ->withQueryParameter('tx_pwcomments[hash]', $messageHash);

        $cHash = $this->get(CacheHashCalculator::class)
            ->generateForParameters($request->getUri()->getQuery());
        $request = $request->withQueryParameter('cHash', $cHash);

        return $this->executeFrontendSubRequest($request);
    }

    private function setHiddenFlagForComment(int $commentUid, bool $hidden): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable('tx_pwcomments_domain_model_comment')
            ->update(
                'tx_pwcomments_domain_model_comment',
                ['hidden' => $hidden ? 1 : 0],
                ['uid' => $commentUid],
            );
    }

    private function requestConfirmComment(int $commentUid, string $hash): void
    {
        // All tx_pwcomments_new confirm parameters are cacheHash-excluded
        // (see ext_localconf.php), so no cHash is required here.
        $request = (new InternalRequest('https://example.com/'))
            ->withPageId(1)
            ->withQueryParameter('tx_pwcomments_new[controller]', 'Comment')
            ->withQueryParameter('tx_pwcomments_new[action]', 'confirmComment')
            ->withQueryParameter('tx_pwcomments_new[comment]', $commentUid)
            ->withQueryParameter('tx_pwcomments_new[hash]', $hash);

        $this->executeFrontendSubRequest($request);
    }

    private function validHashForComment(string $message): string
    {
        $comment = new Comment();
        $comment->setMessage($message);

        return HashEncryptionUtility::createHashForComment($comment);
    }

    private function hiddenFlagForComment(int $commentUid): int
    {
        // Connection::select() applies enable-field restrictions, so it cannot
        // see hidden records. Drop the restrictions to read the raw flag.
        $queryBuilder = $this->getConnectionPool()
            ->getQueryBuilderForTable('tx_pwcomments_domain_model_comment');
        $queryBuilder->getRestrictions()->removeAll();

        $hidden = $queryBuilder
            ->select('hidden')
            ->from('tx_pwcomments_domain_model_comment')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($commentUid, Connection::PARAM_INT),
                ),
            )
            ->executeQuery()
            ->fetchOne();

        return (int) $hidden;
    }

    /**
     * @param array<string, int|float|string> $queryParameters
     */
    private function renderPage(array $queryParameters = []): string
    {
        $request = (new InternalRequest('https://example.com/'))->withPageId(1);
        foreach ($queryParameters as $name => $value) {
            $request = $request->withQueryParameter($name, $value);
        }

        // Foreign parameters are cache relevant, so a real request (e.g. a news
        // detail URL) carries a cHash. Provide a valid one so routing resolves.
        if ($queryParameters !== []) {
            $cHash = $this->get(CacheHashCalculator::class)
                ->generateForParameters($request->getUri()->getQuery());
            $request = $request->withQueryParameter('cHash', $cHash);
        }

        $response = $this->executeFrontendSubRequest($request);

        return (string) $response->getBody();
    }

    /**
     * @param array<string, string> $commentArguments form fields under `tx_pwcomments_new[newComment][...]`
     * @param array<string, string> $extraPluginArguments extra keys under `tx_pwcomments_new[...]` (e.g. honeypot)
     */
    private function postCreateComment(
        array $commentArguments,
        array $extraPluginArguments = [],
        ?InternalRequestContext $context = null,
    ): \Psr\Http\Message\ResponseInterface {
        $pluginArguments = $extraPluginArguments;
        if ($commentArguments !== []) {
            $pluginArguments['newComment'] = $commentArguments;
        }

        // Extbase rejects mass-assignment without a __trustedProperties HMAC token
        // (normally injected by <f:form>). Generate one for the fields we POST.
        $formFieldNames = [];
        foreach (array_keys($commentArguments) as $field) {
            $formFieldNames[] = 'tx_pwcomments_new[newComment][' . $field . ']';
        }
        if ($formFieldNames !== []) {
            $pluginArguments['__trustedProperties'] = $this->get(MvcPropertyMappingConfigurationService::class)
                ->generateTrustedPropertiesToken($formFieldNames, 'tx_pwcomments_new');
        }

        $parsedBody = ['tx_pwcomments_new' => $pluginArguments];

        $request = (new InternalRequest('https://example.com/'))
            ->withPageId(1)
            ->withQueryParameter('tx_pwcomments_new[controller]', 'Comment')
            ->withQueryParameter('tx_pwcomments_new[action]', 'create')
            ->withMethod('POST')
            ->withParsedBody($parsedBody);

        // Pre-serialize the body. testing-framework otherwise calls
        // GuzzleHttp\Psr7\Query::build() which fires "Array to string" warnings
        // on nested array structures like ours.
        $request->getBody()->write(http_build_query($parsedBody));

        return $this->executeFrontendSubRequest($request, $context);
    }

    private function countComments(): int
    {
        $queryBuilder = $this->getConnectionPool()
            ->getQueryBuilderForTable('tx_pwcomments_domain_model_comment');
        $queryBuilder->getRestrictions()->removeAll();

        return (int) $queryBuilder
            ->count('uid')
            ->from('tx_pwcomments_domain_model_comment')
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return array<string, mixed>
     */
    private function latestComment(): array
    {
        $queryBuilder = $this->getConnectionPool()
            ->getQueryBuilderForTable('tx_pwcomments_domain_model_comment');
        $queryBuilder->getRestrictions()->removeAll();

        $row = $queryBuilder
            ->select('*')
            ->from('tx_pwcomments_domain_model_comment')
            ->orderBy('uid', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($row, 'Expected at least one comment row.');
        return $row;
    }

    /**
     * @param array<string, int|string> $queryParameters
     */
    private function requestNewAction(
        array $queryParameters = [],
        ?InternalRequestContext $context = null,
    ): \Psr\Http\Message\ResponseInterface {
        // All tx_pwcomments_new arguments are cacheHash-excluded
        // (see ext_localconf.php), so no cHash is required here.
        $request = (new InternalRequest('https://example.com/'))
            ->withPageId(1)
            ->withQueryParameter('tx_pwcomments_new[controller]', 'Comment')
            ->withQueryParameter('tx_pwcomments_new[action]', 'new');

        foreach ($queryParameters as $name => $value) {
            $request = $request->withQueryParameter($name, $value);
        }

        return $this->executeFrontendSubRequest($request, $context);
    }

    private function requestVote(
        string $action,
        int $commentUid,
        ?InternalRequestContext $context = null,
    ): \Psr\Http\Message\ResponseInterface {
        // All tx_pwcomments_show vote arguments are cacheHash-excluded
        // (see ext_localconf.php), so no cHash is required here.
        $request = (new InternalRequest('https://example.com/'))
            ->withPageId(1)
            ->withQueryParameter('tx_pwcomments_show[controller]', 'Comment')
            ->withQueryParameter('tx_pwcomments_show[action]', $action)
            ->withQueryParameter('tx_pwcomments_show[comment]', $commentUid);

        return $this->executeFrontendSubRequest($request, $context);
    }

    private function countVotesFor(int $commentUid, string $authorIdent, ?int $type = null): int
    {
        $qb = $this->getConnectionPool()
            ->getQueryBuilderForTable('tx_pwcomments_domain_model_vote');
        $qb->getRestrictions()->removeAll();

        $predicates = [
            $qb->expr()->eq('comment', $qb->createNamedParameter($commentUid, Connection::PARAM_INT)),
            $qb->expr()->eq('author_ident', $qb->createNamedParameter($authorIdent)),
        ];
        if ($type !== null) {
            $predicates[] = $qb->expr()->eq('type', $qb->createNamedParameter($type, Connection::PARAM_INT));
        }

        return (int) $qb
            ->count('uid')
            ->from('tx_pwcomments_domain_model_vote')
            ->where(...$predicates)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return array<string, mixed>
     */
    private function findSingleVote(int $commentUid, string $authorIdent): array
    {
        $qb = $this->getConnectionPool()
            ->getQueryBuilderForTable('tx_pwcomments_domain_model_vote');
        $qb->getRestrictions()->removeAll();

        $rows = $qb
            ->select('*')
            ->from('tx_pwcomments_domain_model_vote')
            ->where(
                $qb->expr()->eq('comment', $qb->createNamedParameter($commentUid, Connection::PARAM_INT)),
                $qb->expr()->eq('author_ident', $qb->createNamedParameter($authorIdent)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        self::assertCount(1, $rows, 'Expected exactly one vote for comment ' . $commentUid . '.');
        return $rows[0];
    }

    /**
     * Returns the rendered markup of a single comment, bounded by its
     * `id="comment-N"` marker up to the next comment marker. Lets per-comment
     * assertions stay scoped to that comment instead of leaking across the
     * whole list.
     */
    private function commentSection(string $body, int $uid): string
    {
        $start = strpos($body, 'id="comment-' . $uid . '"');
        self::assertNotFalse($start, 'Could not find rendered comment ' . $uid . '.');

        $next = strpos($body, 'id="comment-', $start + 1);
        return $next === false ? substr($body, $start) : substr($body, $start, $next - $start);
    }

    private function firstUpvoteHref(string $body): string
    {
        self::assertSame(
            1,
            preg_match('/class="upvote" href="([^"]*)"/', $body, $matches),
            'No upvote link was rendered.',
        );

        return $matches[1];
    }
}
