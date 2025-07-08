<?php
namespace T3\PwComments\Domain\Model;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Vote model (for comments)
 *
 * @package T3\PwComments
 */
class Vote extends AbstractEntity
{
    /** Constant for upvote */
    final const TYPE_UPVOTE = 1;
    /** Constant for downvote */
    final const TYPE_DOWNVOTE = 0;

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
     * @var FrontendUser
     */
    protected $author;

    /**
     * @var string
     */
    protected $authorIdent;

    /**
     * @var Comment
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
    public function setOrigPid($origPid): void
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
    public function setType($type): void
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
    public function setCrdate($crdate): void
    {
        $this->crdate = $crdate;
    }

    /**
     * Get author (fe_user)
     *
     * @return FrontendUser
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * Set author (fe_user)
     *
     * @return void
     */
    public function setAuthor(FrontendUser $author): void
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
    public function setAuthorIdent($authorIpAddress): void
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
     */
    public function getComment(): ?Comment
    {
        return $this->comment;
    }

    /**
     * Set related comment
     *
     * @return void
     */
    public function setComment(Comment $comment): void
    {
        $this->comment = $comment;
    }
}
