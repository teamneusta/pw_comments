<?php

declare(strict_types=1);

namespace T3\PwComments\Domain\Repository;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 *  |     2023 Malek Olabi <m.olabi@neusta.de>
 */

use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Domain\Model\Vote;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Repository for votes
 *
 * @extends Repository<Vote>
 */
class VoteRepository extends Repository
{
    /**
     * Initializes the repository.
     *
     * @see \TYPO3\CMS\Extbase\Persistence\Repository::initializeObject()
     */
    public function initializeObject(): void
    {
        /** @var QuerySettingsInterface $querySettings */
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
        $this->defaultQuerySettings->setRespectStoragePage(false);
    }

    /**
     * Find votes by pid
     *
     * @param int $pid pid to get comments for
     * @param string $authorIdent
     * @return QueryResultInterface found votes
     */
    public function findByPidAndAuthorIdent($pid, $authorIdent): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('pid', $pid),
                $query->equals('authorIdent', $authorIdent),
            ),
        );
        return $query->execute();
    }

    /**
     * Find vote by given comment and authorIdent
     *
     * @param string $authorIdent
     */
    public function findOneByCommentAndAuthorIdent(Comment $comment, $authorIdent): ?object
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('comment', $comment),
                $query->equals('authorIdent', $authorIdent),
            ),
        );
        return $query->execute()->getFirst();
    }
}
