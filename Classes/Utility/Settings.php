<?php
namespace PwCommentsTeam\PwComments\Utility;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2016 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016 Christian Wolfram <c.wolfram@chriwo.de>
 */
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * This class provides some methods to prepare and render given
 * extension settings
 *
 * @package PwCommentsTeam\PwComments
 */
class Settings extends AbstractUtility
{
    /**
     * Renders a given typoscript configuration and returns the whole array with
     * calculated values.
     *
     * @param array $settings the typoscript configuration array
     * @param bool $makeSettingsRenderable If TRUE settings are renderable
     * @return array the configuration array with the rendered typoscript
     */
    public static function renderConfigurationArray(array $settings, $makeSettingsRenderable = false)
    {
        $contentObject = self::getConfigurationManagerInterface()->getContentObject();

        if ($makeSettingsRenderable === true) {
            $settings = self::makeConfigurationArrayRenderable($settings);
        }
        $result = [];

        foreach ($settings as $key => $value) {
            if (substr($key, -1) === '.') {
                $keyWithoutDot = substr($key, 0, -1);
                if (array_key_exists($keyWithoutDot, $settings)) {
                    $result[$keyWithoutDot] = $contentObject->cObjGetSingle(
                        $settings[$keyWithoutDot],
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
