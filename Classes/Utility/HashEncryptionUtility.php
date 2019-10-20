<?php
namespace T3\PwComments\Utility;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2019 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */
use T3\PwComments\Domain\Model\Comment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class HashUtility
 *
 * @package T3\PwComments
 */
class HashEncryptionUtility extends AbstractEncryptionUtility
{
    /**
     * Check if given hash is correct
     *
     * @param string $hash
     * @param Comment $comment
     * @return bool
     */
    public static function validCommentHash($hash, Comment $comment)
    {
        return self::createHashForComment($comment) === $hash;
    }

    /**
     * Create hash for a comment
     *
     * @param Comment $comment
     * @return string
     */
    public static function createHashForComment(Comment $comment)
    {
        return self::hashString($comment->getMessage());
    }

    /**
     * Create Hash from String and TYPO3 Encryption Key (if available)
     *
     * @param string $string Any String to hash
     * @param int $length Hash Length
     * @return string $hash Hashed String
     */
    protected static function hashString($string, $length = 20)
    {
        return GeneralUtility::shortMD5($string . self::getEncryptionKey(), $length);
    }
}
