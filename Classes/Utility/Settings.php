<?php
namespace T3\PwComments\Utility;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 *  |     2023 Malek Olabi <m.olabi@neusta.de>
 */

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * This class provides some methods to prepare and render given
 * extension settings
 *
 * @package T3\PwComments
 */
class Settings extends AbstractEncryptionUtility
{
    /**
     * Renders a given typoscript configuration and returns the whole array with
     * calculated values.
     *
     * @param array $settings the typoscript configuration array
     * @param bool $makeSettingsRenderable If TRUE settings are renderable
     * @return array the configuration array with the rendered typoscript
     */
    public static function renderConfigurationArray(array $settings, $makeSettingsRenderable = false, ServerRequestInterface $request = null)
    {
        // @todo: this should be adjusted to use currentContentObject attribute from server request with v13
        /** @var ContentObjectRenderer|null $contentObject */
        $contentObject = self::getConfigurationManagerInterface()->getContentObject();
        if ($contentObject === null) {
            // This is a workaround until custom validators contain the current request to pass on to the current method
            return $makeSettingsRenderable ? self::makeConfigurationArrayRenderable($settings) : $settings;
        }

        if ($makeSettingsRenderable === true) {
            $settings = self::makeConfigurationArrayRenderable($settings);
        }
        $result = [];

        foreach ($settings as $key => $value) {
            if (str_ends_with($key, '.')) {
                $keyWithoutDot = substr($key, 0, -1);
                if (array_key_exists($keyWithoutDot, $settings)) {
                    $result[$keyWithoutDot] = $contentObject->cObjGetSingle(
                        is_array($settings[$keyWithoutDot])
                            ? $settings[$keyWithoutDot]['_typoScriptNodeValue'] : $settings[$keyWithoutDot],
                        $value
                    );
                } else {
                    $result[$keyWithoutDot] = self::renderConfigurationArray($value);
                }
            } else {
                if (!array_key_exists($key . '.', $settings)) {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }

    /**
     * Returns the pw_comments typoscript settings
     *
     * @return array not rendered typoscript settings
     */
    public static function getExtensionSettings()
    {
        $configurationManager = self::getConfigurationManagerInterface();
        $fullTypoScript = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );
        return $fullTypoScript['plugin.']['tx_pwcomments.']['settings.'];
    }

    /**
     * Returns the rendered settings of this extension
     *
     * @return array rendered typoscript settings
     */
    public static function getRenderedExtensionSettings()
    {
        return self::renderConfigurationArray(self::getExtensionSettings());
    }

    /**
     * Formats a given array with typoscript syntax, recursively. After the
     * transformation it can be rendered with cObjGetSingle.
     *
     * Example:
     * Before: $array['level1']['level2']['finalLevel'] = 'hello kitty'
     * After:
     * $array['level1.']['level2.']['finalLevel'] = 'hello kitty'
     * $array['level1'] = 'TEXT'
     *
     * @param array $configuration settings array to make renderable
     * @return array the renderable settings
     */
    protected static function makeConfigurationArrayRenderable(array $configuration)
    {
        $dottedConfiguration = [];
        foreach ($configuration as $key => $value) {
            if (is_array($value)) {
                if (array_key_exists('_typoScriptNodeValue', $value)) {
                    $dottedConfiguration[$key] = $value['_typoScriptNodeValue'];
                }
                $dottedConfiguration[$key . '.'] = self::makeConfigurationArrayRenderable($value);
            } else {
                $dottedConfiguration[$key] = $value;
            }
        }
        return $dottedConfiguration;
    }
}
