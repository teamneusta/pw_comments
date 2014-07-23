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
 * Formats a unix timestamp to a human-readable, relative string
 */
class Tx_PwComments_ViewHelpers_Format_RelativeDateViewHelper extends Tx_Fluid_Core_ViewHelper_AbstractViewHelper {

	protected $dateIsAbsolute = FALSE;

	/**
	 * Render the supplied unix timestamp in a localized human-readable string.
	 *
	 * @param integer|string|DateTime $timestamp unix timestamp
	 * @param string $format Format String to be parsed by strftime
	 * @param string $wrap String to perform sprintf on it, to add text before or after relative date
	 * @param string $wrapAbsolute String to perform sprintf on it, if date is absolute
	 *
	 * @return string Formatted date
	 */
	public function render($timestamp = NULL, $format = NULL, $wrap = '%s', $wrapAbsolute = '%s') {
		$this->dateIsAbsolute = FALSE;
		$timestamp = $this->normalizeTimestamp($timestamp);
		$relativeDate = $this->makeDateRelative($timestamp, $format);
		if ($this->dateIsAbsolute === TRUE) {
			return sprintf($wrapAbsolute, $relativeDate);
		}
		return sprintf($wrap, $relativeDate);
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
	 * Makes a given unixtimestamp relative and returns the string.
	 *
	 * @param integer $timestamp unixtimestamp to make relative
	 * @param string $format Format to use, if relative time is longer ago than 4 weeks
	 *
	 * @return string relative time or formated time
	 */
	protected function makeDateRelative($timestamp, $format = NULL) {
		$diff = time() - $timestamp;
		if ($diff < 60) {
			return $this->getLabel('fewSeconds');
		}

		$diff = round($diff / 60);
		if ($diff < 60) {
			return $diff . ' ' . $this->getLabel('minute') . $this->plural($diff);
		}

		$diff = round($diff / 60);
		if ($diff < 24) {
			return $diff . ' ' . $this->getLabel('hour') . $this->plural($diff);
		}

		$diff = round($diff / 24);
		if ($diff < 7) {
			return $diff . ' ' . $this->getLabel('day') . $this->plural($diff, 'forDay');
		}

		$diff = round($diff / 7);
		if ($diff < 4) {
			return $diff . ' ' . $this->getLabel('week') . $this->plural($diff);
		}

		$this->dateIsAbsolute = TRUE;
		return strftime($format, $timestamp);
	}

	/**
	 * Returns plural suffix, if given integer is greater than one
	 *
	 * @param integer $num Integer which defines if it is plural or not
	 * @param string $suffix Suffix to add to key of plural suffix. Default is '' (empty).
	 *
	 * @return string Returns the plural suffix, which makes a time measure to plural (i.e. Stunde -> Stunden)
	 */
	protected function plural($num, $suffix = '') {
		if ($num > 1) {
			return $this->getLabel('pluralSuffix' . ucfirst($suffix));
		}
	}

	/**
	 * Shortcut for translate method
	 *
	 * @param string $key the key as string
	 *
	 * @return string string which matches the key, containing in locallang.xml
	 */
	protected function getLabel($key) {
		return Tx_Extbase_Utility_Localization::translate('tx_pwcomments.relativeDate.' . $key , 'PwComments');
	}

}
?>