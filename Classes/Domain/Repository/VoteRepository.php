<?php
namespace PwCommentsTeam\PwComments\Domain\Repository;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2017 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */
use PwCommentsTeam\PwComments\Domain\Model\Comment;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

/**
 * Repository for votes
 *
 * @package PwCommentsTeam\PwComments
 */
class VoteRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{
    /**
     * Initializes the repository.
     *
     * @return void
     * @see \TYPO3\CMS\Extbase\Persistence\Repository::initializeObject()
     */
    public function initializeObject()
    {
        /** @var $querySettings Typo3QuerySettings */
        $querySettings = $this->objectManager->get('TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings');
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Find votes by pid
     *
     * @param int $pid pid to get comments for
     * @param string $authorIdent
     * @return \TYPO3\CMS\Extbase\Persistence\Generic\QueryResult found votes
     */
    public function findByPidAndAuthorIdent($pid, $authorIdent)
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('pid', $pid),
                $query->equals('authorIdent', $authorIdent)
            )
        );
        return $query->execute();
    }

    /**
     * Find vote by given comment and authorIdent
     *
     * @param Comment $comment
     * @param string $authorIdent
     * @return \PwCommentsTeam\PwComments\Domain\Model\Vote
     */
    public function findOneByCommentAndAuthorIdent(Comment $comment, $authorIdent)
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('comment', $comment),
                $query->equals('authorIdent', $authorIdent)
            )
        );
        return $query->execute()->getFirst();
    }
}
