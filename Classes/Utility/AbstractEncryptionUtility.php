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
use Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class AbstractUtility
 *
 * @package T3\PwComments
 */
abstract class AbstractEncryptionUtility
{
    /**
     * Get TYPO3 encryption key
     *
     * @return string
     * @throws Exception
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    protected static function getEncryptionKey()
    {
        if (empty($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'])) {
            throw new Exception('No encryption key found in this TYPO3 installation');
        }
        return $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
    }

    /**
     * Returns the configuration manager interface
     *
     * @return ConfigurationManagerInterface
     */
    public static function getConfigurationManagerInterface()
    {
        return GeneralUtility::makeInstance(ConfigurationManagerInterface::class);
    }
}
