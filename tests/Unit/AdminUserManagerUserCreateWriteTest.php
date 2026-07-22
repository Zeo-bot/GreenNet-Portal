<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Controllers\AdminUserManagerUserCreateController;
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

final class AdminUserManagerUserCreateWriteTest extends TestCase
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
            . 'greennet-create-write-' . bin2hex(random_bytes(10));
        self::assertTrue(mkdir($this->backupDirectory, 0700, true));
        file_put_contents($this->backupDirectory . '/fresh.sqlite', 'synthetic-backup');
        touch($this->backupDirectory . '/fresh.sqlite', self::NOW);
        $this->seedPortalFixture();
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

    public function testPreviewBuildsSafePlanWithoutPasswordOrWrite(): void
    {
        $secret = 'synthetic-create-' . bin2hex(random_bytes(8));
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([]);
        $read->queueResponse([[
            '.id' => '*profile',
            'name' => 'synthetic-profile',
            'password' => $secret,
            'token' => $secret,
        ]]);
        $client = new FakeRouterOSClient();
        $controller = $this->controller($read, $client);

        $plan = $this->invoke($controller, 'buildCreatePlan', ['synthetic-user', 1]);
        $_SESSION['um_user_create_result'] = $plan;

        self::assertTrue($plan['can_execute_later']);
        self::assertFalse($plan['user_found']);
        self::assertTrue($plan['profile_found']);
        self::assertSame([
            '.id' => '*profile',
            'name' => 'synthetic-profile',
        ], $plan['router']['profile']['row']);
        self::assertSame('<hidden>', $plan['operations'][0]['params']['password']);
        self::assertSame([], $client->calls);
        self::assertStringNotContainsString($secret, serialize($_SESSION));
        self::assertStringNotContainsString('$_POST[\'password\']', $this->methodSource('preview'));
    }

    public function testSuccessfulExecutionUsesExactWriteOrderAndVerifiesRelation(): void
    {
        $secret = 'synthetic-create-' . bin2hex(random_bytes(8));
        $read = $this->successfulReadSequence();
        $client = new FakeRouterOSClient();
        $client->queueResponse([]);
        $client->queueResponse([]);
        $controller = $this->controller($read, $client);

        $result = $this->invoke($controller, 'executeCreate', [
            'synthetic-user', $secret, 1, 'synthetic-profile', $this->storedPlan(),
        ]);
        $_SESSION['um_user_create_result'] = ['real_result' => $result];

        self::assertTrue($result['ok']);
        self::assertTrue($result['verified']);
        self::assertSame('*user', $result['router_user_id']);
        self::assertSame('*relation', $result['router_user_profile_id']);
        self::assertSame([
            ['command' => '/user-manager/user/add', 'params' => [
                'name' => 'synthetic-user', 'password' => $secret,
            ]],
            ['command' => '/user-manager/user-profile/add', 'params' => [
                'user' => 'synthetic-user', 'profile' => 'synthetic-profile',
            ]],
        ], $client->calls);
        self::assertSame(1, $this->auditCount());
        self::assertStringNotContainsString($secret, serialize($result));
        self::assertStringNotContainsString($secret, serialize($_SESSION));
        self::assertStringNotContainsString($secret, json_encode($this->latestAudit(), JSON_UNESCAPED_SLASHES));
        self::assertStringNotContainsString($secret, (string) file_get_contents($this->database->path()));
    }

    public function testExistingUserAndMissingProfileAreRejectedBeforeWrite(): void
    {
        foreach ([
            [[['.id' => '*existing', 'name' => 'synthetic-user']], [['.id' => '*profile', 'name' => 'synthetic-profile']]],
            [[], []],
        ] as [$users, $profiles]) {
            $read = new FakeRouterOSReadGateway();
            $read->queueResponse($users);
            $read->queueResponse($profiles);
            $client = new FakeRouterOSClient();
            $controller = $this->controller($read, $client);

            try {
                $this->invoke($controller, 'executeCreate', [
                    'synthetic-user', 'synthetic-create-value', 1, 'synthetic-profile', $this->storedPlan(),
                ]);
                self::fail('Expected pre-write rejection.');
            } catch (GuardedWriteExecutionException $failure) {
                self::assertFalse($failure->result()->partialFailure);
            }
            self::assertSame([], $client->calls);
        }
    }

    public function testFirstCommandFailureIsNotPartial(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([]);
        $read->queueResponse([['.id' => '*profile', 'name' => 'synthetic-profile']]);
        $client = new FakeRouterOSClient();
        $client->queueFailure(new RuntimeException('Synthetic first command failure.'));
        $failure = $this->captureFailure($this->controller($read, $client));

        self::assertFalse($failure->result()->partialFailure);
        self::assertSame(0, $failure->result()->successfulCallCount);
        self::assertCount(1, $client->calls);
        self::assertSame(1, $this->auditCount());
    }

    public function testProfileAssignmentFailureAfterCreateIsPartial(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([]);
        $read->queueResponse([['.id' => '*profile', 'name' => 'synthetic-profile']]);
        $client = new FakeRouterOSClient();
        $client->queueResponse([]);
        $client->queueFailure(new RuntimeException('Synthetic relation failure.'));
        $failure = $this->captureFailure($this->controller($read, $client));

        self::assertTrue($failure->result()->partialFailure);
        self::assertSame(1, $failure->result()->successfulCallCount);
        self::assertCount(2, $client->calls);
        self::assertSame(1, $this->auditCount());
    }

    public function testPostWriteVerificationFailureIsPartial(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([]);
        $read->queueResponse([['.id' => '*profile', 'name' => 'synthetic-profile']]);
        $read->queueResponse([]);
        $read->queueResponse([['.id' => '*profile', 'name' => 'synthetic-profile']]);
        $read->queueResponse([]);
        $client = new FakeRouterOSClient();
        $client->queueResponse([]);
        $client->queueResponse([]);
        $failure = $this->captureFailure($this->controller($read, $client));

        self::assertTrue($failure->result()->partialFailure);
        self::assertSame(2, $failure->result()->successfulCallCount);
        self::assertSame(1, $this->auditCount());
    }

    public function testGuardDenialMakesNoRouterCallsOrAudit(): void
    {
        $read = new FakeRouterOSReadGateway();
        $client = new FakeRouterOSClient();
        $controller = $this->controller($read, $client, false);

        try {
            $this->invoke($controller, 'executeCreate', [
                'synthetic-user', 'synthetic-create-value', 1, 'synthetic-profile', $this->storedPlan(),
            ]);
            self::fail('Expected guard denial.');
        } catch (RuntimeException $failure) {
            self::assertStringContainsString('Write Enabled is OFF', $failure->getMessage());
        }
        self::assertSame([], $read->calls);
        self::assertSame([], $client->calls);
        self::assertSame(0, $this->auditCount());
    }

    public function testConfirmationPasswordReentryPlanChecksAndViewRemainCompatible(): void
    {
        $source = $this->methodSource('execute');
        $preview = $this->methodSource('preview');
        $view = file_get_contents(GRENNET_TEST_ROOT . '/src/app/Views/admin/user_manager_user_create.php');
        self::assertIsString($view);
        self::assertStringContainsString('$_POST[\'password\']', $source);
        self::assertStringContainsString("'CREATE'", $source);
        self::assertStringContainsString('validatePassword($password)', $source);
        self::assertStringNotContainsString('$_POST[\'password\']', $preview);
        self::assertSame(1, substr_count($view, 'name="password"'));
        self::assertStringContainsString('method="post" action="/admin/user-manager-user-create/execute"', $view);
        self::assertStringContainsString('type="password" name="password"', $view);
        self::assertStringNotContainsString('type="hidden" name="password"', $view);
        self::assertStringContainsString('name="confirm_create"', $view);

        $controller = new AdminUserManagerUserCreateController();
        $_SESSION = [];
        $this->expectException(RuntimeException::class);
        $this->invoke($controller, 'requireLastPlan', ['synthetic-user', 1]);
    }

    public function testArchitectureHasNoLegacyWriteAccessAndConstructionIsLazy(): void
    {
        $reflection = new ReflectionClass(AdminUserManagerUserCreateController::class);
        $source = file_get_contents((string) $reflection->getFileName());
        self::assertIsString($source);
        self::assertStringNotContainsString('new RouterOSApiClient', $source);
        self::assertStringNotContainsString('RouterOSClientInterface', $source);
        self::assertStringNotContainsString('->comm(', $source);
        self::assertStringNotContainsString('assertRealWriteAllowed(', $source);
        self::assertStringNotContainsString('recordRealAttempt(', $source);
        self::assertStringContainsString('RouterOSReadGatewayInterface', $source);
        self::assertStringContainsString('GuardedRouterOSWriteGatewayInterface', $source);

        $controller = new AdminUserManagerUserCreateController();
        self::assertNull($reflection->getProperty('readGateway')->getValue($controller));
        self::assertNull($reflection->getProperty('writeGateway')->getValue($controller));
        self::assertFalse((new ReflectionClass(new FakeRouterOSClient()))->hasProperty('socket'));
        self::assertFalse((new ReflectionClass(new FakeRouterOSReadGateway()))->hasProperty('socket'));
    }

    private function controller(
        FakeRouterOSReadGateway $read,
        FakeRouterOSClient $client,
        bool $writeEnabled = true
    ): AdminUserManagerUserCreateController {
        $guard = new WriteSafetyGuard(
            $this->database->connection(),
            static fn (): int => self::NOW,
            $this->backupDirectory
        );
        $guard->ensureTables();
        foreach ([
            'mikrotik_write_enabled' => $writeEnabled ? 'true' : 'false',
            'greennet_safe_mode' => 'false',
            'backup_guard_enabled' => 'true',
            'confirm_required' => 'true',
        ] as $key => $value) {
            $stmt = $this->database->connection()->prepare(
                'INSERT INTO greennet_write_safety_settings (setting_key, setting_value)
                 VALUES (:key, :value)
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value'
            );
            $stmt->execute(['key' => $key, 'value' => $value]);
        }
        $gateway = new GuardedRouterOSWriteGateway(
            $client,
            $guard,
            new WriteCommandPolicy(),
            new RouterOSWriteRedactor()
        );
        return new AdminUserManagerUserCreateController($read, $gateway, $this->database->connection());
    }

    private function successfulReadSequence(): FakeRouterOSReadGateway
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([]);
        $read->queueResponse([['.id' => '*profile', 'name' => 'synthetic-profile']]);
        $read->queueResponse([['.id' => '*user', 'name' => 'synthetic-user', 'password' => 'not-retained']]);
        $read->queueResponse([['.id' => '*profile', 'name' => 'synthetic-profile', 'token' => 'not-retained']]);
        $read->queueResponse([[
            '.id' => '*relation',
            'user' => 'synthetic-user',
            'profile' => 'synthetic-profile',
            'secret' => 'not-retained',
        ]]);
        return $read;
    }

    private function storedPlan(): array
    {
        return [
            'action' => 'create_user_manager_user',
            'username' => 'synthetic-user',
            'package_id' => 1,
            'router_profile_name' => 'synthetic-profile',
            'router' => ['profile' => ['id' => '*profile']],
            'local_needs_update' => false,
            'can_execute_later' => true,
            'executed' => false,
            'audit_id' => 42,
        ];
    }

    private function captureFailure(AdminUserManagerUserCreateController $controller): GuardedWriteExecutionException
    {
        try {
            $this->invoke($controller, 'executeCreate', [
                'synthetic-user', 'synthetic-create-value', 1, 'synthetic-profile', $this->storedPlan(),
            ]);
            self::fail('Expected guarded failure.');
        } catch (GuardedWriteExecutionException $failure) {
            return $failure;
        }
    }

    private function seedPortalFixture(): void
    {
        $pdo = $this->database->connection();
        $pdo->exec('CREATE TABLE customers_local (
            id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE, package_id INTEGER DEFAULT 0
        )');
        $pdo->exec('CREATE TABLE service_packages (
            id INTEGER PRIMARY KEY, name TEXT, source_profile TEXT, is_active INTEGER DEFAULT 1
        )');
        $pdo->exec("INSERT INTO customers_local (username, package_id) VALUES ('synthetic-user', 1)");
        $pdo->exec("INSERT INTO service_packages (id, name, source_profile) VALUES (1, 'Synthetic', 'synthetic-profile')");
    }

    private function auditCount(): int
    {
        return (int) $this->database->connection()->query('SELECT COUNT(*) FROM api_audit_logs')->fetchColumn();
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

    private function methodSource(string $name): string
    {
        $method = (new ReflectionClass(AdminUserManagerUserCreateController::class))->getMethod($name);
        $lines = file((string) $method->getFileName());
        self::assertIsArray($lines);
        return implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));
    }
}
