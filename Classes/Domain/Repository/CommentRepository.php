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
 * Repository for Tx_PwComments_Domain_Model_Comment
 *
 * @version $Id$
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Tx_PwComments_Domain_Repository_CommentRepository extends Tx_Extbase_Persistence_Repository {

	/**
	 * Initializes the repository.
	 *
	 * @return void
	 *
	 * @see Tx_Extbase_Persistence_Repository::initializeObject()
	 */
	public function initializeObject() {
		$querySettings = $this->objectManager->create('Tx_Extbase_Persistence_Typo3QuerySettings');
		$querySettings->setRespectStoragePage(FALSE);
		$this->setDefaultQuerySettings($querySettings);
	}

	public function findByPid($pid) {
		$query = $this->createQuery();
		$query->matching(
			$query->equals('pid', $pid)
		);
		$query->setOrderings(array('crdate' => Tx_Extbase_Persistence_QueryInterface::ORDER_ASCENDING));
		return $query->execute();
	}
}
?>