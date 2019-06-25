<?php
namespace T3\PwComments\Domain\Model;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2018 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */

/**
 * Vote model (for comments)
 *
 * @package T3\PwComments
 */
class Vote extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /** Constant for upvote */
    const TYPE_UPVOTE = 1;
    /** Constant for downvote */
    const TYPE_DOWNVOTE = 0;

    /**
     * @var int uid of the page for what the comment is for
     */
    protected $origPid = 0;

    /**
     * @var int
     */
    protected $type;

    /**
     * @var int unix timestamp
     */
    protected $crdate;

    /**
     * @var \T3\PwComments\Domain\Model\FrontendUser
     */
    protected $author = null;

    /**
     * @var string
     */
    protected $authorIdent;

    /**
     * @var \T3\PwComments\Domain\Model\Comment
     */
    protected $comment;

    /**
     * Getter for origPid
     *
     * @return int
     */
    public function getOrigPid()
    {
        return $this->origPid;
    }

    /**
     * Setter for origPid
     *
     * @param int $origPid
     * @return void
     */
    public function setOrigPid($origPid)
    {
        $this->origPid = $origPid;
    }

    /**
     * Get type
     *
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set type
     *
     * @param int $type
     * @return void
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get creation date
     *
     * @return int
     */
    public function getCrdate()
    {
        return $this->crdate;
    }

    /**
     * Set creation date
     *
     * @param int $crdate
     * @return void
     */
    public function setCrdate($crdate)
    {
        $this->crdate = $crdate;
    }

    /**
     * Get author (fe_user)
     *
     * @return \T3\PwComments\Domain\Model\FrontendUser
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * Set author (fe_user)
     *
     * @param \T3\PwComments\Domain\Model\FrontendUser $author
     * @return void
     */
    public function setAuthor(\T3\PwComments\Domain\Model\FrontendUser $author)
    {
        $this->author = $author;
    }

    /**
     * Get author ident
     *
     * @return string
     */
    public function getAuthorIdent()
    {
        return $this->authorIdent;
    }

    /**
     * Set author ident
     *
     * @param string $authorIpAddress
     * @return void
     */
    public function setAuthorIdent($authorIpAddress)
    {
        $this->authorIdent = $authorIpAddress;
    }

    /**
     * Is upvote?
     *
     * @return bool
     */
    public function isUpvote()
    {
        return $this->getType() === self::TYPE_UPVOTE;
    }

    /**
     * Is downvote?
     *
     * @return bool
     */
    public function isDownvote()
    {
        return $this->getType() === self::TYPE_DOWNVOTE;
    }

    /**
     * Get related comment
     *
     * @return \T3\PwComments\Domain\Model\Comment
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Set related comment
     *
     * @param \T3\PwComments\Domain\Model\Comment $comment
     * @return void
     */
    public function setComment(\T3\PwComments\Domain\Model\Comment $comment)
    {
        $this->comment = $comment;
    }
}
