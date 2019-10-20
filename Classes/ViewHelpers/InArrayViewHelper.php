<?php
namespace T3\PwComments\ViewHelpers;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2019 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */

/**
 * InArray ViewHelper
 */
class InArrayViewHelper extends \TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper
{
    /**
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('subject', 'array', 'The subject');
        $this->registerArgument('needle', 'string', '', true);
    }

    /**
     * Checks if the given subject is an array
     *
     * @return bool TRUE if given needle is in array
     */
    public function render()
    {
        $subject = $this->arguments['subject'];
        if ($subject === null) {
            $subject = $this->renderChildren();
        }
        return \in_array($this->arguments['needle'], $subject, true);
    }
}
