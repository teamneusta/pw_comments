<?php
namespace T3\PwComments\Utility;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2019 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */

/**
 * Class StringUtility
 *
 * @package T3\PwComments
 */
class StringUtility
{
    /**
     * Create links
     *
     * @param string $value
     * @return string
     */
    public static function createLinks($value)
    {
        return preg_replace(
            '/(((http(s)?\:\/\/)|(www\.))([^\s]+[^\.\s]+))/',
            '<a href="http$4://$5$6">$1</a>',
            $value
        );
    }

    /**
     * Convert tripple lines into two lines
     *
     * @param string  $value
     * @return string
     */
    public static function convertTrippleLinesToDoubleLines($value)
    {
        $threeNewLines = "\r\n\r\n\r\n";
        $twoNewLines = "\r\n\r\n";
        do {
            $value = str_replace($threeNewLines, $twoNewLines, $value);
        } while (strstr($value, $threeNewLines));

        return $value;
    }

    /**
     * Prepare the comment message before it saved in database
     *
     * @param string $message comment message
     * @param bool $allowLinks
     * @return string
     */
    public static function prepareCommentMessage($message, $allowLinks = false)
    {
        $message = trim($message);
        $message = static::convertTrippleLinesToDoubleLines($message);
        $message = htmlspecialchars($message);

        if ($allowLinks) {
            $message = static::createLinks($message);
        }

        return $message;
    }
}
