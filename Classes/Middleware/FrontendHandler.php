<?php

declare(strict_types=1);

namespace T3\PwComments\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use T3\PwComments\Controller\MailNotificationController;

/**
 * Middleware frontend handler
 * Acts on a given action request parameter
 */
class FrontendHandler implements MiddlewareInterface
{
    public function __construct(private readonly MailNotificationController $notificationController) {}

    /**
     * Check tx_pwcomments parameters and solve action
     * - sendAuthorMailWhenCommentHasBeenApproved
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $params = $request->getQueryParams()['tx_pwcomments'] ?? [];
        if (($params['action'] ?? null) === 'sendAuthorMailWhenCommentHasBeenApproved') {
            return $this->notificationController->sendMail($request);
        }

        return $handler->handle($request);
    }
}
