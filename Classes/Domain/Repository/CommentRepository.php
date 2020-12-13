<?php
namespace T3\PwComments\Domain\Repository;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2021 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\Generic\QueryResult;
use T3\PwComments\Domain\Model\Comment;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Repository for \T3\PwComments\Domain\Model\Comment
 *
 * @package T3\PwComments
 */
class CommentRepository extends Repository
{
    /**
     * @var bool
     */
    protected $invertCommentSorting = false;

    /**
     * @var bool
     */
    protected $invertReplySorting = false;

    /**
     * Initializes the repository.
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        parent::__construct($objectManager);

        /** @var Typo3QuerySettings $querySettings */
        $querySettings = $this->objectManager->get(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Find comments by pid
     *
     * @param int $pid pid to get comments for
     * @return object|QueryResult<Comment> found comments
     */
    public function findByPid($pid)
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                [
                    $query->equals('pid', $pid),
                    $query->equals('parentComment', 0)
                ]
            )
        );
        $query->setOrderings(['crdate' => $this->getCommentSortingDirection()]);
        $comments = $query->execute();

        foreach ($comments as $comment) {
            $this->findAndAttachCommentReplies($comment);
        };
        return $comments;
    }

    /**
     * Find comments by pid and entry uid
     *
     * @param int $pid pid to get comments for
     * @param int $entryUid entry id to get comments for
     * @return object|QueryResult<Comment> found comments
     */
    public function findByPidAndEntryUid($pid, $entryUid)
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                [
                    $query->equals('pid', $pid),
                    $query->equals('entryUid', $entryUid),
                    $query->equals('parentComment', 0)
                ]
            )
        );
        $query->setOrderings(['crdate' => $this->getCommentSortingDirection()]);
        $comments = $query->execute();

        foreach ($comments as $comment) {
            $this->findAndAttachCommentReplies($comment);
        };
        return $comments;
    }

    /**
     * Find comment by uid
     *
     * @param int $uid
     * @return Comment|null
     */
    public function findByCommentUid($uid)
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setIgnoreEnableFields(true);
        $query->matching($query->equals('uid', $uid));

        /** @var Comment|null $comment */
        $comment = $query->execute()->getFirst();
        if ($comment) {
            $this->findAndAttachCommentReplies($comment);
        }
        return $comment;
    }

    /**
     * Find replies by given comment and attaches them to replies attribute.
     *
     * @param Comment $comment
     * @return void
     */
    protected function findAndAttachCommentReplies(Comment $comment)
    {
        $query = $this->createQuery();
        $query->matching(
            $query->equals('parentComment', $comment->getUid())
        );
        $query->setOrderings(['crdate' => $this->getReplySortingDirection()]);
        $comment->setReplies($query->execute());
    }

    /**
     * Returns order direction for comments
     *
     * @return string
     */
    public function getCommentSortingDirection()
    {
        if ($this->getInvertCommentSorting() === true) {
            return QueryInterface::ORDER_DESCENDING;
        }
        return QueryInterface::ORDER_ASCENDING;
    }

    /**
     * Gets invert comment sorting flag
     *
     * @return bool
     */
    public function getInvertCommentSorting()
    {
        return $this->invertCommentSorting;
    }

    /**
     * Sets invert comment sorting flag
     *
     * @param bool $invertCommentSorting
     * @return void
     */
    public function setInvertCommentSorting($invertCommentSorting)
    {
        $this->invertCommentSorting = $invertCommentSorting;
    }

    /**
     * Returns order direction for replies
     *
     * @return string
     */
    public function getReplySortingDirection()
    {
        if ($this->getInvertReplySorting() === true) {
            return QueryInterface::ORDER_DESCENDING;
        }
        return QueryInterface::ORDER_ASCENDING;
    }

    /**
     * Gets invert reply sorting flag
     *
     * @return bool
     */
    public function getInvertReplySorting()
    {
        return $this->invertReplySorting;
    }

    /**
     * Sets invert reply sorting flag
     *
     * @param bool $invertReplySorting
     * @return void
     */
    public function setInvertReplySorting($invertReplySorting)
    {
        $this->invertReplySorting = $invertReplySorting;
    }
}
