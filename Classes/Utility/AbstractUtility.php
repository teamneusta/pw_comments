<?php
namespace PwCommentsTeam\PwComments\Utility;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2016 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016 Christian Wolfram <c.wolfram@chriwo.de>
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class AbstractUtility
 *
 * @package TYPO3
 * @subpackage tx_pwcomments
 */
class AbstractUtility
{
    /**
     * Returns the configuration manager interface
     *
     * @return ConfigurationManagerInterface
     */
    public static function getConfigurationManagerInterface()
    {
        return self::getObjectManager()->get(ConfigurationManagerInterface::class);
    }
    
    /**
     * Returns the object manager
     *
     * @return ObjectManager
     */
    public static function getObjectManager()
    {
        return GeneralUtility::makeInstance(ObjectManager::class);
    }
}
