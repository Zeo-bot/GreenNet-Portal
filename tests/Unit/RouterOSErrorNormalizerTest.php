<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Services\RouterOS\RouterOSErrorNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RouterOSErrorNormalizerTest extends TestCase
{
    #[DataProvider('errors')]
    public function testOperationalFailuresHaveStableCodes(string $message, string $code): void
    {
        $result = (new RouterOSErrorNormalizer())->normalize(new RuntimeException($message));

        self::assertSame($code, $result['code']);
        self::assertNotSame('', $result['message']);
        self::assertSame($message, $result['detail']);
    }

    public static function errors(): iterable
    {
        yield ['MikroTik API login failed: rejected', 'AUTHENTICATION_FAILED'];
        yield ['RouterOS API timeout after 6 seconds', 'CONNECTION_TIMEOUT'];
        yield ['RouterOS read command is not allowed.', 'UNSUPPORTED_COMMAND'];
        yield ['duplicate username', 'DUPLICATE_RECORD'];
        yield ['profile not found', 'PROFILE_NOT_FOUND'];
        yield ['limitation not found', 'LIMITATION_NOT_FOUND'];
        yield ['active session not found', 'ACTIVE_SESSION_NOT_FOUND'];
        yield ['backend not configured', 'BACKEND_NOT_CONFIGURED'];
        yield ['record not found', 'RECORD_NOT_FOUND'];
        yield ['partial operation', 'PARTIAL_OPERATION'];
        yield ['cleanup failed', 'CLEANUP_FAILURE'];
        yield ['reconciliation required', 'RECONCILIATION_REQUIRED'];
        yield ['MikroTik trap: invalid value', 'ROUTEROS_VALIDATION_ERROR'];
        yield ['Router unreachable or API port closed', 'ROUTER_UNAVAILABLE'];
    }
}
