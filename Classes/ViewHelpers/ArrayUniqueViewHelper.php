<?php
namespace T3\PwComments\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */
/**
 * ArrayUnique ViewHelper
 *
 * @package T3\PwComments
 */
class ArrayUniqueViewHelper extends AbstractViewHelper
{

    /**
     * Removes duplicated entries in array
     *
     * @return array The filtered array
     */
    public function render()
    {
        $subject = $this->arguments['subject'];
        if ($subject === null) {
            $subject = $this->renderChildren();
        }
        return array_unique($subject);
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('subject', 'array', '', false);
    }
}
