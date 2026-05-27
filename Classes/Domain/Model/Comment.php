<?php

declare(strict_types=1);

namespace T3\PwComments\Domain\Model;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 *  |     2023 Malek Olabi <m.olabi@neusta.de>
 */
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * The comment model
 */
class Comment extends AbstractEntity
{
    /**
     * @var int uid of the page for what the comment is for
     */
    protected int $origPid = 0;

    /**
     * @var int uid of entry for what the comment is for
     */
    protected int $entryUid = 0;

    /**
     * crdate as unix timestamp
     */
    protected int $crdate;

    /**
     * hidden state
     */
    protected bool $hidden;

    /**
     * The author as model or NULL if comment author wasn't logged in
     *
     * @var FrontendUser
     */
    protected $author;

    /**
     * author name
     */
    protected string $authorName = '';

    /**
     * author's mail
     */
    protected string $authorMail = '';

    protected string $authorIdent;

    /**
     * the comment's message
     */
    protected string $message;

    /**
     * Parent comment (if set this comment is an answer). One comment can just have
     * child comments or parent comment - not unlimited nested!
     *
     * @var Comment
     */
    protected $parentComment;

    /**
     * Replies (child comments). One comment can just have child comments
     * or parent comment - not unlimited nested!
     *
     * @var QueryResult
     */
    protected $replies;

    /**
     * @var ObjectStorage<Vote>
     */
    protected $votes;

    protected int $upvoteAmount = 0;

    protected int $downvoteAmount = 0;

    protected bool $votesCounted = false;

    protected bool $termsAccepted = false;

    protected int $rating = 0;

    /**
     * @var string|null
     */
    protected $aiModerationStatus;

    /**
     * @var string|null
     */
    protected $aiModerationReason;

    /**
     * @var float|null
     */
    protected $aiModerationConfidence;

    /**
     * The constructor.
     */
    public function __construct()
    {
        $this->author = GeneralUtility::makeInstance(FrontendUser::class);
        $this->votes = new ObjectStorage();
    }

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
     */
    public function setOrigPid($origPid): void
    {
        $this->origPid = $origPid;
    }

    /**
     * Getter for entryUid
     *
     * @return int
     */
    public function getEntryUid()
    {
        return $this->entryUid;
    }

    /**
     * Setter for entryUid
     *
     * @param int $entryUid
     */
    public function setEntryUid($entryUid): void
    {
        $this->entryUid = $entryUid;
    }

    /**
     * Setter for crdate
     *
     * @param int $crdate crdate
     */
    public function setCrdate($crdate): void
    {
        $this->crdate = $crdate;
    }

    /**
     * Getter for crdate
     *
     * @return int crdate
     */
    public function getCrdate()
    {
        return $this->crdate;
    }

    /**
     * Setter for hidden state
     *
     * @param bool $hidden
     */
    public function setHidden($hidden): void
    {
        $this->hidden = $hidden;
    }

    /**
     * Getter for hidden state
     *
     * @return bool
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Setter for authorName
     *
     * @param string $authorName authorName
     */
    public function setAuthorName($authorName): void
    {
        $this->authorName = trim($authorName);
    }

    /**
     * Getter for authorName
     *
     * @return string authorName
     */
    public function getAuthorName()
    {
        return $this->authorName;
    }

    /**
     * Setter for authorMail
     *
     * @param string $authorMail authorMail
     */
    public function setAuthorMail($authorMail): void
    {
        $this->authorMail = trim($authorMail);
    }

    /**
     * Getter for authorMail
     *
     * @return string authorMail
     */
    public function getAuthorMail()
    {
        return $this->authorMail;
    }

    /**
     * Get email address of comment author (respecting fe_users or anonymous users)
     */
    public function getCommentAuthorMailAddress(): string
    {
        return $this->getAuthor()?->getEmail() ?? $this->getAuthorMail();
    }

    /**
     * Checks if comment author has got an email address
     *
     * @return bool
     */
    public function hasCommentAuthorMailAddress()
    {
        $mailAddress = $this->getCommentAuthorMailAddress();
        return !empty($mailAddress);
    }

    /**
     * Setter for message
     *
     * @param string $message message
     */
    public function setMessage($message): void
    {
        $this->message = $message;
    }

