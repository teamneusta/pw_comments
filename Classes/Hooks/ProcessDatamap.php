<?php
namespace PwCommentsTeam\PwComments\Hooks;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2016 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016 Christian Wolfram <c.wolfram@chriwo.de>
 */
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * ProcessDatamap Hook
 *
 * @package PwCommentsTeam\PwComments
 */
class ProcessDatamap
{
    /** @var array */
    protected $enabledTables = ['tx_pwcomments_domain_model_comment'];

    /** @var array */
    protected $enabledStatus = ['update'];

    /**
     * After Save hook
     *
     * @param string $status
     * @param  string $table
     * @param  int $id
     * @param array $fieldArray
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $pObj
     * @return void
     */
    public function processDatamap_postProcessFieldArray($status, $table, $id, $fieldArray, $pObj)
    {
        if (in_array($table, $this->enabledTables) && in_array($status, $this->enabledStatus)) {
            if (isset($fieldArray['hidden']) && $fieldArray['hidden'] == 0) {
                $row = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
                    '*',
                    'tx_pwcomments_domain_model_comment',
                    'uid=' . $id
                );

                $this->runExtbaseController(
                    'PwComments',
                    'Comment',
                    'sendAuthorMailWhenCommentHasBeenApproved',
                    'Pi2',
                    ['_commentUid' => $row['uid'], '_skipMakingSettingsRenderable' => true],
                    intval($row['pid'])
                );
            }
        }
    }

    /**
     * Initializes and runs an extbase controller
     *
     * @param string $extensionName Name of extension, in UpperCamelCase
     * @param string $controller Name of controller, in UpperCamelCase
     * @param string $action Optional name of action, in lowerCamelCase
     * @param string $pluginName Optional name of plugin. Default is 'Pi1'
     * @param array $settings Optional array of settings to use in controller
     * @param int $pageUid Uid of current page
     * @param string $vendorName VendorName
     * @return string output of controller's action
     */
    protected function runExtbaseController(
        $extensionName,
        $controller,
        $action = 'index',
        $pluginName = 'Pi1',
        $settings = [],
        $pageUid = 0,
        $vendorName = 'PwCommentsTeam'
    ) {
        $GLOBALS['TT'] = GeneralUtility::makeInstance('TYPO3\CMS\Core\TimeTracker\TimeTracker');
        $GLOBALS['TSFE'] = GeneralUtility::makeInstance(
            'TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController',
            $GLOBALS['TYPO3_CONF_VARS'],
            $pageUid,
            0
        );
        $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance('TYPO3\CMS\Frontend\Page\PageRepository');
        $GLOBALS['TSFE']->initTemplate();
        $rootline = $GLOBALS['TSFE']->sys_page->getRootLine($pageUid);
        $GLOBALS['TSFE']->tmpl->start($rootline);
        $GLOBALS['TSFE']->getConfigArray();

        $pluginSettings = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_pwcomments.'];
        $pwCommentsTypoScript = $pluginSettings['settings.'];

        \TYPO3\CMS\Frontend\Utility\EidUtility::initLanguage('de');
        \TYPO3\CMS\Frontend\Utility\EidUtility::initTCA();
        \TYPO3\CMS\Frontend\Utility\EidUtility::initExtensionTCA('pw_comments');

        if (unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pw_comments'])) {
            $settings = array_merge(
                $settings,
                unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pw_comments'])
            );
        }
        $settings = array_merge($settings, $pwCommentsTypoScript);

        $bootstrap = new \TYPO3\CMS\Extbase\Core\Bootstrap();
        $bootstrap->cObj = GeneralUtility::makeInstance('TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer');

        $extensionTyposcriptSetup = $this->getExtensionTyposcriptSetup();

        $localLangArray = [];
        if (is_array($pluginSettings['_LOCAL_LANG.'])) {
            $typoScriptService = GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Service\TypoScriptService');
            $localLangArray = $typoScriptService->convertTypoScriptArrayToPlainArray($pluginSettings['_LOCAL_LANG.']);
        }
        $configuration = [
            'pluginName' => $pluginName,
            'extensionName' => $extensionName,
            'controller' => $controller,
            'vendorName' => $vendorName,
            'controllerConfiguration' => [$controller],
            'action' => $action,
            'mvc' => [
                'requestHandlers' => [
                    'TYPO3\CMS\Extbase\Mvc\Web\FrontendRequestHandler' => 'TYPO3\CMS\Extbase\Mvc\Web\FrontendRequestHandler'
                ]
            ],
            'settings' => $settings,
            'persistence' => $extensionTyposcriptSetup['plugin']['tx_pwcomments']['persistence'],
            '_LOCAL_LANG' => $localLangArray
        ];

        return $bootstrap->run('', $configuration);
    }

    /**
     * Gets the typoscript setup defined in ext_typoscript_setup.txt as array
     *
     * @return array
     */
    protected function getExtensionTyposcriptSetup()
    {
        /** @var $tsParser \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser */
        $tsParser = GeneralUtility::makeInstance('TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser');
        $tsParser->parse(
            file_get_contents(
                ExtensionManagementUtility::extPath('pw_comments') . 'ext_typoscript_setup.txt'
            )
        );
        $typoScriptService = GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Service\TypoScriptService');
        return $typoScriptService->convertTypoScriptArrayToPlainArray($tsParser->setup);
    }
}
