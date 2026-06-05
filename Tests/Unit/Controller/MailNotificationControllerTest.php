<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Controller;

use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use T3\PwComments\Controller\MailNotificationController;
use T3\PwComments\Utility\HashEncryptionUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;

final class MailNotificationControllerTest extends TestCase
{
    private const ENCRYPTION_KEY = 'test-encryption-key';
    private const COMMENT_MESSAGE = 'Hello world';

    private ?string $encryptionKeyBackup = null;

    protected function setUp(): void
    {
        $this->encryptionKeyBackup = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = self::ENCRYPTION_KEY;
    }

    protected function tearDown(): void
    {
        if ($this->encryptionKeyBackup === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $this->encryptionKeyBackup;
        }
    }

    #[Test]
    public function sendMailThrowsInvalidArgumentExceptionWhenActionIsMissing(): void
    {
        $controller = $this->buildController($this->buildQueryBuilder(null, expectRemoveAll: false));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(5066963646);

        $controller->sendMail($this->buildRequest([
            'action' => '',
            'hash' => 'abc',
            'uid' => 1,
            'pid' => 1,
        ]));
    }

    #[Test]
    public function sendMailThrowsInvalidArgumentExceptionWhenUidIsZero(): void
    {
        $controller = $this->buildController($this->buildQueryBuilder(null, expectRemoveAll: false));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(5066963646);

        $controller->sendMail($this->buildRequest([
            'action' => 'sendAuthorMailWhenCommentHasBeenApproved',
            'hash' => 'abc',
            'uid' => 0,
            'pid' => 1,
        ]));
    }

    #[Test]
    public function sendMailThrowsInvalidArgumentExceptionWhenPidIsZero(): void
    {
        $controller = $this->buildController($this->buildQueryBuilder(null, expectRemoveAll: false));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(5066963646);

        $controller->sendMail($this->buildRequest([
            'action' => 'sendAuthorMailWhenCommentHasBeenApproved',
            'hash' => 'abc',
            'uid' => 1,
            'pid' => 0,
        ]));
    }

    #[Test]
    public function sendMailThrowsInvalidArgumentExceptionWhenHashIsMissing(): void
    {
        $controller = $this->buildController($this->buildQueryBuilder(null, expectRemoveAll: false));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(5066963646);

        $controller->sendMail($this->buildRequest([
            'action' => 'sendAuthorMailWhenCommentHasBeenApproved',
            'hash' => '',
            'uid' => 1,
            'pid' => 1,
        ]));
    }

