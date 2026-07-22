<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Controllers\AdminUserManagerPasswordController;
use GreenNet\Exceptions\GuardedWriteExecutionException;
use GreenNet\Services\RouterOS\GuardedRouterOSWriteGateway;
use GreenNet\Services\RouterOS\RouterOSWriteRedactor;
use GreenNet\Services\RouterOS\WriteCommandPolicy;
use GreenNet\Services\WriteSafetyGuard;
use GreenNet\Tests\Support\FakeRouterOSClient;
use GreenNet\Tests\Support\FakeRouterOSReadGateway;
use GreenNet\Tests\Support\TempDatabase;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class AdminUserManagerPasswordWriteTest extends TestCase
{
    private const NOW = 1_700_000_000;

    private TempDatabase $database;
    private string $backupDirectory;

    protected function setUp(): void
    {
        $_POST = [];
        $_SESSION = [];
        $this->database = new TempDatabase();
        $this->backupDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'greennet-password-write-' . bin2hex(random_bytes(12));
        self::assertTrue(mkdir($this->backupDirectory, 0700, true));
        file_put_contents($this->backupDirectory . '/fresh.sqlite', 'synthetic-backup');
        touch($this->backupDirectory . '/fresh.sqlite', self::NOW);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_SESSION = [];
        foreach (glob($this->backupDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->backupDirectory)) {
            rmdir($this->backupDirectory);
        }
        $this->database->cleanup();
    }

    public function testPreviewPlanUsesReadGatewayAndPersistsOnlySafeProjection(): void
    {
        $sentinel = 'synthetic-sentinel-' . bin2hex(random_bytes(8));
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([[
            '.id' => '*synthetic-user',
            'name' => 'synthetic-user',
            'disabled' => 'false',
            'password' => $sentinel,
            'secret' => $sentinel,
            'token' => $sentinel,
        ]]);
        $client = new FakeRouterOSClient();
        $controller = $this->controller($read, $client);

        $plan = $this->invoke($controller, 'buildPasswordPlan', ['synthetic-user']);
        $_SESSION['um_password_result'] = $plan;

        self::assertSame([[
            'command' => '/user-manager/user/print',
            'params' => ['?name' => 'synthetic-user'],
        ]], $read->calls);
        self::assertSame([], $client->calls);
        self::assertSame([
            '.id' => '*synthetic-user',
            'name' => 'synthetic-user',
            'disabled' => 'false',
        ], $plan['router']['user']['row']);
        self::assertSame('<hidden>', $plan['operations'][0]['params']['password']);
        self::assertStringNotContainsString($sentinel, serialize($_SESSION));
    }

    public function testGuardedExecutionIsExactReadSetReadAndKeepsPasswordInClientMemoryOnly(): void
    {
        $sentinel = 'synthetic-sentinel-' . bin2hex(random_bytes(8));
        $sequence = [];
        $read = new FakeRouterOSReadGateway(static function (string $command) use (&$sequence): void {
            $sequence[] = 'read:' . $command;
        });
        $read->queueResponse([$this->userRow()]);
        $read->queueResponse([$this->userRow()]);
        $client = new FakeRouterOSClient(static function (string $command) use (&$sequence): void {
            $sequence[] = 'write:' . $command;
        });
        $client->queueResponse([['status' => 'synthetic-ok']]);
        $controller = $this->controller($read, $client);

        $result = $this->invoke($controller, 'executePasswordChange', [
            'synthetic-user',
            $sentinel,
            $this->storedPlan(),
        ]);
        $_SESSION['um_password_result'] = ['real_result' => $result];

        self::assertSame([
            'read:/user-manager/user/print',
            'write:/user-manager/user/set',
            'read:/user-manager/user/print',
        ], $sequence);
        self::assertSame('/user-manager/user/set', $client->calls[0]['command']);
        self::assertSame('*synthetic-user', $client->calls[0]['params']['numbers']);
        self::assertSame($sentinel, $client->calls[0]['params']['password']);
        self::assertStringNotContainsString($sentinel, serialize($result));
        self::assertStringNotContainsString($sentinel, serialize($_SESSION));
        self::assertStringNotContainsString($sentinel, json_encode($this->latestAudit(), JSON_UNESCAPED_SLASHES));
        self::assertStringNotContainsString($sentinel, (string) file_get_contents($this->database->path()));
        self::assertSame(1, $this->auditCount());
        self::assertSame(0, $this->queueCount());
    }

    public function testGuardDenialMakesNoReadWriteOrAuditCall(): void
    {
        $read = new FakeRouterOSReadGateway();
        $client = new FakeRouterOSClient();
        $controller = $this->controller($read, $client, false);

        try {
            $this->invoke($controller, 'executePasswordChange', [
                'synthetic-user',
                'synthetic-sentinel-denied',
                $this->storedPlan(),
            ]);
            self::fail('Expected guard denial.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('Write Enabled is OFF', $error->getMessage());
        }

        self::assertSame([], $read->calls);
        self::assertSame([], $client->calls);
        self::assertSame(0, $this->auditCount());
    }

    public function testTargetMismatchFailsBeforeWriteAndAuditsOnce(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([['.id' => '*different', 'name' => 'synthetic-user', 'disabled' => 'false']]);
        $client = new FakeRouterOSClient();
        $controller = $this->controller($read, $client);

        $failure = $this->captureFailure($controller, 'synthetic-sentinel-mismatch');

        self::assertFalse($failure->result()->partialFailure);
        self::assertCount(1, $read->calls);
        self::assertSame([], $client->calls);
        self::assertSame(1, $this->auditCount());
    }

    public function testCommandFailureStopsBeforeSecondReadAndAuditsOnce(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([$this->userRow()]);
        $client = new FakeRouterOSClient();
        $client->queueFailure(new RuntimeException('Synthetic command failure.'));
        $controller = $this->controller($read, $client);

        $failure = $this->captureFailure($controller, 'synthetic-sentinel-failure');

        self::assertFalse($failure->result()->partialFailure);
        self::assertCount(1, $read->calls);
        self::assertCount(1, $client->calls);
        self::assertSame(1, $this->auditCount());
    }

    public function testPostWriteContinuityFailureIsPartialFailure(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([$this->userRow()]);
        $read->queueResponse([['.id' => '*different', 'name' => 'synthetic-user', 'disabled' => 'false']]);
        $client = new FakeRouterOSClient();
        $client->queueResponse([]);
        $controller = $this->controller($read, $client);

        $failure = $this->captureFailure($controller, 'synthetic-sentinel-partial');

        self::assertTrue($failure->result()->partialFailure);
        self::assertSame(1, $failure->result()->successfulCallCount);
        self::assertCount(2, $read->calls);
        self::assertSame(1, $this->auditCount());
    }

    public function testAuditFailureAfterRouterSuccessRemainsOperationalSuccess(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([$this->userRow()]);
        $read->queueResponse([$this->userRow()]);
        $client = new FakeRouterOSClient();
        $client->queueResponse([]);
        $guard = $this->configuredGuard(new PasswordFailingAuditGuard(
            $this->database->connection(),
            static fn (): int => self::NOW,
            $this->backupDirectory
        ));
        $controller = new AdminUserManagerPasswordController(
            $read,
            $this->gateway($client, $guard),
            $this->database->connection()
        );

        $result = $this->invoke($controller, 'executePasswordChange', [
            'synthetic-user',
            'synthetic-sentinel-audit',
            $this->storedPlan(),
        ]);

        self::assertTrue($result['ok']);
        self::assertFalse($result['audit_recorded']);
        self::assertSame('RouterOS operation audit could not be recorded.', $result['audit_warning']);
        self::assertSame(0, $this->auditCount());
    }

    public function testLifecycleValidationViewAndArchitectureRemainSafeAndCompatible(): void
    {
        $reflection = new ReflectionClass(AdminUserManagerPasswordController::class);
        $source = file_get_contents((string) $reflection->getFileName());
        $view = file_get_contents(GRENNET_TEST_ROOT . '/src/app/Views/admin/user_manager_password.php');
        self::assertIsString($source);
        self::assertIsString($view);

        self::assertStringNotContainsString('new RouterOSApiClient', $source);
        self::assertStringNotContainsString('RouterOSClientInterface', $source);
        self::assertStringNotContainsString('->comm(', $source);
        self::assertStringNotContainsString('assertRealWriteAllowed(', $source);
        self::assertStringNotContainsString('recordRealAttempt(', $source);
        self::assertStringContainsString("\$_POST['password']", $this->methodSource('execute'));
        self::assertStringContainsString("'PASSWORD'", $this->methodSource('execute'));
        self::assertStringNotContainsString("\$_POST['password']", $this->methodSource('preview'));
        self::assertSame(1, substr_count($view, 'name="password"'));
        self::assertStringNotContainsString('type="hidden" name="password"', $view);
        self::assertStringNotContainsString('value="<?= gn_um_pass_h((string) ($result[\'password\']', $view);

        $controller = new AdminUserManagerPasswordController();
        self::assertNull($reflection->getProperty('readGateway')->getValue($controller));
        self::assertNull($reflection->getProperty('writeGateway')->getValue($controller));
        self::assertFalse((new ReflectionClass(new FakeRouterOSClient()))->hasProperty('socket'));
        self::assertFalse((new ReflectionClass(new FakeRouterOSReadGateway()))->hasProperty('socket'));
    }

    public function testPasswordRulesAndStoredPlanChecksArePreserved(): void
    {
        $controller = new AdminUserManagerPasswordController(
            new FakeRouterOSReadGateway(),
            $this->gateway(new FakeRouterOSClient(), $this->guard()),
            $this->database->connection()
        );

        foreach (['ab', str_repeat('x', 129), "valid\ninvalid"] as $invalid) {
            try {
                $this->invoke($controller, 'validatePassword', [$invalid]);
                self::fail('Expected password validation failure.');
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }

        $this->invoke($controller, 'validatePassword', ['synthetic-valid-value']);
        $_SESSION = [];
        $this->expectException(RuntimeException::class);
        $this->invoke($controller, 'requireLastPlan', ['synthetic-user']);
    }

    public function testMismatchedStoredPlanIsRejected(): void
    {
        $controller = new AdminUserManagerPasswordController();
        $_SESSION['um_password_result'] = array_merge($this->storedPlan(), [
            'username' => 'different-synthetic-user',
        ]);

        $this->expectException(RuntimeException::class);
        $this->invoke($controller, 'requireLastPlan', ['synthetic-user']);
    }

    private function controller(
        FakeRouterOSReadGateway $read,
        FakeRouterOSClient $client,
        bool $writeEnabled = true
    ): AdminUserManagerPasswordController {
        return new AdminUserManagerPasswordController(
            $read,
            $this->gateway($client, $this->guard($writeEnabled)),
            $this->database->connection()
        );
    }

    private function guard(bool $writeEnabled = true): WriteSafetyGuard
    {
        return $this->configuredGuard(new WriteSafetyGuard(
            $this->database->connection(),
            static fn (): int => self::NOW,
            $this->backupDirectory
        ), $writeEnabled);
    }

    private function gateway(FakeRouterOSClient $client, WriteSafetyGuard $guard): GuardedRouterOSWriteGateway
    {
        return new GuardedRouterOSWriteGateway(
            $client,
            $guard,
            new WriteCommandPolicy(),
            new RouterOSWriteRedactor()
        );
    }

    private function configuredGuard(WriteSafetyGuard $guard, bool $writeEnabled = true): WriteSafetyGuard
    {
        $guard->ensureTables();
        foreach ([
            'mikrotik_write_enabled' => $writeEnabled ? 'true' : 'false',
            'greennet_safe_mode' => 'false',
            'backup_guard_enabled' => 'true',
            'confirm_required' => 'true',
        ] as $key => $value) {
            $statement = $this->database->connection()->prepare("
                INSERT INTO greennet_write_safety_settings (setting_key, setting_value)
                VALUES (:key, :value)
                ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value
            ");
            $statement->execute(['key' => $key, 'value' => $value]);
        }

        return $guard;
    }

    private function captureFailure(
        AdminUserManagerPasswordController $controller,
        string $password
    ): GuardedWriteExecutionException {
        try {
            $this->invoke($controller, 'executePasswordChange', [
                'synthetic-user',
                $password,
                $this->storedPlan(),
            ]);
            self::fail('Expected guarded execution failure.');
        } catch (GuardedWriteExecutionException $failure) {
            self::assertStringNotContainsString($password, $failure->getMessage());

            return $failure;
        }
    }

    private function storedPlan(): array
    {
        return [
            'action' => 'change_user_manager_password',
            'username' => 'synthetic-user',
            'router_user_id' => '*synthetic-user',
            'can_execute_later' => true,
            'executed' => false,
            'audit_id' => 42,
        ];
    }

    private function userRow(): array
    {
        return ['.id' => '*synthetic-user', 'name' => 'synthetic-user', 'disabled' => 'false'];
    }

    private function auditCount(): int
    {
        return (int) $this->database->connection()->query('SELECT COUNT(*) FROM api_audit_logs')->fetchColumn();
    }

    private function queueCount(): int
    {
        return (int) $this->database->connection()
            ->query('SELECT COUNT(*) FROM mikrotik_transaction_queue')
            ->fetchColumn();
    }

    private function latestAudit(): array
    {
        $row = $this->database->connection()
            ->query('SELECT * FROM api_audit_logs ORDER BY id DESC LIMIT 1')
            ->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    private function invoke(object $target, string $method, array $arguments): mixed
    {
        return (new ReflectionClass($target))->getMethod($method)->invokeArgs($target, $arguments);
    }

    private function methodSource(string $methodName): string
    {
        $method = (new ReflectionClass(AdminUserManagerPasswordController::class))->getMethod($methodName);
        $lines = file((string) $method->getFileName());
        self::assertIsArray($lines);

        return implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));
    }
}

final class PasswordFailingAuditGuard extends WriteSafetyGuard
{
    public function recordRealAttempt(array $data): int
    {
        throw new RuntimeException('Synthetic audit failure.');
    }
}
