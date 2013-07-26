<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Armin Ruediger Vieweg <info@professorweb.de>
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
class Tx_PwComments_Domain_Model_Comment extends Tx_Extbase_DomainObject_AbstractEntity {

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
	 * @var Tx_PwComments_Domain_Model_FrontendUser
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
	 * the comment's message
	 *
	 * @var string
	 */
	protected $message;

	/**
	 * Parent comment (if set this comment is an answer). One comment can just have
	 * child comments or parent comment - not unlimited nested!
	 *
	 * @var Tx_PwComments_Domain_Model_Comment
	 */
	protected $parentComment = NULL;

	/**
	 * Replies (child comments). One comment can just have child comments
	 * or parent comment - not unlimited nested!
	 *
	 * @var Tx_Extbase_Persistence_QueryResult
	 */
	protected $_replies = NULL;

	/**
	 * The constructor.
	 */
	public function __construct() {
		$this->initializeObject();
		$this->author = t3lib_div::makeInstance('Tx_PwComments_Domain_Model_FrontendUser');
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
	 *
	 * @return void
	 */
	public function setEntryUid($entryUid) {
		$this->entryUid = $entryUid;
	}

	/**
	 * Setter for crdate
	 *
	 * @param integer $crdate crdate
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
	 * @param boolean $hidden
	 */
	public function setHidden($hidden) {
		$this->hidden = $hidden;
	}

	/**
	 * Getter for hidden state
	 * @return boolean
	 */
	public function getHidden() {
		return $this->hidden;
	}

	/**
	 * Setter for authorName
	 *
	 * @param string $authorName authorName
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
	 */
	public function setMessage($message) {
		$message = trim($message);

		$threeNewLines = "\r\n\r\n\r\n";
		$twoNewLines = "\r\n\r\n";
		do {
			$message = str_replace($threeNewLines, $twoNewLines, $message);
		}
		while (strstr($message, $threeNewLines));

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
	 * @param Tx_PwComments_Domain_Model_FrontendUser $author author
	 */
	public function setAuthor($author) {
			$this->author = $author;
	}

	/**
	 * Getter for author
	 *
	 * @return Tx_PwComments_Domain_Model_FrontendUser The author
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
		$configurationManager = t3lib_div::makeInstance('Tx_Extbase_Configuration_ConfigurationManager');

		$fullTyposcript = $configurationManager->getConfiguration(
			Tx_Extbase_Configuration_ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
		);
		return $fullTyposcript['plugin.']['tx_pwcomments' . '.']['settings.'];
	}

	/**
	 * @return Tx_PwComments_Domain_Model_Comment
	 */
	public function getParentComment() {
		return $this->parentComment;
	}

	/**
	 * @param Tx_PwComments_Domain_Model_Comment $parentComment
	 */
	public function setParentComment($parentComment) {
		$this->parentComment = $parentComment;
	}

	/**
	 * @return Tx_Extbase_Persistence_QueryResult
	 */
	public function getReplies() {
		return $this->_replies;
	}

	/**
	 * @param Tx_Extbase_Persistence_QueryResult $replies
	 */
	public function setReplies(Tx_Extbase_Persistence_QueryResult $replies) {
		$this->_replies = $replies;
	}
}
?>