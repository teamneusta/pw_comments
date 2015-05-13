<?php
namespace PwCommentsTeam\PwComments\ViewHelpers;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2015 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 */

/**
 * ArrayUnique ViewHelper
 *
 * @package PwCommentsTeam\PwComments
 */
class ArrayUniqueViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * Removes duplicated entries in array
	 *
	 * @param array $subject
	 * @return array The filtered array
	 */
	public function render(array $subject = NULL) {
		if ($subject === NULL) {
			$subject = $this->renderChildren();
		}
		return array_unique($subject);
	}
}