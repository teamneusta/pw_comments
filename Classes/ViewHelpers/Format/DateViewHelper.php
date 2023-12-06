<?php declare(strict_types=1);

namespace T3\PwComments\ViewHelpers\Format;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use DateTime;
use InvalidArgumentException;
use function is_numeric;
use function is_string;
use function strtotime;
use function time;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 *  |     2023 Malek Olabi <m.olabi@neusta.de>
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
 * @package T3\PwComments
 */
class DateViewHelper extends AbstractViewHelper
{
    /**
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('timestamp', 'mixed', 'unix timestamp', false);
        $this->registerArgument('format', 'string', 'Format String to be parsed by DateTime', false, 'Y-m-d');
        $this->registerArgument('get', 'string', 'get some related date (see class doc)', false, '');
    }

    /**
     * Render the supplied unix timestamp in a localized human-readable string.
     *
     * @return string Formatted date
     */
    public function render(): string
    {
        $timestamp = $this->normalizeTimestamp($this->arguments['timestamp'] ?? null);
        if (!empty($this->arguments['get'])) {
            $timestamp = $this->modifyDate($timestamp, $this->arguments['get']);
        }
        // be backwards compatible and replace old format with new format
        $format = str_replace('%', '', (string)($this->arguments['format'] ?? ''));

        return (new DateTime())->setTimestamp($timestamp)->format($format);
    }

    /**
     * Handle all the different input formats and return a real timestamp
     *
     *
     * @throws InvalidArgumentException
     */
    protected function normalizeTimestamp(int|string|null|DateTime $timestamp): int
    {
        if (is_numeric($timestamp)) {
            $timestamp = (int)$timestamp;
        } elseif (is_string($timestamp)) {
            $timestamp = (strtotime($timestamp) ?: 0);
        } elseif ($timestamp instanceof DateTime) {
            $timestamp = (int)$timestamp->format('U');
        } else {
            $timestamp = time();
        }

        return $timestamp;
    }

    /**
     * Do the modification do a relative date
     *
     * @param int $timestamp
     * @param string $timeString
     * @return int
     */
    protected function modifyDate(int $timestamp, string $timeString): int
    {
        return (strtotime($timeString, $timestamp) ?: 0);
    }
}
