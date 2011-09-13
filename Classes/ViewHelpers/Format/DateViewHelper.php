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
 * Formats a unix timestamp to a human-readable, localized string
 *
 * = Examples =
 * with namespace: pw
 *
 * <code title="Defaults">
 * <pw:format.date timestamp="1234567890" />
 * </code>
 *
 * Output:
 * 2009-02-13
 *
 *
 * <code title="Defaults with string">
 * <pw:format.date timestamp="2009-02-13 20:31:30GMT" />
 * </code>
 *
 * Output:
 * 2009-02-13
 *
 *
 * <code title="Defaults with DateTime object">
 * <pw:format.date timestamp="dateTimeObject" />
 * </code>
 *
 * Output:
 * 2009-02-13
 *
 *
 * <code title="Custom date format">
 * <pw:format.date format="%a, %e. %B %G" timestamp="1234567890" />
 * </code>
 *
 * Output:
 * Fre, 13. Februar 2009
 * (for german localization)
 *
 *
 * <code title="relative date">
 * <pw:format.date timestamp="1234567890" get="+1 day"/>
 * </code>
 *
 * Output:
 * 2009-02-14
 *
 *
 * <code title="relative date">
 * <pw:format.date timestamp="1234567890" get="first of this month"/>
 * </code>
 *
 * Output:
 * 2009-02-01
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @see http://www.php.net/manual/en/function.strftime.php
 * @see http://www.php.net/manual/en/function.strtotime.php
 * @see http://www.php.net/manual/en/datetime.formats.relative.php
 *
 * @author Christian Zenker <christian.zenker@599media.de>
 * @author Armin Rüdiger Vieweg <info@professorweb.de>
 */
class Tx_PwComments_ViewHelpers_Format_DateViewHelper extends Tx_Fluid_Core_ViewHelper_AbstractViewHelper {

	/**
	 * Render the supplied unix timestamp in a localized human-readable string.
	 *
	 * @param integer|string|DateTime $timestamp unix timestamp
	 * @param string $format Format String to be parsed by strftime
	 * @param string $get get some related date (see class doc)
	 * @return string Formatted date
	 */
	public function render($timestamp = NULL, $format = '%Y-%m-%d', $get = '') {

		$timestamp = $this->normalizeTimestamp($timestamp);
		if($get) {
			$timestamp = $this->modifyDate($timestamp, $get);
		}

		// Added by Armin Rüdiger Vieweg <info@professorweb.de>
		$format = preg_replace('/([a-zA-Z])/is', '%$1', $format);
		$format = str_replace('%%', '%', $format);

		return strftime($format, $timestamp);
	}

	/**
	 * handle all the different input formats and return a real timestamp
	 *
	 * @param $timestamp
	 * @return integer
	 */
	protected function normalizeTimestamp($timestamp) {
		if(is_null($timestamp)) {
			$timestamp = time();
		} elseif(is_numeric($timestamp)) {
			$timestamp = intval($timestamp);
		} elseif(is_string($timestamp)) {
			$timestamp = strtotime($timestamp);
		} elseif($timestamp instanceof DateTime) {
			$timestamp = $timestamp->format('U');
		} else {
			throw new InvalidArgumentException(sprintf('timestamp might be an integer, a string or a DateTimeObject only.'));
		}
		return $timestamp;
	}

	/**
	 * do the modification do a relative date
	 *
	 * @param $timestamp
	 * @param $get
	 * @return string
	 */
	protected function modifyDate($timestamp, $get) {
		return Tx_ViewhelperIncubator_ViewHelpers_Format_StrToTime::strtotime($get, $timestamp);
	}
}
?>