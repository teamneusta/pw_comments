<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Utility\HashEncryptionUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
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
            ['EXT:pw_comments/Tests/Fixtures/Frontend/BasicSetup.typoscript']
        );
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
            'Foreign GET parameter was dropped from the generated voting link (issue #40).'
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
                    $queryBuilder->createNamedParameter($commentUid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchOne();

        return (int)$hidden;
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

        return (string)$response->getBody();
    }

    private function firstUpvoteHref(string $body): string
    {
        self::assertSame(
            1,
            preg_match('/class="upvote" href="([^"]*)"/', $body, $matches),
            'No upvote link was rendered.'
        );

        return $matches[1];
    }
}
