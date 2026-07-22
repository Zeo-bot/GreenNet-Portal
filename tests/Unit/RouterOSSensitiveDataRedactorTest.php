<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Services\RouterOS\RouterOSSensitiveDataRedactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouterOSSensitiveDataRedactorTest extends TestCase
{
    #[DataProvider('sensitiveKeyProvider')]
    public function testNormalizedSensitiveKeysAreRedacted(string $key): void
    {
        $redactor = new RouterOSSensitiveDataRedactor();
        $value = 'synthetic-value-' . bin2hex(random_bytes(8));

        self::assertSame([$key => '<hidden>'], $redactor->redact([$key => $value]));
    }

    public static function sensitiveKeyProvider(): array
    {
        return [
            'password' => ['password'],
            'passwd case' => ['PassWd'],
            'pass' => ['pass'],
            'secret' => ['client_secret'],
            'token' => ['access-token'],
            'authorization' => ['Authorization'],
            'api key spaces' => ['API Key'],
            'api key underscore' => ['api_key'],
            'private key' => ['private-key'],
        ];
    }

    public function testNestedDiscoveryRedactsRepeatedValuesAndPreservesSafeFields(): void
    {
        $redactor = new RouterOSSensitiveDataRedactor();
        $secret = 'synthetic-value-' . bin2hex(random_bytes(8));
        $input = [
            '.id' => '*synthetic',
            'name' => 'synthetic-user',
            'disabled' => 'false',
            'nested' => [
                'password' => $secret,
                'message' => 'response repeated ' . $secret,
            ],
        ];

        $redacted = $redactor->redact($input);

        self::assertSame('*synthetic', $redacted['.id']);
        self::assertSame('synthetic-user', $redacted['name']);
        self::assertSame('false', $redacted['disabled']);
        self::assertSame('<hidden>', $redacted['nested']['password']);
        self::assertSame('response repeated <hidden>', $redacted['nested']['message']);
        self::assertSame($redacted, $redactor->redact($redacted));
        self::assertStringNotContainsString($secret, $redactor->json($input));
    }
}
