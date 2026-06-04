<?php

declare(strict_types=1);

namespace T3\PwCommentsRequestFactoryDouble;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Client\GuzzleClientFactory;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;

/**
 * Functional-test double for TYPO3's RequestFactory. Captures every outbound
 * request and returns a canned response. State lives on FakeRequestRegistry
 * because `RequestFactory` is `readonly`, and readonly classes can't declare
 * static state.
 */
final readonly class FakeRequestFactory extends RequestFactory
{
    public function __construct(GuzzleClientFactory $guzzleFactory)
    {
        parent::__construct($guzzleFactory);
    }

    public function request(string $uri, string $method = 'GET', array $options = [], ?string $context = null): ResponseInterface
    {
        FakeRequestRegistry::$calls[] = ['url' => $uri, 'method' => $method, 'options' => $options];

        if (FakeRequestRegistry::$nextException !== null) {
            throw FakeRequestRegistry::$nextException;
        }

        $body = new Stream('php://temp', 'rw');
        $body->write(FakeRequestRegistry::$nextBody);
        $body->rewind();

        return new Response($body, FakeRequestRegistry::$nextStatus);
    }
}
