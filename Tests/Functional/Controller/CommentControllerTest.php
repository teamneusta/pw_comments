<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Functional\Controller;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\Test;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Domain\Model\Vote;
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
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/pw_comments',
    ];

    protected function setUp(): void
    {
        parent::setUp();

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

    #[Test]
    public function indexActionRendersStoredComments(): void
    {
        $body = $this->renderPage();

        self::assertStringContainsString('Test comment 1', $body);
        self::assertStringContainsString('Test comment 2', $body);
        // crdate (a passthrough column) must still be mapped/read for display:
        // comment 1's crdate 1609459200 renders as 01.01.2021.
        self::assertStringContainsString('01.01.2021', $body);
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
     * End-to-end regression test for #64 and #65: a logged-in frontend user
     * submits the new-comment form and the comment is actually persisted.
     *
     * Before the fixes this request died with a 500:
     *  - #64: the int fe_users.uid was assigned to the typed string
     *    Comment::$authorIdent ("Cannot assign int to property ... of type string").
     *  - #65: $crdate / $hidden were never initialized on the auto-approved path
     *    ("Typed property ... must not be accessed before initialization").
     */
    #[Test]
    public function loggedInFrontendUserCanCreateComment(): void
    {
        $message = 'A brand new comment posted by a logged-in user';

        // Send the form fields as an encoded body string (not a parsed-body
        // array): the testing framework serializes an array body with
        // GuzzleHttp\Psr7\Query::build(), which cannot handle the nested
        // newComment[...] structure. http_build_query() can, and the framework
        // parse_str()s the string back into the correct nested parsed body.
        $body = http_build_query([
            'tx_pwcomments_new' => [
                'newComment' => [
                    'authorName' => '',
                    'authorMail' => '',
                    'message' => $message,
                ],
                '__trustedProperties' => $this->trustedPropertiesForNewComment(),
            ],
        ]);

        $request = (new InternalRequest('https://example.com/'))
            ->withPageId(1)
            ->withMethod('POST')
            ->withQueryParameter('tx_pwcomments_new[controller]', 'Comment')
            ->withQueryParameter('tx_pwcomments_new[action]', 'create')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody(Utils::streamFor($body));

        $response = $this->executeFrontendSubRequest(
            $request,
            (new InternalRequestContext())->withFrontendUserId(1),
        );

        // A TypeError from #64/#65 surfaced as a 500; the success path redirects.
        self::assertLessThan(400, $response->getStatusCode(), 'Creating a comment must not error.');

        $comment = $this->storedCommentByMessage($message);
        self::assertNotEmpty($comment, 'The comment was not persisted.');
        self::assertSame('1', $comment['author_ident'], 'fe_users uid must be stored as a string (#64).');
        self::assertSame(1, (int) $comment['author'], 'The comment must be linked to the logged-in user.');
        self::assertSame(0, (int) $comment['hidden'], 'An auto-approved comment must be visible (#65).');
        self::assertGreaterThan(0, (int) $comment['crdate'], 'crdate must be framework-stamped on persist. Row: ' . json_encode($comment));
    }

    /**
     * Voting shares the create flow's failure modes: createNewVote() also calls
     * setPid($this->commentStorageUid) (the int-cast fix, #setPid) and stamps a
     * framework-managed crdate. This proves a logged-in user can upvote and the
     * Vote persists correctly. Vote's properties are untyped, so it has no
     * #64/#65 TypeError, and its crdate (null default) is not clobbered.
     */
    #[Test]
    public function loggedInFrontendUserCanUpvoteComment(): void
    {
        $request = (new InternalRequest('https://example.com/'))
            ->withPageId(1)
            ->withQueryParameter('tx_pwcomments_show[controller]', 'Comment')
            ->withQueryParameter('tx_pwcomments_show[action]', 'upvote')
            ->withQueryParameter('tx_pwcomments_show[comment]', '2');

        $response = $this->executeFrontendSubRequest(
            $request,
            (new InternalRequestContext())->withFrontendUserId(1),
        );

        self::assertLessThan(400, $response->getStatusCode(), 'Voting must not error.');

        $vote = $this->storedVoteForComment(2, '1');
        self::assertNotEmpty($vote, 'The vote was not persisted.');
        self::assertSame(1, (int) $vote['pid'], 'Vote pid must be the int storagePid (setPid fix).');
        self::assertSame(1, (int) $vote['author'], 'The vote must be linked to the logged-in user.');
        self::assertSame(Vote::TYPE_UPVOTE, (int) $vote['type'], 'An upvote must be stored.');
        self::assertGreaterThan(0, (int) $vote['crdate'], 'Vote crdate must be framework-stamped on persist.');
    }

    /**
     * @return array<string, mixed>
     */
    private function storedVoteForComment(int $commentUid, string $authorIdent): array
    {
        $queryBuilder = $this->getConnectionPool()
            ->getQueryBuilderForTable('tx_pwcomments_domain_model_vote');
        $queryBuilder->getRestrictions()->removeAll();

        $row = $queryBuilder
            ->select('uid', 'pid', 'author', 'author_ident', 'type', 'crdate')
            ->from('tx_pwcomments_domain_model_vote')
            ->where(
                $queryBuilder->expr()->eq('comment', $queryBuilder->createNamedParameter($commentUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('author_ident', $queryBuilder->createNamedParameter($authorIdent)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: [];
    }

    /**
     * Mint the Extbase property-mapping HMAC the FormViewHelper would emit for
     * the new-comment form, so property mapping accepts the submitted fields.
     */
    private function trustedPropertiesForNewComment(): string
    {
        return $this->get(MvcPropertyMappingConfigurationService::class)
            ->generateTrustedPropertiesToken(
                [
                    'tx_pwcomments_new[newComment][authorName]',
                    'tx_pwcomments_new[newComment][authorMail]',
                    'tx_pwcomments_new[newComment][message]',
                ],
                'tx_pwcomments_new',
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function storedCommentByMessage(string $message): array
    {
        $queryBuilder = $this->getConnectionPool()
            ->getQueryBuilderForTable('tx_pwcomments_domain_model_comment');
        $queryBuilder->getRestrictions()->removeAll();

        $row = $queryBuilder
            ->select('uid', 'author', 'author_ident', 'hidden', 'crdate')
            ->from('tx_pwcomments_domain_model_comment')
            ->where(
                $queryBuilder->expr()->eq(
                    'message',
                    $queryBuilder->createNamedParameter($message),
                ),
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row ?: [];
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
