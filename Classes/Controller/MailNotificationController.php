<?php
namespace T3\PwComments\Controller;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2019 Armin Vieweg <armin@v.ieweg.de>
 */
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Utility\EidUtility;

/**
 * Called by eID script "pw_comments_send_mail"
 *
 * @TODO Create middleware instead eid script. needed for TYPO3 v10
 */
class MailNotificationController
{

    public function sendMail(ServerRequest $request, ResponseInterface $response)
    {
        $params = $request->getQueryParams();
        $action = $params['action'];
        $hash = $params['hash'];
        $uid = (int) $params['uid'];
        $pid = (int) $params['pid'];

        if (!$action || !$uid || !$pid || !$hash) {
            throw new \InvalidArgumentException('Invalid arguments given.');
        }

        // Check hash
        $registry = GeneralUtility::makeInstance(Registry::class);
        $hashTimestamp = $registry->get('pw_comments', $hash);
        if (!$hashTimestamp || time() - $hashTimestamp > 60) {
            throw new \RuntimeException('Given hash not valid!');
        }
        $registry->remove('pw_comments', $hash);
        unset($hash);

        if ($action === 'sendAuthorMailWhenCommentHasBeenApproved') {
            $this->runExtbaseController(
                $request,
                'PwComments',
                'Comment',
                'sendAuthorMailWhenCommentHasBeenApproved',
                'Pi2',
                ['_commentUid' => $uid, '_skipMakingSettingsRenderable' => true],
                $pid
            );
            $response->getBody()->write('200');
            return $response;
        }

        $response->getBody()->write('Nothing happend.');
        return $response;
    }


    /**
     * Initializes and runs an extbase controller
     *
     * @param ServerRequest $request
     * @param string $extensionName Name of extension, in UpperCamelCase
     * @param string $controller Name of controller, in UpperCamelCase
     * @param string $action Optional name of action, in lowerCamelCase
     * @param string $pluginName Optional name of plugin. Default is 'Pi1'
     * @param array $settings Optional array of settings to use in controller
     * @param int $pageUid Uid of current page
     * @param string $vendorName VendorName
     * @return string output of controller's action
     * @throws \TYPO3\CMS\Core\Error\Http\ServiceUnavailableException
     * @throws \TYPO3\CMS\Core\Exception
     */
    protected function runExtbaseController(
        ServerRequest $request,
        $extensionName,
        $controller,
        $action = 'index',
        $pluginName = 'Pi1',
        $settings = [],
        $pageUid = 1,
        $vendorName = 'T3'
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

        $GLOBALS['TSFE']->fe_user = EidUtility::initFeUser();


        $pluginSettings = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_pwcomments.'];
        $pwCommentsTypoScript = $pluginSettings['settings.'];

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
                    'TYPO3\CMS\Extbase\Mvc\Web\FrontendRequestHandler' =>
                        'TYPO3\CMS\Extbase\Mvc\Web\FrontendRequestHandler'
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
