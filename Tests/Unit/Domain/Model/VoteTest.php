<?php
namespace T3\PwComments\Tests\Unit\Domain\Model;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2018 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Domain\Model\FrontendUser;
use T3\PwComments\Domain\Model\Vote;
use TYPO3\CMS\Core\Tests\UnitTestCase;

/**
 * Class VoteTest
 *
 * @package T3\PwComments
 */
class VoteTest extends UnitTestCase
{
    /**
     * @var Vote
     */
    protected $voteModel;

    /**
     * setUp function
     * @return void
     */
    protected function setUp()
    {
        $this->voteModel = new Vote();
    }

    /**
     * tearDown function
     * @return void
     */
    protected function tearDown()
    {
        unset($this->voteModel);
    }

    /**
     * Test, if type could be set
     *
     * @test
     * @return void
     */
    public function typeCanBeSet()
    {
        $type = 43;
        $this->voteModel->setType($type);
        $this->assertEquals($type, $this->voteModel->getType());
    }

    /**
     * Test, if vote an upvote
     *
     * @test
     * @dataProvider upVoteDataProvider
     * @return void
     */
    public function isUpvoteReturnsCorrectType($content, $expected)
    {
        $this->voteModel->setType($content);
        $this->assertEquals($expected, $this->voteModel->isUpvote());
    }

    /**
     * Data provider
     *
     * @return array
     */
    public function upVoteDataProvider()
    {
        return [
            'correctVote' => [1, true],
            'incorrectVote' => [90, false]
        ];
    }

    /**
     * Test, if vote an downvote
     *
     * @test
     * @dataProvider downVoteDataProvider
     * @return void
     */
    public function isDownVoteReturnsCorrectType($content, $expected)
    {
        $this->voteModel->setType($content);
        $this->assertEquals($expected, $this->voteModel->isDownvote());
    }

    /**
     * Data provider
     *
     * @return array
     */
    public function downVoteDataProvider()
    {
        return [
            'correctVote' => [0, true],
            'incorrectVote' => [90, false]
        ];
    }

    /**
     * Test, if crdate could be set
     *
     * @test
     * @return void
     */
    public function crdateCanBeSet()
    {
        $crdate = 1479500955;
        $this->voteModel->setCrdate($crdate);
        $this->assertEquals($crdate, $this->voteModel->getCrdate());
    }

    /**
     * Test, if author (fe user) could be set
     *
     * @test
     * @return void
     */
    public function authorCanBeSet()
    {
        $author = new FrontendUser('john', '12345');
        $author->setPid(23);

        $this->voteModel->setAuthor($author);
        $this->assertEquals($author, $this->voteModel->getAuthor());
    }

    /**
     * Test, if author ident could be set
     *
     * @test
     * @return void
     */
    public function authorIdentCanBeSet()
    {
        $authorIdent = 'author-ident-31';
        $this->voteModel->setAuthorIdent($authorIdent);
        $this->assertEquals($authorIdent, $this->voteModel->getAuthorIdent());
    }

    /**
     * Test, if comment could be set
     *
     * @test
     * @return void
     */
    public function commentCanBeSet()
    {
        $comment = new Comment();
        $comment->setEntryUid(54);
        $this->voteModel->setComment($comment);
        $this->assertEquals($comment, $this->voteModel->getComment());
    }
}
