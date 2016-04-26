<?php
namespace PwCommentsTeam\PwComments\Utility;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2015 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 */

/**
 * This class provides some methods to prepare and render given
 * extension settings
 *
 * @package PwCommentsTeam\PwComments
 */
class Settings
{
    /**
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    protected $contentObject;

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     * @inject
     */
    protected $configurationManager = null;


    /**
     * Initialize this settings utility
     *
     * @return void
     */
    public function initializeObject()
    {
        $this->contentObject = $this->configurationManager->getContentObject();
    }

    /**
     * Renders a given typoscript configuration and returns the whole array with
     * calculated values.
     *
     * @param array $settings the typoscript configuration array
     * @param bool $makeSettingsRenderable If TRUE settings are renderable
     * @return array the configuration array with the rendered typoscript
     */
    public function renderConfigurationArray(array $settings, $makeSettingsRenderable = false)
    {
        if ($makeSettingsRenderable === true) {
            $settings = $this->makeConfigurationArrayRenderable($settings);
        }
        $result = array();

        foreach ($settings as $key => $value) {
            if (substr($key, -1) === '.') {
                $keyWithoutDot = substr($key, 0, -1);
                if (array_key_exists($keyWithoutDot, $settings)) {
                    $result[$keyWithoutDot] = $this->contentObject->cObjGetSingle(
                        $settings[$keyWithoutDot],
                        $value
                    );
                } else {
                    $result[$keyWithoutDot] = $this->renderConfigurationArray($value);
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
    protected function makeConfigurationArrayRenderable(array $configuration)
    {
        $dottedConfiguration = array();
        foreach ($configuration as $key => $value) {
            if (is_array($value)) {
                if (array_key_exists('_typoScriptNodeValue', $value)) {
                    $dottedConfiguration[$key] = $value['_typoScriptNodeValue'];
                }
                $dottedConfiguration[$key . '.'] = $this->makeConfigurationArrayRenderable($value);
            } else {
                $dottedConfiguration[$key] = $value;
            }
        }
        return $dottedConfiguration;
    }
}
