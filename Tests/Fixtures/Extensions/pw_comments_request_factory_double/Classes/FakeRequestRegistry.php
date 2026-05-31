<?php

declare(strict_types=1);

namespace T3\PwCommentsRequestFactoryDouble;

/**
 * Static state for FakeRequestFactory. Lives on its own class because
 * RequestFactory is readonly and forbids static properties on its children.
 */
final class FakeRequestRegistry
{
    /** @var list<array{url: string, method: string, options: array}> */
    public static array $calls = [];

    public static int $nextStatus = 200;
    public static string $nextBody = '200';
    public static ?\Throwable $nextException = null;

    public static function reset(): void
    {
        self::$calls = [];
        self::$nextStatus = 200;
        self::$nextBody = '200';
        self::$nextException = null;
    }
}
