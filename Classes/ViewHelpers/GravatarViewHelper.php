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
 * Gravatar Viewhelper
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Tx_PwComments_ViewHelpers_GravatarViewHelper extends Tx_Fluid_Core_ViewHelper_AbstractViewHelper {

	/**
	 * Generates a gravatar link
	 *
	 * @param string $email The mail to create gravatar link for
	 * @param integer $size The size of avatar in pixel
	 * @param string $default The image to take if user has no gravatar
	 *
	 * @return string Link to gravatar
	 */
	public function render($email = NULL, $size = 100, $default = 'mm') {
		if ($email === NULL) {
			$email = $this->renderChildren();
		}

		$link = '.gravatar.com/avatar/';
		$hash = md5(strtolower(trim($email)));
		$domainHash = hexdec($hash[0]) % 3;
		$sizeParam = '?s=' . $size;
		$defaultParam = '&d=' . $default;

		return 'http://' . $domainHash . $link . $hash . $sizeParam . $defaultParam;
	}
}

?>