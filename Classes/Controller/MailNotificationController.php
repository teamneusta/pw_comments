<?php
namespace T3\PwComments\Controller;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2023 Malek Olabi <m.olabi@neusta.de>
 */

use InvalidArgumentException;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use T3\PwComments\Utility\HashEncryptionUtility;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Core\Bootstrap;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Called by middleware request FrontendHandler
 */
class MailNotificationController
{
    private ServerRequestInterface $request;

    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly TypoScriptService $typoScriptService,
        private readonly array $extConfig,
    ) {
    }

    /**
     * Send mail
     *
     * @return ResponseInterface
     * @throws ServiceUnavailableException
     * @throws Exception
     */
    public function sendMail(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;
        $queryParams = $this->request->getQueryParams();
        $params = $queryParams['tx_pwcomments'] ?? [];
        $action = $params['action'];
        $hash = $params['hash'];
        $uid = (int) $params['uid'];
        $pid = (int) $params['pid'];

        if (!$action || !$uid || !$pid || !$hash) {
            throw new InvalidArgumentException('Invalid arguments given.');
        }

        // Get comment row
        $this->queryBuilder->getRestrictions()->removeAll();
        $row = $this->queryBuilder
            ->select('*')
            ->from('tx_pwcomments_domain_model_comment')->where($this->queryBuilder->expr()->eq(
            'uid',
            $this->queryBuilder->createNamedParameter($uid, PDO::PARAM_INT)
        ))->executeQuery()->fetchAssociative();


        // Check hash
        $valid = HashEncryptionUtility::validCommentMessageHash($hash, $row['message']);
        if (!$valid) {
            throw new RuntimeException('Given hash not valid!');
        }
        // Send mail and respond
        if ($action === 'sendAuthorMailWhenCommentHasBeenApproved' && $row['hidden']) {
            $this->runExtbaseController(
                'PwComments',
                'Comment',
                'sendAuthorMailWhenCommentHasBeenApproved',
                'Pi2',
                ['_commentUid' => $uid, '_skipMakingSettingsRenderable' => true],
            );
            $statusCode = 200; // OK
        } else {
            $statusCode = 400; // Bad request
        }

        return (new Response())->withStatus($statusCode);
    }


    /**
     * Initializes and runs an extbase controller
     *
     * @param string $extensionName Name of extension, in UpperCamelCase
     * @param string $controller Name of controller, in UpperCamelCase
     * @param string $action Optional name of action, in lowerCamelCase
     * @param string $pluginName Optional name of plugin. Default is 'Pi1'
     * @param array $settings Optional array of settings to use in controller
     * @param string $vendorName VendorName
     * @return string output of controller's action
     * @throws ServiceUnavailableException
     * @throws Exception
     */
    protected function runExtbaseController(
        $extensionName,
        $controller,
        $action = 'index',
        $pluginName = 'show',
        $settings = [],
        $vendorName = 'T3'
    ) {
        // Get plugin setup
        $typoScriptSetup = $this->getTypoScriptSetup();
        $plugin = $typoScriptSetup['plugin.']['tx_pwcomments.'];

        // Get plugin setup: settings
        $pluginSetupSettings = $this->typoScriptService->convertTypoScriptArrayToPlainArray($plugin['settings.']);
        if (is_array($this->extConfig)) {
            $settings = array_merge($settings, $this->extConfig);
        }
        $pluginSetupSettings = array_merge($settings, $pluginSetupSettings);

        // Get plugin setup: persistence
        $pluginSetupPersistence = $this->typoScriptService->convertTypoScriptArrayToPlainArray($plugin['persistence.'] ?? []);

        // Get plugin setup: _LOCAL_LANG
        $pluginSetupLocalLang = [];
        if (isset($plugin['_LOCAL_LANG.']) && is_array($plugin['_LOCAL_LANG.'])) {
            $pluginSetupLocalLang = $this->typoScriptService->convertTypoScriptArrayToPlainArray($plugin['_LOCAL_LANG.']);
        }

        // Run bootstrap with configuration
        /** @var Bootstrap $bootstrap */
        $bootstrap = GeneralUtility::makeInstance(Bootstrap::class);
        $bootstrap->setContentObjectRenderer(GeneralUtility::makeInstance(ContentObjectRenderer::class));

        $configuration = [
            'pluginName' => $pluginName,
            'extensionName' => $extensionName,
            'controller' => $controller,
            'vendorName' => $vendorName,
            'controllerConfiguration' => [$controller],
            'action' => $action,
            'settings' => $pluginSetupSettings,
            'persistence' => $pluginSetupPersistence,
            '_LOCAL_LANG' => $pluginSetupLocalLang
        ];

        return $bootstrap->run('', $configuration, $this->request);
    }

    /**
     * Returns TypoScript Setup array from a given page id
     * Adoption of same method in BackendConfigurationManager
     *
     * @return array the raw TypoScript setup
     */
    protected function getTypoScriptSetup(): array
    {
        return $this->request->getAttribute('frontend.typoscript')->getSetupArray();
    }
}
