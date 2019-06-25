<?php
namespace T3\PwComments\Utility;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License..
 *  |
 *  | (c) 2011-2018 Armin Vieweg <armin@v.ieweg.de>
 */
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Database utility
 *
 * @package T3\PwComments
 */
class DatabaseUtility
{
    /**
     * Returns a valid DatabaseConnection object that is connected and ready
     * to be used static
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    public static function getDatabaseConnection()
    {
        if (!$GLOBALS['TYPO3_DB']) {
            \TYPO3\CMS\Core\Core\Bootstrap::getInstance()->initializeTypo3DbGlobal();
        }
        return $GLOBALS['TYPO3_DB'];
    }

    /**
     * Get enabledFields for given table name, respecting TYPO3_MODE. Includes deleteClause
     *
     * @param string $tableName
     * @param bool $showHidden
     * @return string SQL where part containing enabled fields
     */
    public static function getEnabledFields($tableName, $showHidden = false)
    {
        if (TYPO3_MODE === 'BE') {
            return ($showHidden ? '' : BackendUtility::BEenableFields($tableName)) .
                BackendUtility::deleteClause($tableName);
        } else {
            /** @var $contentObjectRenderer ContentObjectRenderer */
            $contentObjectRenderer = GeneralUtility::makeInstance(
                'TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer'
            );
            return $contentObjectRenderer->enableFields($tableName, $showHidden);
        }
    }
}
