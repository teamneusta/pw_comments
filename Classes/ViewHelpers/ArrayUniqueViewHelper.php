<?php
namespace PwCommentsTeam\PwComments\ViewHelpers;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2018 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */

/**
 * ArrayUnique ViewHelper
 *
 * @package PwCommentsTeam\PwComments
 */
class ArrayUniqueViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper
{

    /**
     * Removes duplicated entries in array
     *
     * @param array $subject
     * @return array The filtered array
     */
    public function render(array $subject = null)
    {
        if ($subject === null) {
            $subject = $this->renderChildren();
        }
        return array_unique($subject);
    }
}