    /**
     * Getter for message
     *
     * @return string message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Setter for author
     *
     * @param FrontendUser|null $author author
     */
    public function setAuthor(?FrontendUser $author): void
    {
        $this->author = $author;
    }

    /**
     * Getter for author
     *
     * @return FrontendUser|null The author
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * Get parent comment
     *
     * @return Comment
     */
    public function getParentComment()
    {
        return $this->parentComment;
    }

    /**
     * Set parent comment
     *
     * @param Comment $parentComment
     */
    public function setParentComment($parentComment): void
    {
        $this->parentComment = $parentComment;
    }

    /**
     * Get comment replies
     *
     * @return QueryResult
     */
    public function getReplies()
    {
        return $this->replies;
    }

    /**
     * Set comment replies
     *
     * @param QueryResult $replies Containing comments
     */
    public function setReplies(QueryResult $replies): void
    {
        $this->replies = $replies;
    }

    /**
     * Get votes
     *
     * @return ObjectStorage
     */
    public function getVotes()
    {
        return $this->votes;
    }

    /**
     * Set votes
     */
    public function setVotes(ObjectStorage $votes): void
    {
        $this->votes = $votes;
    }

    /**
     * Add single vote
     */
    public function addVote(Vote $vote): void
    {
        $this->votes->attach($vote);
    }

    /**
     * Remove single vote
     */
    public function removeVote(Vote $vote): void
    {
        $this->votes->detach($vote);
    }

    /**
     * Get amount of upvotes
     *
     * @return int
     */
    public function getUpvoteAmount()
    {
        if ($this->votesCounted === false) {
            $this->countVotes();
        }
        return $this->upvoteAmount;
    }

    /**
     * Get amount of downvotes
     *
     * @return int
     */
    public function getDownvoteAmount()
    {
        if ($this->votesCounted === false) {
            $this->countVotes();
        }
        return $this->downvoteAmount;
    }

    /**
     * Get sum of up- and downvotes
     *
     * @return int
     */
    public function getVoteSum()
    {
        return $this->getUpvoteAmount() - $this->getDownvoteAmount();
    }

    /**
     * Get count of votes
     *
     * @return int
     */
    public function getVoteCount()
    {
        return $this->getVotes()->count();
    }

    /**
     * Count up- and downvotes
     */
    protected function countVotes()
    {
        /** @var Vote $vote */
        foreach ($this->getVotes() as $vote) {
            if ($vote->isDownvote()) {
                $this->downvoteAmount = $this->downvoteAmount + 1;
            } else {
                $this->upvoteAmount = $this->upvoteAmount + 1;
            }
        }
        $this->votesCounted = true;
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
     * @param string $authorIdent
     */
    public function setAuthorIdent($authorIdent): void
    {
        $this->authorIdent = $authorIdent;
    }

    /**
     * @return bool
     */
    public function getTermsAccepted()
    {
        return $this->termsAccepted;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): void
    {
        $this->rating = $rating;
    }

    /**
     * @param bool $termsAccepted
     */
    public function setTermsAccepted($termsAccepted): void
    {
        $this->termsAccepted = $termsAccepted;
    }

    /**
     * Get AI moderation status
     */
    public function getAiModerationStatus(): ?string
    {
        return $this->aiModerationStatus;
    }

    /**
     * Set AI moderation status
     */
    public function setAiModerationStatus(?string $aiModerationStatus): void
    {
        $this->aiModerationStatus = $aiModerationStatus;
    }

    /**
     * Get AI moderation reason
     */
    public function getAiModerationReason(): ?string
    {
        return $this->aiModerationReason;
    }

    /**
     * Set AI moderation reason
     */
    public function setAiModerationReason(?string $aiModerationReason): void
    {
        $this->aiModerationReason = $aiModerationReason;
    }

    /**
     * Get AI moderation confidence score
     */
    public function getAiModerationConfidence(): ?float
    {
        return $this->aiModerationConfidence;
    }

    /**
     * Set AI moderation confidence score
     */
    public function setAiModerationConfidence(?float $aiModerationConfidence): void
    {
        $this->aiModerationConfidence = $aiModerationConfidence;
    }
}
