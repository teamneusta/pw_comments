<?php
namespace PwCommentsTeam\PwComments\Tests\Unit\Domain\Model;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2016 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016 Christian Wolfram <c.wolfram@chriwo.de>
 */
use PwCommentsTeam\PwComments\Domain\Model\Comment;
use PwCommentsTeam\PwComments\Domain\Model\FrontendUser;
use PwCommentsTeam\PwComments\Domain\Model\Vote;
use TYPO3\CMS\Core\Tests\UnitTestCase;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Class CommentTest
 *
 * @package PwCommentsTeam\PwComments
 */
class CommentTest extends UnitTestCase
{
    /**
     * @var Comment
     */
    protected $comment;

    /**
     * setUp function
     * @return void
     */
    public function setUp()
    {
        $this->comment = new Comment();
    }

    /**
     * tearDown function
     * @return void
     */
    public function tearDown()
    {
        unset($this->comment);
    }

    /**
     * Test, if entry uid could be set
     *
     * @test
     * @return void
     */
    public function entryUidCanBeSet()
    {
        $entryUid = 100;
        $this->comment->setEntryUid($entryUid);
        $this->assertEquals($entryUid, $this->comment->getEntryUid());
    }

    /**
     * Test, if author name could be set
     *
     * @test
     * @return void
     */
    public function authorNameCanBeSet()
    {
        $authorName = 'John Do';
        $this->comment->setAuthorName($authorName);
        $this->assertEquals($authorName, $this->comment->getAuthorName());
    }

    /**
     * Test, if author mail could be set
     *
     * @test
     * @return void
     */
    public function authorMailCanBeSet()
    {
        $authorMail = 'john@do.com';
        $this->comment->setAuthorMail($authorMail);
        $this->assertEquals($authorMail, $this->comment->getAuthorMail());
    }

    /**
     * Test, if comment message could be set
     *
     * @test
     * @return void
     */
    public function messageCanBeSet()
    {
        $message = 'A simples commit without any other characters';
        $this->comment->setMessage($message);
        $this->assertEquals($message, $this->comment->getMessage());
    }

    /**
     * Test, if an feuser could be set as author
     *
     * @test
     * @return void
     */
    public function authorCanBeSet()
    {
        $feUser = new FrontendUser();
        $feUser->_setProperty('uid', 1);
        $this->comment->setAuthor($feUser);
        $this->assertEquals($feUser, $this->comment->getAuthor());
    }

    /**
     * Test, if an parent comment could be set
     *
     * @test
     * @return void
     */
    public function parentCommentCanBeSet()
    {
        $parentComment = new Comment();
        $parentComment->_setProperty('uid', 23);
        $this->comment->setParentComment($parentComment);
        $this->assertEquals($parentComment, $this->comment->getParentComment());
    }

    /**
     * Test, if replies could be set
     *
     * @test
     * @return void
     */
    public function repliesCanBeSet()
    {
        $this->markTestSkipped('Test not implement yet');
    }

    /**
     * Test, if votes could be set
     *
     * @test
     * @return void
     */
    public function votesCanBeSet()
    {
        $votes = new ObjectStorage();

        $vote = new Vote();
        $vote->setType(1);
        $votes->attach($vote);
        $this->comment->setVotes($votes);

        $this->assertEquals($votes, $this->comment->getVotes());
    }

    /**
     * Test, if author ident could be set
     *
     * @test
     * @return void
     */
    public function authorIdentCanBeSet()
    {
        $authorIdent = '34348efj9fe9';
        $this->comment->setAuthorIdent($authorIdent);
        $this->assertEquals($authorIdent, $this->comment->getAuthorIdent());
    }
}
