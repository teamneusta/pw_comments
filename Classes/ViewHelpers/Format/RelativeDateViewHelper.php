<?php declare(strict_types=1);

namespace T3\PwComments\ViewHelpers\Format;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 *  |     2023 Malek Olabi <m.olabi@neusta.de>
 */

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use DateTime;
use function is_numeric;
use function is_string;
use function round;
use function strtotime;
use function time;

/**
 * Formats a unix timestamp to a human-readable, relative string
 *
 * @package T3\PwComments
 */
class RelativeDateViewHelper extends AbstractViewHelper
{
    private bool $dateIsAbsolute = false;

    public function __construct(private readonly LanguageService $languageService)
    {
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('timestamp', 'mixed', 'Unix timestamp', true);
        $this->registerArgument('format', 'string', 'String to be parsed by date', true);
        $this->registerArgument('wrap', 'string', 'Uses sprintf to wrap relative date (use %s for date)', false, '%s');
        $this->registerArgument('wrapAbsolute', 'string', 'Same like wrap, but used if date is absolute', false, '%s');
    }

    /**
     * Render the supplied unix timestamp in a localized human-readable string.
     *
     * @return string Formatted date
     */
    public function render(): string
    {
        $timestamp = $this->normalizeTimestamp($this->arguments['timestamp']);
        $relativeDate = $this->makeDateRelative($timestamp, $this->arguments['format'] ?? '');
        if ($this->dateIsAbsolute === true) {
            return sprintf($this->arguments['wrapAbsolute'], $relativeDate);
        }

        return sprintf($this->arguments['wrap'], $relativeDate);
    }

    /**
     * handle all the different input formats and return a real timestamp
     *
     * @param int|string|DateTime|null $timestamp
     * @return int
     */
    protected function normalizeTimestamp(int|string|DateTime|null $timestamp): int
    {
        if (is_numeric($timestamp)) {
            $timestamp = (int) $timestamp;
        } elseif (is_string($timestamp)) {
            $timestamp = strtotime($timestamp) ?: 0;
        } elseif ($timestamp instanceof DateTime) {
            $timestamp = (int)$timestamp->format('U');
        } else {
            $timestamp = time();
        }

        return $timestamp;
    }

    /**
     * Makes a given unix timestamp relative and returns the string.
     *
     * @param int $timestamp Unix timestamp to make relative
     * @param string $format Format to use, if relative time is older than 4 weeks
     *
     * @return string Relative time or formatted time
     */
    protected function makeDateRelative(int $timestamp, string $format = ''): string
    {
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return $this->getLabel('fewSeconds');
        }

        $diff = (int)round($diff / 60);
        if ($diff < 60) {
            return $diff . ' ' . $this->getLabel('minute') . $this->plural($diff);
        }

        $diff = (int)round($diff / 60);
        if ($diff < 24) {
            return $diff . ' ' . $this->getLabel('hour') . $this->plural($diff);
        }

        $diff = (int)round($diff / 24);
        if ($diff < 7) {
            return $diff . ' ' . $this->getLabel('day') . $this->plural($diff, 'forDay');
        }

        $diff = (int)round($diff / 7);
        if ($diff < 4) {
            return $diff . ' ' . $this->getLabel('week') . $this->plural($diff);
        }

        $this->dateIsAbsolute = true;

        return date($format, $timestamp);
    }

    /**
     * Returns plural suffix, if given integer is greater than one
     *
     * @param int $num Integer which defines if it is plural or not
     * @param string $suffix Suffix to add to key of plural suffix
     * @return string Returns the plural suffix (may be empty)
     */
    private function plural(int $num, string $suffix = ''): string
    {
        return ($num > 1) ? $this->getLabel('pluralSuffix' . ucfirst($suffix)) : '';
    }

    private function getLabel(string $key): string
    {
        return $this->languageService->sL('LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:tx_pwcomments.relativeDate.' . $key);
    }
}
