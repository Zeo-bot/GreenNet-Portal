<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Controllers\AdminUserManagerUserDeleteController;
use GreenNet\Exceptions\GuardedWriteExecutionException;
use GreenNet\Services\RouterOS\GuardedRouterOSWriteGateway;
use GreenNet\Services\RouterOS\RouterOSWriteRedactor;
use GreenNet\Services\RouterOS\WriteCommandPolicy;
use GreenNet\Services\WriteSafetyGuard;
use GreenNet\Tests\Support\FakeRouterOSClient;
use GreenNet\Tests\Support\FakeRouterOSReadGateway;
use GreenNet\Tests\Support\TempDatabase;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class AdminUserManagerUserDeleteWriteTest extends TestCase
{
    private const NOW = 1_700_000_000;
    private TempDatabase $database;
    private string $backupDirectory;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->database = new TempDatabase();
        $this->backupDirectory = sys_get_temp_dir() . '/greennet-delete-' . bin2hex(random_bytes(8));
        mkdir($this->backupDirectory, 0700, true);
        file_put_contents($this->backupDirectory . '/fresh.sqlite', 'fixture');
        touch($this->backupDirectory . '/fresh.sqlite', self::NOW);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->backupDirectory . '/*') ?: [] as $file) unlink($file);
        if (is_dir($this->backupDirectory)) rmdir($this->backupDirectory);
        $this->database->cleanup();
    }

    public function testMultipleSessionsRelationsAndUserUseExactOrderAndOneAudit(): void
    {
        $read = $this->reads(true);
        $client = new FakeRouterOSClient();
        foreach (range(1, 4) as $_) $client->queueResponse([]);
        $controller = $this->controller($read, $client);
        $result = $this->invoke($controller, 'executeDelete', ['synthetic-user', $this->plan()]);

        self::assertTrue($result['ok']);
        self::assertTrue($result['verified_absent']);
        self::assertSame([
            ['/user-manager/session/remove', '*s1'],
            ['/user-manager/session/remove', '*s2'],
            ['/user-manager/user-profile/remove', '*r1'],
            ['/user-manager/user/remove', '*u1'],
        ], array_map(static fn (array $call): array => [$call['command'], $call['params']['numbers']], $client->calls));
        self::assertSame(1, $this->auditCount());
    }

    public function testNoSessionNoRelationDeletesExactUserOnly(): void
    {
        $read = $this->reads(false);
        $client = new FakeRouterOSClient();
        $client->queueResponse([]);
        $controller = $this->controller($read, $client);
        $plan = $this->plan();
        $plan['operations'] = [end($plan['operations'])];
        $result = $this->invoke($controller, 'executeDelete', ['synthetic-user', $plan]);
        self::assertTrue($result['ok']);
        self::assertSame('/user-manager/user/remove', $client->calls[0]['command']);
        self::assertSame('*u1', $client->calls[0]['params']['numbers']);
    }

    public function testCurrentUserIdMismatchRejectsBeforeWrite(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([['.id' => '*different', 'name' => 'synthetic-user']]);
        $read->queueResponse([]);
        $read->queueResponse([]);
        $read->queueResponse([]);
        $client = new FakeRouterOSClient();
        $failure = $this->failure($this->controller($read, $client), $this->plan());
        self::assertFalse($failure->result()->partialFailure);
        self::assertSame([], $client->calls);
    }

    public function testSessionRelationAndUserFailuresReportCorrectPartialCounts(): void
    {
        foreach ([0, 2, 3] as $failureIndex) {
            $read = $this->reads(true, false);
            $client = new FakeRouterOSClient();
            for ($i = 0; $i <= $failureIndex; $i++) {
                $i === $failureIndex
                    ? $client->queueFailure(new RuntimeException('Synthetic removal failure.'))
                    : $client->queueResponse([]);
            }
            $failure = $this->failure($this->controller($read, $client), $this->plan());
            self::assertSame($failureIndex > 0, $failure->result()->partialFailure);
            self::assertSame($failureIndex, $failure->result()->successfulCallCount);
            self::assertSame(1, $this->auditCount());
            $this->database->cleanup();
            $this->database = new TempDatabase();
        }
    }

    public function testPostDeleteVerificationFailureIsPartial(): void
    {
        $read = $this->reads(true, true);
        $client = new FakeRouterOSClient();
        foreach (range(1, 4) as $_) $client->queueResponse([]);
        $failure = $this->failure($this->controller($read, $client), $this->plan());
        self::assertTrue($failure->result()->partialFailure);
        self::assertSame(4, $failure->result()->successfulCallCount);
    }

    public function testPreviewPlanUsesSafeProjectionsAndNoWrites(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([['.id' => '*u1', 'name' => 'synthetic-user', 'password' => 'not-retained']]);
        $read->queueResponse([['.id' => '*r1', 'user' => 'synthetic-user', 'profile' => 'p', 'secret' => 'not-retained']]);
        $read->queueResponse([['.id' => '*s1', 'user' => 'synthetic-user', 'token' => 'not-retained']]);
        $client = new FakeRouterOSClient();
        $controller = $this->controller($read, $client);
        $plan = $this->invoke($controller, 'buildDeletePlan', ['synthetic-user']);
        self::assertSame(['.id' => '*u1', 'name' => 'synthetic-user', 'disabled' => ''], $plan['router']['user']['row']);
        self::assertArrayNotHasKey('raw_rows', $plan['router']['sessions']);
        self::assertArrayNotHasKey('raw_rows', $plan['router']['user_profiles']);
        self::assertStringNotContainsString('not-retained', serialize($plan));
        self::assertSame([], $client->calls);
    }

    public function testConfirmationPlanAndArchitectureRemainCompatibleAndLazy(): void
    {
        $reflection = new ReflectionClass(AdminUserManagerUserDeleteController::class);
        $source = file_get_contents((string) $reflection->getFileName());
        self::assertIsString($source);
        self::assertStringContainsString("'DELETE'", $this->methodSource('execute'));
        self::assertStringNotContainsString('new RouterOSApiClient', $source);
        self::assertStringNotContainsString('RouterOSClientInterface', $source);
        self::assertStringNotContainsString('->comm(', $source);
        self::assertStringNotContainsString('assertRealWriteAllowed(', $source);
        self::assertStringNotContainsString('recordRealAttempt(', $source);
        $controller = new AdminUserManagerUserDeleteController();
        self::assertNull($reflection->getProperty('readGateway')->getValue($controller));
        self::assertNull($reflection->getProperty('writeGateway')->getValue($controller));
        $_SESSION = [];
        $this->expectException(RuntimeException::class);
        $this->invoke($controller, 'requireLastPlan', ['synthetic-user']);
    }

    private function reads(bool $children, bool $verificationFails = false): FakeRouterOSReadGateway
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([['.id' => '*u1', 'name' => 'synthetic-user']]);
        $read->queueResponse($children ? [['.id' => '*r1', 'user' => 'synthetic-user', 'profile' => 'p']] : []);
        $read->queueResponse($children ? [
            ['.id' => '*s1', 'user' => 'synthetic-user'], ['.id' => '*s2', 'user' => 'synthetic-user'],
        ] : []);
        if (!$children) $read->queueResponse([]);
        $read->queueResponse($verificationFails ? [['.id' => '*u1', 'name' => 'synthetic-user']] : []);
        $read->queueResponse([]);
        $read->queueResponse([]);
        $read->queueResponse([]);
        return $read;
    }

    private function plan(): array
    {
        return [
            'router_user_id' => '*u1', 'audit_id' => 10,
            'operations' => [
                ['command' => '/user-manager/session/remove', 'params' => ['numbers' => '*s1']],
                ['command' => '/user-manager/session/remove', 'params' => ['numbers' => '*s2']],
                ['command' => '/user-manager/user-profile/remove', 'params' => ['numbers' => '*r1']],
                ['command' => '/user-manager/user/remove', 'params' => ['numbers' => '*u1']],
            ],
        ];
    }

    private function controller(FakeRouterOSReadGateway $read, FakeRouterOSClient $client): AdminUserManagerUserDeleteController
    {
        $guard = new WriteSafetyGuard($this->database->connection(), static fn (): int => self::NOW, $this->backupDirectory);
        $guard->ensureTables();
        foreach (['mikrotik_write_enabled' => 'true', 'greennet_safe_mode' => 'false', 'backup_guard_enabled' => 'true', 'confirm_required' => 'true'] as $key => $value) {
            $stmt = $this->database->connection()->prepare('INSERT INTO greennet_write_safety_settings (setting_key, setting_value) VALUES (:k,:v) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value');
            $stmt->execute(['k' => $key, 'v' => $value]);
        }
        $gateway = new GuardedRouterOSWriteGateway($client, $guard, new WriteCommandPolicy(), new RouterOSWriteRedactor());
        return new AdminUserManagerUserDeleteController($read, $gateway, $this->database->connection());
    }

    private function failure(AdminUserManagerUserDeleteController $controller, array $plan): GuardedWriteExecutionException
    {
        try {
            $this->invoke($controller, 'executeDelete', ['synthetic-user', $plan]);
            self::fail('Expected guarded failure.');
        } catch (GuardedWriteExecutionException $failure) {
            return $failure;
        }
    }

    private function auditCount(): int
    {
        return (int) $this->database->connection()->query('SELECT COUNT(*) FROM api_audit_logs')->fetchColumn();
    }

    private function invoke(object $target, string $method, array $args): mixed
    {
        return (new ReflectionClass($target))->getMethod($method)->invokeArgs($target, $args);
    }

    private function methodSource(string $name): string
    {
        $method = (new ReflectionClass(AdminUserManagerUserDeleteController::class))->getMethod($name);
        $lines = file((string) $method->getFileName());
        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }
}
