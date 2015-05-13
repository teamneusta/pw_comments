<?php
namespace PwCommentsTeam\PwComments\ViewHelpers\Format;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2015 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 */

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
 * @see http://www.php.net/manual/en/function.strftime.php
 * @see http://www.php.net/manual/en/function.strtotime.php
 * @see http://www.php.net/manual/en/datetime.formats.relative.php
 *
 * @package PwCommentsTeam\PwComments
 */
class DateViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * Render the supplied unix timestamp in a localized human-readable string.
	 *
	 * @param int|string|\DateTime $timestamp unix timestamp
	 * @param string $format Format String to be parsed by strftime
	 * @param string $get get some related date (see class doc)
	 * @return string Formatted date
	 */
	public function render($timestamp = NULL, $format = '%Y-%m-%d', $get = '') {
		$timestamp = $this->normalizeTimestamp($timestamp);
		if ($get) {
			$timestamp = $this->modifyDate($timestamp, $get);
		}
		$format = preg_replace('/([a-zA-Z])/is', '%$1', $format);
		$format = str_replace('%%', '%', $format);
		return strftime($format, $timestamp);
	}

	/**
	 * Handle all the different input formats and return a real timestamp
	 *
	 * @param int $timestamp
	 * @return int
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function normalizeTimestamp($timestamp) {
		if (is_null($timestamp)) {
			$timestamp = time();
		} elseif (is_numeric($timestamp)) {
			$timestamp = (int) $timestamp;
		} elseif (is_string($timestamp)) {
			$timestamp = strtotime($timestamp);
		} elseif ($timestamp instanceof \DateTime) {
			$timestamp = (int) $timestamp->format('U');
		} else {
			throw new \InvalidArgumentException(sprintf('Timestamp might be an integer, a string or a DateTimeObject only.'));
		}
		return $timestamp;
	}

	/**
	 * Do the modification do a relative date
	 *
	 * @param int $timestamp
	 * @param string $timeString
	 * @return string
	 */
	protected function modifyDate($timestamp, $timeString) {
		return strtotime($timeString, $timestamp);
	}
}