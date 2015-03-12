<?php
namespace PwCommentsTeam\PwComments\Domain\Model;

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
 * Vote model (for comments)
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Vote extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity {
	/** Constant for upvote */
	const TYPE_UPVOTE = 1;
	/** Constant for downvote */
	const TYPE_DOWNVOTE = 0;

	/**
	 * @var integer
	 */
	protected $type;

	/**
	 * @var integer unix timestamp
	 */
	protected $crdate;

	/**
	 * @var \PwCommentsTeam\PwComments\Domain\Model\FrontendUser
	 */
	protected $author = NULL;

	/**
	 * @var string
	 */
	protected $authorIdent;

	/**
	 * @var \PwCommentsTeam\PwComments\Domain\Model\Comment
	 */
	protected $comment;

	/**
	 * Get type
	 *
	 * @return integer
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Set type
	 *
	 * @param integer $type
	 * @return void
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * Get creation date
	 *
	 * @return integer
	 */
	public function getCrdate() {
		return $this->crdate;
	}

	/**
	 * Set creation date
	 *
	 * @param integer $crdate
	 * @return void
	 */
	public function setCrdate($crdate) {
		$this->crdate = $crdate;
	}

	/**
	 * Get author (fe_user)
	 *
	 * @return \PwCommentsTeam\PwComments\Domain\Model\FrontendUser
	 */
	public function getAuthor() {
		return $this->author;
	}

	/**
	 * Set author (fe_user)
	 *
	 * @param \PwCommentsTeam\PwComments\Domain\Model\FrontendUser $author
	 * @return void
	 */
	public function setAuthor(\PwCommentsTeam\PwComments\Domain\Model\FrontendUser $author) {
		$this->author = $author;
	}

	/**
	 * Get author ident
	 *
	 * @return string
	 */
	public function getAuthorIdent() {
		return $this->authorIdent;
	}

	/**
	 * Set author ident
	 *
	 * @param string $authorIpAddress
	 * @return void
	 */
	public function setAuthorIdent($authorIpAddress) {
		$this->authorIdent = $authorIpAddress;
	}

	/**
	 * Is upvote?
	 *
	 * @return boolean
	 */
	public function isUpvote() {
		return $this->getType() === self::TYPE_UPVOTE;
	}

	/**
	 * Is downvote?
	 *
	 * @return boolean
	 */
	public function isDownvote() {
		return $this->getType() === self::TYPE_DOWNVOTE;
	}

	/**
	 * Get related comment
	 *
	 * @return \PwCommentsTeam\PwComments\Domain\Model\Comment
	 */
	public function getComment() {
		return $this->comment;
	}

	/**
	 * Set related comment
	 *
	 * @param \PwCommentsTeam\PwComments\Domain\Model\Comment $comment
	 * @return void
	 */
	public function setComment(\PwCommentsTeam\PwComments\Domain\Model\Comment $comment) {
		$this->comment = $comment;
	}

}