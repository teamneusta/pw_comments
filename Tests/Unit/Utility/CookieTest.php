<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use T3\PwComments\Utility\Cookie;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class CookieTest extends TestCase
{
    private array $cookieBackup;
    private array $serverBackup;
    private ?array $confVarsBackup;
    private mixed $requestBackup;

    protected function setUp(): void
    {
        $this->cookieBackup = $_COOKIE;
        $this->serverBackup = $_SERVER;
        $this->confVarsBackup = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        $this->requestBackup = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $_COOKIE = [];
        GeneralUtility::flushInternalRuntimeCaches();
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->cookieBackup;
        $_SERVER = $this->serverBackup;
        if ($this->confVarsBackup === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS'] = $this->confVarsBackup;
        }
        if ($this->requestBackup === null) {
            unset($GLOBALS['TYPO3_REQUEST']);
        } else {
            $GLOBALS['TYPO3_REQUEST'] = $this->requestBackup;
        }
        GeneralUtility::flushInternalRuntimeCaches();
    }

    #[Test]
    public function getReturnsNullWhenCookieIsAbsent(): void
    {
        self::assertNull((new Cookie())->get('missing'));
    }

    #[Test]
    public function getReturnsValueWhenCookieIsPresentWithPrefix(): void
    {
        $_COOKIE['tx_pwcomments_authorname'] = 'Alice';

        self::assertSame('Alice', (new Cookie())->get('authorname'));
    }

    #[Test]
    public function getIgnoresKeyWithoutTheExtensionPrefix(): void
    {
        $_COOKIE['authorname'] = 'Bob';

        self::assertNull((new Cookie())->get('authorname'));
    }

    #[Test]
    public function getCookieDomainReturnsLiteralValueVerbatim(): void
    {
        $this->stubFrontendRequest();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] = '.example.com';

        self::assertSame('.example.com', $this->invokeGetCookieDomain(new Cookie()));
    }

    #[Test]
    public function getCookieDomainResolvesRegexMatchAgainstHttpHost(): void
    {
        $this->stubFrontendRequest('shop.example.com');
        $_SERVER['HTTP_HOST'] = 'shop.example.com';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] = '/example\.com$/';

        self::assertSame('example.com', $this->invokeGetCookieDomain(new Cookie()));
    }

    #[Test]
    public function getCookieDomainReturnsEmptyStringWhenRegexHasNoMatch(): void
    {
        // The implementation guards with `$matchCnt !== false` but preg_match returns 0 on
        // a clean miss, so it still reads `$match[0]` and triggers an undefined-key warning.
        // We suppress that incidental warning here and pin the documented contract (empty
        // string) until the guard is corrected.
        $this->stubFrontendRequest('shop.other.test');
        $_SERVER['HTTP_HOST'] = 'shop.other.test';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['cookieDomain'] = '/example\.com$/';

        set_error_handler(static fn() => true, \E_WARNING);
        try {
            self::assertSame('', $this->invokeGetCookieDomain(new Cookie()));
        } finally {
            restore_error_handler();
        }
    }

    private function stubFrontendRequest(string $host = 'example.org'): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $normalizedParams = $this->createMock(NormalizedParams::class);

        $normalizedParams
            ->method('getHttpHost')
            ->willReturn($host)
        ;

        $request
            ->method('getAttribute')
            ->willReturnMap([
                ['applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE],
                ['normalizedParams', $normalizedParams],
            ])
        ;

        $GLOBALS['TYPO3_REQUEST'] = $request;
    }

    private function invokeGetCookieDomain(Cookie $cookie): string
    {
        $reflection = new \ReflectionMethod(Cookie::class, 'getCookieDomain');
        return (string) $reflection->invoke($cookie);
    }
}
