<?php
namespace T3\PwComments\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use T3\PwComments\Controller\MailNotificationController;
use TYPO3\CMS\Core\Exception;

/**
 * Middleware frontend handler
 * Acts on a given action request parameter
 */
class FrontendHandler implements MiddlewareInterface
{
    public function __construct(private readonly MailNotificationController $notificationController)
    {
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
        $queryParams = $request->getQueryParams();
        $params = $queryParams['tx_pwcomments'] ?? [];
        if (!empty($params) && isset($params['action']) && $params['action'] === 'sendAuthorMailWhenCommentHasBeenApproved') {
            $mailSendResponse = $this->notificationController->sendMail($request);
            $mailSendResponse->getBody()->write((string)$mailSendResponse->getStatusCode());

            return $mailSendResponse;
        }

        return $handler->handle($request);
    }
}