    #[Test]
    public function sendMailThrowsInvalidArgumentExceptionWhenTxPwcommentsKeyIsAbsent(): void
    {
        // sendMail does `$queryParams['tx_pwcomments'] ?? []`, so a request without that key
        // produces an empty params array. Accessing $params['action'] / ['hash'] then triggers
        // PHP 8 "undefined array key" warnings before the InvalidArgumentException fires.
        // Suppress the warnings and pin the current contract.
        $controller = $this->buildController($this->buildQueryBuilder(null, expectRemoveAll: false));
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn([]);

        set_error_handler(static fn() => true, \E_WARNING);
        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionCode(5066963646);
            $controller->sendMail($request);
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function sendMailThrowsTypeErrorWhenCommentRowIsNotFound(): void
    {
        // fetchAssociative() returning false makes $row['message'] resolve to null (with a
        // warning), which the strict-typed HashEncryptionUtility::validCommentMessageHash
        // rejects with a TypeError. Pinned as the current contract.
        $controller = $this->buildController($this->buildQueryBuilder(false));

        set_error_handler(static fn() => true, \E_WARNING);
        try {
            $this->expectException(\TypeError::class);
            $controller->sendMail($this->buildRequest([
                'action' => 'sendAuthorMailWhenCommentHasBeenApproved',
                'hash' => 'whatever',
                'uid' => 1,
                'pid' => 1,
            ]));
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function sendMailThrowsRuntimeExceptionWhenHashDoesNotMatch(): void
    {
        $controller = $this->buildController($this->buildQueryBuilder([
            'uid' => 1,
            'message' => self::COMMENT_MESSAGE,
            'hidden' => 1,
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(9298443636);

        $controller->sendMail($this->buildRequest([
            'action' => 'sendAuthorMailWhenCommentHasBeenApproved',
            'hash' => 'this-is-not-the-real-hash',
            'uid' => 1,
            'pid' => 1,
        ]));
    }

    #[Test]
    public function sendMailReturns200AndInvokesExtbaseControllerWhenActionMatchesAndCommentHidden(): void
    {
        $controller = $this->buildController(
            $this->buildQueryBuilder([
                'uid' => 42,
                'message' => self::COMMENT_MESSAGE,
                'hidden' => 1,
            ]),
        );

        $response = $controller->sendMail($this->buildRequest([
            'action' => 'sendAuthorMailWhenCommentHasBeenApproved',
            'hash' => HashEncryptionUtility::createHashForCommentMessage(self::COMMENT_MESSAGE),
            'uid' => 42,
            'pid' => 1,
        ]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            [
                'extensionName' => 'PwComments',
                'controller' => 'Comment',
                'action' => 'sendAuthorMailWhenCommentHasBeenApproved',
                'pluginName' => 'Pi2',
                'settings' => ['_commentUid' => 42, '_skipMakingSettingsRenderable' => true],
            ],
            $controller->extbaseInvocations[0] ?? null,
        );
    }

    #[Test]
    public function sendMailReturns400WhenActionMatchesButCommentIsNotHidden(): void
    {
        $controller = $this->buildController(
            $this->buildQueryBuilder([
                'uid' => 7,
                'message' => self::COMMENT_MESSAGE,
                'hidden' => 0,
            ]),
        );

        $response = $controller->sendMail($this->buildRequest([
            'action' => 'sendAuthorMailWhenCommentHasBeenApproved',
            'hash' => HashEncryptionUtility::createHashForCommentMessage(self::COMMENT_MESSAGE),
            'uid' => 7,
            'pid' => 1,
        ]));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame([], $controller->extbaseInvocations);
    }

    #[Test]
    public function sendMailReturns400WhenActionIsUnknownEvenIfCommentIsHidden(): void
    {
        $controller = $this->buildController(
            $this->buildQueryBuilder([
                'uid' => 7,
                'message' => self::COMMENT_MESSAGE,
                'hidden' => 1,
            ]),
        );

        $response = $controller->sendMail($this->buildRequest([
            'action' => 'someUnknownAction',
            'hash' => HashEncryptionUtility::createHashForCommentMessage(self::COMMENT_MESSAGE),
            'uid' => 7,
            'pid' => 1,
        ]));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame([], $controller->extbaseInvocations);
    }

    private function buildController(QueryBuilder $queryBuilder): MailNotificationController
    {
        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getQueryBuilderForTable')
            ->with('tx_pwcomments_domain_model_comment')
            ->willReturn($queryBuilder);

        return new class ($connectionPool, $this->createMock(TypoScriptService::class), []) extends MailNotificationController {
            public array $extbaseInvocations = [];

            protected function runExtbaseController(
                $extensionName,
                $controller,
                $action = 'index',
                $pluginName = 'show',
                $settings = [],
                $vendorName = 'T3',
            ) {
                $this->extbaseInvocations[] = [
                    'extensionName' => $extensionName,
                    'controller' => $controller,
                    'action' => $action,
                    'pluginName' => $pluginName,
                    'settings' => $settings,
                ];
                return '';
            }
        };
    }

    private function buildRequest(array $txPwcomments): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn(['tx_pwcomments' => $txPwcomments]);
        return $request;
    }

    private function buildQueryBuilder(array|false|null $row, bool $expectRemoveAll = true): QueryBuilder&MockObject
    {
        $restrictions = $this->createMock(QueryRestrictionContainerInterface::class);
        $restrictions->expects($expectRemoveAll ? self::once() : self::never())->method('removeAll');

        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('uid = :uid');

        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')->willReturn($row ?? false);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('createNamedParameter')->willReturn(':uid');
        $queryBuilder->method('executeQuery')->willReturn($result);

        return $queryBuilder;
    }
}
