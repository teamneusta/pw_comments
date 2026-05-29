<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Utility\HashEncryptionUtility;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Database\Connection;
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

    protected array $coreExtensionsToLoad = [
        'install',
    ];

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/pw_comments',
    ];

    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport' => 'mbox',
            'transport_mbox_file' => self::MBOX_FILE,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        @unlink(self::MBOX_FILE);

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
        parent::tearDown();
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

    private function capturedMail(): string
    {
        self::assertFileExists(self::MBOX_FILE, 'Mbox file was never written - no mail was sent.');
        return (string) file_get_contents(self::MBOX_FILE);
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
