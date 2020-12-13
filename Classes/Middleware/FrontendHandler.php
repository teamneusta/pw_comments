<?php
namespace T3\PwComments\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use T3\PwComments\Controller\MailNotificationController;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Middleware frontend handler
 * Acts on a given action request parameter
 */
class FrontendHandler implements MiddlewareInterface
{
    /**
     * @var TypoScriptFrontendController
     */
    protected $typoScriptFrontendController;

    /**
     * @param TypoScriptFrontendController $typoScriptFrontendController
     */
    public function __construct(TypoScriptFrontendController $typoScriptFrontendController = null)
    {
        $this->typoScriptFrontendController = $typoScriptFrontendController ?? $GLOBALS['TSFE'];
    }

    /**
     * Check tx_pwcomments parameters and solve action
     * - sendAuthorMailWhenCommentHasBeenApproved
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $params = (array)$request->getQueryParams()['tx_pwcomments'] ?? [];
        if (!empty($params) && isset($params['action'])) {
            if ($params['action'] === 'sendAuthorMailWhenCommentHasBeenApproved') {
                /** @var MailNotificationController $mailNotification */
                $mailNotification = GeneralUtility::makeInstance(MailNotificationController::class);
                $nullResponse = new NullResponse();
                /** @var ResponseInterface $mailSendResponse */
                $mailSendResponse = $mailNotification->sendMail($request, $nullResponse);
                $statusCode = $mailSendResponse->getStatusCode();
                $response = new Response();
                $response->withStatus($statusCode);
                $response->getBody()->write((string)$statusCode);
                return $response;
            }
        }
        return $handler->handle($request);
    }
}
