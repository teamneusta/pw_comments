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
 * The comment model
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Comment extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity {

	/**
	 * @var integer uid of entry for what the comment is for
	 */
	protected $entryUid = 0;

	/**
	 * crdate as unix timestamp
	 *
	 * @var integer
	 */
	protected $crdate;

	/**
	 * hidden state
	 *
	 * @var boolean
	 */
	protected $hidden;

	/**
	 * The author as model or NULL if comment author wasn't logged in
	 *
	 * @var \PwCommentsTeam\PwComments\Domain\Model\FrontendUser
	 */
	protected $author = NULL;

	/**
	 * author name
	 *
	 * @var string
	 */
	protected $authorName = '';

	/**
	 * author's mail
	 *
	 * @var string
	 */
	protected $authorMail = '';

	/**
	 * @var string
	 */
	protected $authorIdent;

	/**
	 * the comment's message
	 *
	 * @var string
	 */
	protected $message;

	/**
	 * Parent comment (if set this comment is an answer). One comment can just have
	 * child comments or parent comment - not unlimited nested!
	 *
	 * @var \PwCommentsTeam\PwComments\Domain\Model\Comment
	 */
	protected $parentComment = NULL;

	/**
	 * Replies (child comments). One comment can just have child comments
	 * or parent comment - not unlimited nested!
	 *
	 * @var \TYPO3\CMS\Extbase\Persistence\Generic\QueryResult
	 */
	protected $replies = NULL;

	/**
	 * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\PwCommentsTeam\PwComments\Domain\Model\Vote>
	 */
	protected $votes;

	/**
	 * @var integer
	 */
	protected $upvoteAmount = 0;

	/**
	 * @var integer
	 */
	protected $downvoteAmount = 0;

	/**
	 * @var boolean
	 */
	protected $votesCounted = FALSE;

	/**
	 * The constructor.
	 */
	public function __construct() {
		$this->initializeObject();
		$this->author = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('PwCommentsTeam\PwComments\Domain\Model\FrontendUser');
		$this->votes = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
	}

	/**
	 * Getter for entryUid
	 *
	 * @return integer
	 */
	public function getEntryUid() {
		return $this->entryUid;
	}

	/**
	 * Setter for entryUid
	 *
	 * @param integer $entryUid
	 * @return void
	 */
	public function setEntryUid($entryUid) {
		$this->entryUid = $entryUid;
	}

	/**
	 * Setter for crdate
	 *
	 * @param integer $crdate crdate
	 * @return void
	 */
	public function setCrdate($crdate) {
		$this->crdate = $crdate;
	}

	/**
	 * Getter for crdate
	 *
	 * @return integer crdate
	 */
	public function getCrdate() {
		return $this->crdate;
	}

	/**
	 * Setter for hidden state
	 *
	 * @param boolean $hidden
	 * @return void
	 */
	public function setHidden($hidden) {
		$this->hidden = $hidden;
	}

	/**
	 * Getter for hidden state
	 *
	 * @return boolean
	 */
	public function getHidden() {
		return $this->hidden;
	}

	/**
	 * Setter for authorName
	 *
	 * @param string $authorName authorName
	 * @return void
	 */
	public function setAuthorName($authorName) {
		$this->authorName = trim($authorName);
	}

	/**
	 * Getter for authorName
	 *
	 * @return string authorName
	 */
	public function getAuthorName() {
		return $this->authorName;
	}

	/**
	 * Setter for authorMail
	 *
	 * @param string $authorMail authorMail
	 * @return void
	 */
	public function setAuthorMail($authorMail) {
		$this->authorMail = trim($authorMail);
	}

	/**
	 * Getter for authorMail
	 *
	 * @return string authorMail
	 */
	public function getAuthorMail() {
		return $this->authorMail;
	}

	/**
	 * Get email address of comment author (respecting fe_users or anonymous users)
	 *
	 * @return string
	 */
	public function getCommentAuthorMailAddress() {
		if ($this->getAuthor() !== NULL) {
			return $this->getAuthor()->getEmail();
		}
		return $this->getAuthorMail();
	}

	/**
	 * Checks if comment author has got an email address
	 *
	 * @return boolean
	 */
	public function hasCommentAuthorMailAddress() {
		$mailAddress = $this->getCommentAuthorMailAddress();
		return !empty($mailAddress);
	}

	/**
	 * Getter for gravatar link by author's mail
	 *
	 * @return string gravatar link
	 */
	public function getAuthorGravatar() {
		$link = '.gravatar.com/avatar/';
		$hash = md5(strtolower($this->getAuthorMail()));
		$domainHash = hexdec($hash[0]) % 3;
		return 'http://' . $domainHash . $link . $hash;
	}

	/**
	 * Setter for message
	 *
	 * @param string $message message
	 * @return void
	 */
	public function setMessage($message) {
		$message = trim($message);

		$threeNewLines = "\r\n\r\n\r\n";
		$twoNewLines = "\r\n\r\n";
		do {
			$message = str_replace($threeNewLines, $twoNewLines, $message);
		} while (strstr($message, $threeNewLines));

		// Decode html tags
		$message = htmlspecialchars($message);

		$settings = $this->getExtensionSettings();
		if ($settings['linkUrlsInComments']) {
			// Create links
			$message = preg_replace('/(((http(s)?\:\/\/)|(www\.))([^\s]+[^\.\s]+))/', '<a href="http$4://$5$6">$1</a>', $message);
		}

		$this->message = $message;
	}

	/**
	 * Getter for message
	 *
	 * @return string message
	 */
	public function getMessage() {
		return $this->message;
	}

	/**
	 * Setter for author
	 *
	 * @param \PwCommentsTeam\PwComments\Domain\Model\FrontendUser $author author
	 * @return void
	 */
	public function setAuthor($author) {
		$this->author = $author;
	}

	/**
	 * Getter for author
	 *
	 * @return \PwCommentsTeam\PwComments\Domain\Model\FrontendUser The author
	 */
	public function getAuthor() {
		return $this->author;
	}

	/**
	 * Returns the settings of this extension (not rendered)
	 *
	 * @return array rendered typoscript settings
	 */
	protected function getExtensionSettings() {
		/** @var $configurationManager \TYPO3\CMS\Extbase\Configuration\ConfigurationManager */
		$configurationManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Configuration\ConfigurationManager');

		$fullTyposcript = $configurationManager->getConfiguration(
			\TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
		);
		return $fullTyposcript['plugin.']['tx_pwcomments.']['settings.'];
	}

	/**
	 * Get parent comment
	 *
	 * @return \PwCommentsTeam\PwComments\Domain\Model\Comment
	 */
	public function getParentComment() {
		return $this->parentComment;
	}

	/**
	 * Set parent comment
	 *
	 * @param \PwCommentsTeam\PwComments\Domain\Model\Comment $parentComment
	 * @return void
	 */
	public function setParentComment($parentComment) {
		$this->parentComment = $parentComment;
	}

	/**
	 * Get comment replies
	 *
	 * @return \TYPO3\CMS\Extbase\Persistence\Generic\QueryResult
	 */
	public function getReplies() {
		return $this->replies;
	}

	/**
	 * Set comment replies
	 *
	 * @param \TYPO3\CMS\Extbase\Persistence\Generic\QueryResult $replies
	 * @return void
	 */
	public function setReplies(\TYPO3\CMS\Extbase\Persistence\Generic\QueryResult $replies) {
		$this->replies = $replies;
	}

	/**
	 * Get votes
	 *
	 * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage
	 */
	public function getVotes() {
		return $this->votes;
	}

	/**
	 * Set votes
	 *
	 * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage $votes
	 * @return void
	 */
	public function setVotes(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $votes) {
		$this->votes = $votes;
	}

	/**
	 * Add single vote
	 *
	 * @param \PwCommentsTeam\PwComments\Domain\Model\Vote $vote
	 * @return void
	 */
	public function addVote(\PwCommentsTeam\PwComments\Domain\Model\Vote $vote) {
		$this->votes->attach($vote);
	}

	/**
	 * Remove single vote
	 *
	 * @param \PwCommentsTeam\PwComments\Domain\Model\Vote $vote
	 * @return void
	 */
	public function removeVote(\PwCommentsTeam\PwComments\Domain\Model\Vote $vote) {
		$this->votes->detach($vote);
	}

	/**
	 * Get amount of upvotes
	 *
	 * @return integer
	 */
	public function getUpvoteAmount() {
		if ($this->votesCounted === FALSE) {
			$this->countVotes();
		}
		return $this->upvoteAmount;
	}

	/**
	 * Get amout of downvotes
	 *
	 * @return integer
	 */
	public function getDownvoteAmount() {
		if ($this->votesCounted === FALSE) {
			$this->countVotes();
		}
		return $this->downvoteAmount;
	}

	/**
	 * Get sum of up- and downvotes
	 *
	 * @return integer
	 */
	public function getVoteSum() {
		return $this->getUpvoteAmount() - $this->getDownvoteAmount();
	}

	/**
	 * Get count of votes
	 *
	 * @return integer
	 */
	public function getVoteCount() {
		return $this->getVotes()->count();
	}

	/**
	 * Count up- and downvotes
	 *
	 * @return void
	 */
	protected function countVotes() {
		/** @var $vote \PwCommentsTeam\PwComments\Domain\Model\Vote */
		foreach ($this->getVotes() as $vote) {
			if ($vote->isDownvote()) {
				$this->downvoteAmount = $this->downvoteAmount + 1;
			} else {
				$this->upvoteAmount = $this->upvoteAmount + 1;
			}
		}
		$this->votesCounted = TRUE;
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
	 * @param string $authorIdent
	 * @return void
	 */
	public function setAuthorIdent($authorIdent) {
		$this->authorIdent = $authorIdent;
	}

}
