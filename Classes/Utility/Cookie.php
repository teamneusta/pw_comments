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
 * Cookie Utility
 *
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class Tx_PwComments_Utility_Cookie {
	/** Cookie Prefix */
	const COOKIE_PREFIX = 'tx_pwcomments_';
	/** Lifetime of cookie in days */
	const COOKIE_LIFETIME_DAYS = 365;

	/**
	 * Get cookie value
	 *
	 * @param string $key
	 * @return string|NULL
	 */
	static public function get($key) {
		if (isset($_COOKIE[self::COOKIE_PREFIX . $key])) {
			return $_COOKIE[self::COOKIE_PREFIX . $key];
		}
		return NULL;
	}

	/**
	 * Set cookie value
	 *
	 * @param string $key
	 * @param string $value
	 * @return void
	 */
	static public function set($key, $value) {
		$cookieExpireDate = time() + self::COOKIE_LIFETIME_DAYS * 24 * 60 * 60;
		setcookie(
			self::COOKIE_PREFIX . $key,
			$value,
			$cookieExpireDate,
			'/',
			self::getCookieDomain(),
			$GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieSecure'] > 0,
			$GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieHttpOnly'] == 1
		);
	}

	/**
	 * Gets the domain to be used on setting cookies.
	 * The information is taken from the value in $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'].
	 *
	 * @return string The domain to be used on setting cookies
	 */
	static protected function getCookieDomain() {
		$result = '';
		$cookieDomain = $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'];
		if (!empty($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['cookieDomain'])) {
			$cookieDomain = $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['cookieDomain'];
		}
		if ($cookieDomain) {
			if ($cookieDomain[0] == '/') {
				$match = array();
				$matchCnt = @preg_match($cookieDomain, t3lib_div::getIndpEnv('TYPO3_HOST_ONLY'), $match);
				if ($matchCnt !== FALSE) {
					$result = $match[0];
				}
			} else {
				$result = $cookieDomain;
			}
		}
		return $result;
	}
}