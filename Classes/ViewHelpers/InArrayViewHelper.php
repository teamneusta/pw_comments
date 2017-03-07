<?php
namespace PwCommentsTeam\PwComments\ViewHelpers;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2017 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */

/**
 * InArray ViewHelper
 *
 * @package PwCommentsTeam\PwComments
 */
class InArrayViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper
{

    /**
     * Checks if the given subject is an array
     *
     * @param array $subject
     * @param string $needle
     * @return bool TRUE if given needle is in array
     */
    public function render(array $subject = null, $needle)
    {
        if ($subject === null) {
            $subject = $this->renderChildren();
        }
        return in_array($needle, $subject);
    }
}
