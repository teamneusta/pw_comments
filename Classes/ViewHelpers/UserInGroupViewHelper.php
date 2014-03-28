<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2014 Armin Ruediger Vieweg <armin@v.ieweg.de>
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
 * UserInGroup Viewhelper
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Tx_PwComments_ViewHelpers_UserInGroupViewHelper extends Tx_Fluid_Core_ViewHelper_AbstractViewHelper {

	/**
	 * Checks if the given user is member of a given group
	 *
	 * @param Tx_Extbase_Domain_Model_FrontendUser $user User to check for
	 * @param integer $inGroup Uid of group
	 *
	 * @return boolean TRUE if user is in group, otherwise FALSE
	 */
	public function render($user = NULL, $inGroup = NULL) {
		if ($user === NULL) {
			$user = $this->renderChildren();
		}

		$usergroups = $user->getUsergroup();
		if ($usergroups) {
			foreach ($usergroups as $usergroup) {
				if ($usergroup->getUid() == $inGroup) {
					return TRUE;
				}
			}
		}
		return FALSE;
	}
}

?>