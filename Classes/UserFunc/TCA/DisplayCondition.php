<?php

declare(strict_types=1);

namespace T3\PwComments\UserFunc\TCA;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2023 Malek Olabi <m.olabi@neusta.de>
 */
use Exception;
use T3\PwComments\Utility\Settings;

/**
 * TCA Display Condition UserFunc class
 */
class DisplayCondition
{
    /**
     * Check if rating feature is enabled in extension configuration
     */
    public function isRatingEnabled(array $parameters): bool
    {
        try {
            $settings = Settings::getExtensionSettings();
            return !empty($settings['enableRating']);
        } catch (Exception $e) {
            // If settings cannot be retrieved, default to hiding the field
            return false;
        }
    }
}
