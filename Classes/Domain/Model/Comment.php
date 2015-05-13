<?php
namespace PwCommentsTeam\PwComments\Domain\Model;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2015 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 */
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * The comment model
 *
 * @package PwCommentsTeam\PwComments
 */
class Comment extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity {

	/**
	 * @var int uid of entry for what the comment is for
	 */
	protected $entryUid = 0;

	/**
	 * crdate as unix timestamp
	 *
	 * @var int
	 */
	protected $crdate;

	/**
	 * hidden state
	 *
	 * @var bool
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
	 * @var int
	 */
	protected $upvoteAmount = 0;

	/**
	 * @var int
	 */
	protected $downvoteAmount = 0;

	/**
	 * @var bool
	 */
	protected $votesCounted = FALSE;

	/**
	 * The constructor.
	 */
	public function __construct() {
		$this->initializeObject();
		$this->author = GeneralUtility::makeInstance('PwCommentsTeam\PwComments\Domain\Model\FrontendUser');
		$this->votes = new ObjectStorage();
	}

	/**
	 * Getter for entryUid
	 *
	 * @return int
	 */
	public function getEntryUid() {
		return $this->entryUid;
	}

	/**
	 * Setter for entryUid
	 *
	 * @param int $entryUid
	 * @return void
	 */
	public function setEntryUid($entryUid) {
		$this->entryUid = $entryUid;
	}

	/**
	 * Setter for crdate
	 *
	 * @param int $crdate crdate
	 * @return void
	 */
	public function setCrdate($crdate) {
		$this->crdate = $crdate;
	}

	/**
	 * Getter for crdate
	 *
	 * @return int crdate
	 */
	public function getCrdate() {
		return $this->crdate;
	}

	/**
	 * Setter for hidden state
	 *
	 * @param bool $hidden
	 * @return void
	 */
	public function setHidden($hidden) {
		$this->hidden = $hidden;
	}

	/**
	 * Getter for hidden state
	 *
	 * @return bool
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
	 * @return bool
	 */
	public function hasCommentAuthorMailAddress() {
		$mailAddress = $this->getCommentAuthorMailAddress();
		return !empty($mailAddress);
	}

	/**
	 * Getter for Gravatar link by author's mail
	 *
	 * @return string Gravatar link
	 */
	public function getAuthorGravatar() {
		$link = '.gravatar.com/avatar/';
		$hash = md5(strtolower($this->getAuthorMail()));
		$domainHash = hexdec($hash[0]) % 3;
		return 'https://' . $domainHash . $link . $hash;
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
		/** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
		$objectManager = GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Object\ObjectManager');

		/** @var ConfigurationManagerInterface $configurationManager */
		$configurationManager = $objectManager->get('TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface');
		$fullTyposcript = $configurationManager->getConfiguration(
			ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
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
	 * @param QueryResultInterface $replies Containing comments
	 * @return void
	 */
	public function setReplies(QueryResultInterface $replies) {
		$this->replies = $replies;
	}

	/**
	 * Get votes
	 *
	 * @return ObjectStorage
	 */
	public function getVotes() {
		return $this->votes;
	}

	/**
	 * Set votes
	 *
	 * @param ObjectStorage $votes
	 * @return void
	 */
	public function setVotes(ObjectStorage $votes) {
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
	 * @return int
	 */
	public function getUpvoteAmount() {
		if ($this->votesCounted === FALSE) {
			$this->countVotes();
		}
		return $this->upvoteAmount;
	}

	/**
	 * Get amount of downvotes
	 *
	 * @return int
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
	 * @return int
	 */
	public function getVoteSum() {
		return $this->getUpvoteAmount() - $this->getDownvoteAmount();
	}

	/**
	 * Get count of votes
	 *
	 * @return int
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
