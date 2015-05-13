<?php
namespace PwCommentsTeam\PwComments\ViewHelpers;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2015 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 */

/**
 * InArray ViewHelper
 *
 * @package PwCommentsTeam\PwComments
 */
class InArrayViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * Checks if the given subject is an array
	 *
	 * @param array $subject
	 * @param string $needle
	 * @return bool TRUE if given needle is in array
	 */
	public function render(array $subject = NULL, $needle) {
		if ($subject === NULL) {
			$subject = $this->renderChildren();
		}
		return in_array($needle, $subject);
	}
}