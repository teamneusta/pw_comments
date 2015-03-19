<?php
namespace PwCommentsTeam\PwComments\Domain\Repository;

/***************************************************************
*  Copyright notice
*
*  (c) 2011-2014 Armin Ruediger Vieweg <armin@v.ieweg.de>
*
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Repository for \PwCommentsTeam\PwComments\Domain\Model\Comment
 *
 * @package PwCommentsTeam\PwComments
 */
class CommentRepository extends \TYPO3\CMS\Extbase\Persistence\Repository {
	/**
	 * @var bool
	 */
	protected $invertCommentSorting = FALSE;

	/**
	 * @var bool
	 */
	protected $invertReplySorting = FALSE;

	/**
	 * Initializes the repository.
	 *
	 * @return void
	 * @see \TYPO3\CMS\Extbase\Persistence\Repository::initializeObject()
	 */
	public function initializeObject() {
		/** @var $querySettings \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings */
		$querySettings = $this->objectManager->get('TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings');
		$querySettings->setRespectStoragePage(FALSE);
		$this->setDefaultQuerySettings($querySettings);
	}

	/**
	 * Find comments by pid
	 *
	 * @param int $pid pid to get comments for
	 * @return \TYPO3\CMS\Extbase\Persistence\QueryResult found comments
	 */
	public function findByPid($pid) {
		$query = $this->createQuery();
		$query->matching(
			$query->logicalAnd(
				$query->equals('pid', $pid),
				$query->equals('parentComment', 0)
			)
		);
		$query->setOrderings(array('crdate' => $this->getCommentSortingDirection()));
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
	 * @return \TYPO3\CMS\Extbase\Persistence\QueryResult found comments
	 */
	public function findByPidAndEntryUid($pid, $entryUid) {
		$query = $this->createQuery();
		$query->matching(
			$query->logicalAnd(
				array(
					$query->equals('pid', $pid),
					$query->equals('entryUid', $entryUid),
					$query->equals('parentComment', 0)
				)
			)
		);
		$query->setOrderings(array('crdate' => $this->getCommentSortingDirection()));
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
	 * @return \PwCommentsTeam\PwComments\Domain\Model\Comment
	 */
	public function findByCommentUid($uid) {
		$query = $this->createQuery();
		if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) >= 6000000) {
			$query->getQuerySettings()->setIgnoreEnableFields(TRUE);
		} else {
			$query->getQuerySettings()->setRespectEnableFields(FALSE);
		}
		$query->matching($query->equals('uid', $uid));
		$comment = $query->execute()->getFirst();
		$this->findAndAttachCommentReplies($comment);
		return $comment;
	}

	/**
	 * Find replies by given comment and attaches them to replies attribute.
	 *
	 * @param \PwCommentsTeam\PwComments\Domain\Model\Comment $comment
	 * @return void
	 */
	protected function findAndAttachCommentReplies(\PwCommentsTeam\PwComments\Domain\Model\Comment $comment) {
		$query = $this->createQuery();
		$query->matching(
			$query->equals('parentComment', $comment->getUid())
		);
		$query->setOrderings(array('crdate' => $this->getReplySortingDirection()));
		$comment->setReplies($query->execute());
	}

	/**
	 * Returns order direction for comments
	 *
	 * @return string
	 */
	public function getCommentSortingDirection() {
		if ($this->getInvertCommentSorting() === TRUE) {
			return \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_DESCENDING;
		}
		return \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING;
	}

	/**
	 * Gets invert comment sorting flag
	 *
	 * @return bool
	 */
	public function getInvertCommentSorting() {
		return $this->invertCommentSorting;
	}

	/**
	 * Sets invert comment sorting flag
	 *
	 * @param bool $invertCommentSorting
	 * @return void
	 */
	public function setInvertCommentSorting($invertCommentSorting) {
		$this->invertCommentSorting = $invertCommentSorting;
	}

	/**
	 * Returns order direction for replies
	 *
	 * @return string
	 */
	public function getReplySortingDirection() {
		if ($this->getInvertReplySorting() === TRUE) {
			return \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_DESCENDING;
		}
		return \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING;
	}

	/**
	 * Gets invert reply sorting flag
	 *
	 * @return bool
	 */
	public function getInvertReplySorting() {
		return $this->invertReplySorting;
	}

	/**
	 * Sets invert reply sorting flag
	 *
	 * @param bool $invertReplySorting
	 * @return void
	 */
	public function setInvertReplySorting($invertReplySorting) {
		$this->invertReplySorting = $invertReplySorting;
	}
}
