<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use T3\PwComments\Controller\MailNotificationController;
use T3\PwComments\Middleware\FrontendHandler;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;

final class FrontendHandlerTest extends TestCase
{
    private const MATCHING_ACTION = 'sendAuthorMailWhenCommentHasBeenApproved';

    public static function delegationCases(): array
    {
        return [
            'tx_pwcomments missing from query' => [[]],
            'tx_pwcomments is an empty array' => [['tx_pwcomments' => []]],
            'tx_pwcomments present but action key missing' => [
                ['tx_pwcomments' => ['other' => 'value']],
            ],
            'action set to a different string' => [
                ['tx_pwcomments' => ['action' => 'somethingElse']],
            ],
            'tx_pwcomments is a scalar string' => [
                ['tx_pwcomments' => 'foo'],
            ],
        ];
    }

    #[Test]
    #[DataProvider('delegationCases')]
    public function processDelegatesToHandlerAndReturnsItsExactResponse(array $queryParams): void
    {
        $request = (new ServerRequest())->withQueryParams($queryParams);
        $delegatedResponse = new Response();

        $notificationController = $this->createMock(MailNotificationController::class);
        $notificationController->expects(self::never())->method('sendMail');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn($delegatedResponse);

        $middleware = new FrontendHandler($notificationController);

        self::assertSame($delegatedResponse, $middleware->process($request, $handler));
    }

    #[Test]
    public function processInvokesMailControllerAndReturnsItsResponseWhenActionMatches(): void
    {
        $request = (new ServerRequest())
            ->withQueryParams(['tx_pwcomments' => ['action' => self::MATCHING_ACTION]]);
        $controllerResponse = new Response('php://memory', 418);

        $notificationController = $this->createMock(MailNotificationController::class);
        $notificationController
            ->expects(self::once())
            ->method('sendMail')
            ->with($request)
            ->willReturn($controllerResponse);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $middleware = new FrontendHandler($notificationController);

        $response = $middleware->process($request, $handler);

        self::assertSame($controllerResponse, $response);
        self::assertSame('', (string) $response->getBody());
    }
}
