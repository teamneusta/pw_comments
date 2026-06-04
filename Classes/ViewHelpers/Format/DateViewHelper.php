<?php

declare(strict_types=1);

namespace T3\PwComments\ViewHelpers\Format;

use DateTime;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 *  |     2023 Malek Olabi <m.olabi@neusta.de>
 */
/**
 * Formats a unix timestamp to a human-readable string.
 *
 * The `format` argument follows PHP's date()/DateTime::format() syntax. Legacy
 * strftime-style inputs with a leading `%` on each format letter are tolerated:
 * the helper strips `%` before formatting, so both `Y-m-d` and `%Y-%m-%d`
 * produce the same output. Literal `%` characters in the format are not
 * supported.
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
 * <code title="PHP date()-style format">
 * <pw:format.date format="d.m.Y" timestamp="1234567890" />
 * </code>
 *
 * Output:
 * 13.02.2009
 *
 *
 * <code title="relative date">
 * <pw:format.date timestamp="1234567890" get="+1 day"/>
 * </code>
 *
 * Output:
 * 2009-02-14
 *
 * @see https://www.php.net/manual/en/datetime.format.php
 * @see https://www.php.net/manual/en/function.strtotime.php
 * @see https://www.php.net/manual/en/datetime.formats.relative.php
 */
class DateViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('timestamp', 'mixed', 'unix timestamp', false);
        $this->registerArgument('format', 'string', 'Format string (PHP date()-style; legacy strftime %-prefixed inputs are tolerated)', false, '%Y-%m-%d');
        $this->registerArgument('get', 'string', 'get some related date (see class doc)', false, '');
    }

    /**
     * Render the supplied unix timestamp in a localized human-readable string.
     *
     * @return string Formatted date
     */
    public function render(): string
    {
        $timestamp = $this->normalizeTimestamp($this->arguments['timestamp']);
        if ($this->arguments['get']) {
            $timestamp = $this->modifyDate($timestamp, $this->arguments['get']);
        }
        $format = str_replace('%', '', (string) $this->arguments['format']);

        return (new \DateTime())->setTimestamp($timestamp)->format($format);
    }

    /**
     * Handle all the different input formats and return a real timestamp
     */
    protected function normalizeTimestamp(int|string|\DateTimeInterface|null $timestamp): int|bool
    {
        if ($timestamp === null) {
            $timestamp = time();
        } elseif (is_numeric($timestamp)) {
            $timestamp = (int) $timestamp;
        } elseif (\is_string($timestamp)) {
            $timestamp = strtotime($timestamp);
        } elseif ($timestamp instanceof \DateTimeInterface) {
            $timestamp = (int) $timestamp->format('U');
        }
        return $timestamp;
    }

    /**
     * Apply a strtotime modifier (e.g. "+1 day") relative to the given timestamp.
     */
    protected function modifyDate(int $timestamp, string $timeString): int
    {
        $modified = strtotime($timeString, $timestamp);
        if ($modified === false) {
            throw new \InvalidArgumentException(
                sprintf('Could not parse relative date string "%s".', $timeString),
                1780358400,
            );
        }
        return $modified;
    }
}
