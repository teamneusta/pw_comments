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
 * Flash Messages Viewhelper
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Tx_PwComments_ViewHelpers_FlashMessagesViewHelper extends Tx_Fluid_ViewHelpers_FlashMessagesViewHelper {

	/**
	 * Overwritten render method.
	 *
	 * @param string $renderMode one of the RENDER_MODE_* constants
	 * @param integer $severity limit show of flash messages to given severity. If NULL all flashmessages are shown.
	 *
	 * @return string rendered Flash Messages, if there are any.
	 * @author Sebastian Kurfürst <sebastian@typo3.org>
	 * @author Armin Rüdiger Vieweg <armin.vieweg@diemedialen.de>
	 * @api
	 */
	public function render($renderMode = self::RENDER_MODE_UL, $severity = NULL) {
		$allFlashMessages = $this->controllerContext->getFlashMessageContainer()->getAllMessagesAndFlush();
		$flashMessages = array();

		if ($severity !== NULL) {
			/** @var $flashMessage t3lib_FlashMessage */
			foreach ($allFlashMessages as $flashMessage) {
				if ($flashMessage->getSeverity() == $severity) {
					$flashMessages[] = $flashMessage;
				}
			}
		} else {
			$flashMessages = $allFlashMessages;
		}


		if ($flashMessages === NULL || count($flashMessages) === 0) {
			return '';
		}
		switch ($renderMode) {
			case self::RENDER_MODE_UL:
				return $this->renderUl($flashMessages);
			case self::RENDER_MODE_DIV:
				return $this->renderDiv($flashMessages);
			default:
				throw new Tx_Fluid_Core_ViewHelper_Exception('Invalid render mode "' . $renderMode . '" passed to FlashMessageViewhelper', 1290697924);
		}
	}
}

?>