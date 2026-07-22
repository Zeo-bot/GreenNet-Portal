<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Controllers\AdminMikroTikDryRunController;
use GreenNet\Exceptions\GuardedWriteExecutionException;
use GreenNet\Services\RouterOS\GuardedRouterOSWriteGateway;
use GreenNet\Services\RouterOS\RouterOSGatewayBundleFactory;
use GreenNet\Services\RouterOS\RouterOSWriteRedactor;
use GreenNet\Services\RouterOS\WriteCommandPolicy;
use GreenNet\Services\WriteSafetyGuard;
use GreenNet\Tests\Support\FakeRouterOSClient;
use GreenNet\Tests\Support\FakeRouterOSReadGateway;
use GreenNet\Tests\Support\TempDatabase;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class AdminMikroTikDisableWriteTest extends TestCase
{
    private const NOW = 1_700_000_000;

    private TempDatabase $database;
    private string $backupDirectory;

    protected function setUp(): void
    {
        $_POST = [];
        $_SESSION = [];
        $this->database = new TempDatabase();
        $this->backupDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'greennet-disable-write-'
            . bin2hex(random_bytes(12));
        self::assertTrue(mkdir($this->backupDirectory, 0700, true));
        file_put_contents($this->backupDirectory . '/fresh.sqlite', 'synthetic-backup');
        touch($this->backupDirectory . '/fresh.sqlite', self::NOW);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_SESSION = [];
        foreach (glob($this->backupDirectory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->backupDirectory)) {
            rmdir($this->backupDirectory);
        }
        $this->database->cleanup();
    }

    #[DataProvider('stateCases')]
    public function testDisableAndEnableUseExactGuardedCommandAndPreserveResult(
        string $action,
        string $confirmation,
        string $beforeState,
        string $afterState,
        string $expectedDisabled
    ): void {
        $sequence = [];
        $read = new FakeRouterOSReadGateway(static function (string $command) use (&$sequence): void {
            $sequence[] = 'read:' . $command;
        });
        $read->queueResponse([$this->userRow($beforeState)]);
        $read->queueResponse([$this->userRow($afterState)]);
        $client = new FakeRouterOSClient(static function (string $command) use (&$sequence): void {
            $sequence[] = 'write:' . $command;
        });
        $client->queueResponse([['status' => 'synthetic-ok']]);
        $controller = $this->controller($read, $client);
        $this->prepareExecution($action, $confirmation);

        $this->invokeExecuteSetDisabled($controller);

        self::assertTrue($controller->redirected);
        self::assertSame([
            'read:/user-manager/user/print',
            'write:/user-manager/user/set',
            'read:/user-manager/user/print',
        ], $sequence);
        self::assertSame([[
            'command' => '/user-manager/user/set',
            'params' => ['numbers' => '*synthetic-user', 'disabled' => $expectedDisabled],
        ]], $client->calls);
        self::assertTrue($_SESSION['mikrotik_dry_run_result']['executed']);
        self::assertTrue($_SESSION['mikrotik_dry_run_result']['real_execution']);
        self::assertTrue($_SESSION['mikrotik_dry_run_result']['real_result']['success']);
        self::assertSame($expectedDisabled, $_SESSION['mikrotik_dry_run_result']['real_result']['params']['disabled']);
        self::assertSame(1, $this->auditCount());

        $audit = $this->latestAudit();
        self::assertSame(
            $expectedDisabled === 'yes' ? 'user_manager_disable_user' : 'user_manager_enable_user',
            $audit['action']
        );
        self::assertSame('user_manager_user', $audit['dataset']);
        self::assertSame('/user-manager/user/set', $audit['command']);
        self::assertSame(1, (int) $audit['executed']);
        self::assertSame(1, (int) $audit['success']);
    }

    public static function stateCases(): array
    {
        return [
            'disable' => ['um_disable_user', 'DISABLE', 'false', 'true', 'yes'],
            'enable' => ['um_enable_user', 'ENABLE', 'true', 'false', 'no'],
        ];
    }

    public function testPreviewPlanRemainsReadOnlyAndDoesNotCallWriter(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([$this->userRow('false')]);
        $client = new FakeRouterOSClient();
        $controller = $this->controller($read, $client);

        $method = (new ReflectionClass(AdminMikroTikDryRunController::class))
            ->getMethod('buildSetDisabledPlan');
        $plan = $method->invoke($controller, 'synthetic-user', true);

        self::assertFalse($plan['executed']);
        self::assertFalse($plan['real_execution']);
        self::assertTrue($plan['can_execute_later']);
        self::assertSame([], $client->calls);
        self::assertSame('/user-manager/user/print', $read->calls[0]['command']);

        $source = $this->methodSource('preview');
        self::assertStringContainsString('assertDryRunAllowed()', $source);
        self::assertStringContainsString('recordDryRun(', $source);
        self::assertStringNotContainsString('writeGateway()', $source);
    }

    #[DataProvider('previewStateCases')]
    public function testDisableAndEnablePreviewNeverPersistTheCompleteUserRowOrSecret(
        bool $desiredDisabled,
        string $currentDisabled
    ): void {
        $secret = 'synthetic-preview-value-' . bin2hex(random_bytes(8));
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([[
            '.id' => '*synthetic-user',
            'name' => 'synthetic-user',
            'disabled' => $currentDisabled,
            'password' => $secret,
            'comment' => 'repeated ' . $secret,
        ]]);
        $client = new FakeRouterOSClient();
        $controller = $this->controller($read, $client);
        $reflection = new ReflectionClass(AdminMikroTikDryRunController::class);
        $plan = $reflection->getMethod('buildSetDisabledPlan')
            ->invoke($controller, 'synthetic-user', $desiredDisabled);

        $_SESSION['mikrotik_dry_run_result'] = $plan;
        $routerResponse = $reflection->getMethod('auditResponseFromPlan')->invoke($controller, $plan);
        $guard = new WriteSafetyGuard(
            $this->database->connection(),
            static fn (): int => self::NOW,
            $this->backupDirectory
        );
        $guard->recordDryRun([
            'action' => $plan['action'],
            'dataset' => $plan['dataset'],
            'username' => $plan['username'],
            'command' => $plan['command'],
            'params' => $plan,
            'router_response' => $routerResponse,
        ]);

        self::assertArrayNotHasKey('matched_raw_row', $plan['backend_lookup']['user_manager']);
        self::assertSame([
            '.id' => '*synthetic-user',
            'name' => 'synthetic-user',
            'disabled' => $currentDisabled,
        ], $plan['backend_lookup']['user_manager']['matched_row']);
        self::assertSame([], $client->calls);
        self::assertStringNotContainsString($secret, serialize($_SESSION));
        self::assertStringNotContainsString($secret, json_encode($this->latestAudit(), JSON_UNESCAPED_SLASHES));
        self::assertStringNotContainsString($secret, (string) file_get_contents($this->database->path()));
    }

    public static function previewStateCases(): array
    {
        return [
            'disable preview' => [true, 'false'],
            'enable preview' => [false, 'true'],
        ];
    }

    public function testConfirmationMismatchAndMissingOrInvalidPlansMakeNoCalls(): void
    {
        foreach ([
            ['confirm' => 'ENABLE', 'plan' => $this->storedPlan('um_disable_user', true)],
            ['confirm' => 'DISABLE', 'plan' => null],
            ['confirm' => 'DISABLE', 'plan' => $this->storedPlan('um_disable_user', false)],
        ] as $case) {
            $read = new FakeRouterOSReadGateway();
            $client = new FakeRouterOSClient();
            $controller = $this->controller($read, $client);
            $_POST = [
                'action' => 'um_disable_user',
                'confirm_execute' => $case['confirm'],
            ];
            $_SESSION = [];
            if (is_array($case['plan'])) {
                $_SESSION['mikrotik_dry_run_result'] = $case['plan'];
            }

            try {
                $this->invokeExecuteSetDisabled($controller);
                self::fail('Expected validation failure.');
            } catch (RuntimeException) {
                self::assertSame([], $read->calls);
                self::assertSame([], $client->calls);
            }
        }
    }

    public function testGuardDenialMakesNoReadOrWriteClientCalls(): void
    {
        $read = new FakeRouterOSReadGateway();
        $client = new FakeRouterOSClient();
        $controller = $this->controller($read, $client, false);
        $this->prepareExecution('um_disable_user', 'DISABLE');

        try {
            $this->invokeExecuteSetDisabled($controller);
            self::fail('Expected guard denial.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Write Enabled is OFF', $exception->getMessage());
        }

        self::assertSame([], $read->calls);
        self::assertSame([], $client->calls);
        self::assertSame(0, $this->auditCount());
    }

    public function testSetFailureStopsBeforeVerificationAndAuditsOnce(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([$this->userRow('false')]);
        $client = new FakeRouterOSClient();
        $client->queueFailure(new RuntimeException('Synthetic set failure.'));
        $controller = $this->controller($read, $client);
        $this->prepareExecution('um_disable_user', 'DISABLE');

        $exception = $this->captureGuardedFailure($controller);

        self::assertFalse($exception->result()->partialFailure);
        self::assertCount(1, $read->calls);
        self::assertCount(1, $client->calls);
        self::assertSame(1, $this->auditCount());
    }

    public function testVerificationFailureAfterSetIsPartialAndStoresLegacyResultShape(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([$this->userRow('false')]);
        $read->queueResponse([$this->userRow('false')]);
        $client = new FakeRouterOSClient();
        $client->queueResponse([]);
        $controller = $this->controller($read, $client);
        $this->prepareExecution('um_disable_user', 'DISABLE');

        $exception = $this->captureGuardedFailure($controller);

        self::assertTrue($exception->result()->partialFailure);
        self::assertSame(1, $exception->result()->successfulCallCount);
        self::assertSame(1, $this->auditCount());
        self::assertTrue($_SESSION['mikrotik_dry_run_result']['real_execution']);
        self::assertFalse($_SESSION['mikrotik_dry_run_result']['real_result']['success']);
        self::assertFalse($_SESSION['mikrotik_dry_run_result']['real_result']['verified_disabled']);
    }

    public function testAuditFailureAfterRouterSuccessDoesNotReportRouterFailure(): void
    {
        $read = new FakeRouterOSReadGateway();
        $read->queueResponse([$this->userRow('false')]);
        $read->queueResponse([$this->userRow('true')]);
        $client = new FakeRouterOSClient();
        $client->queueResponse([]);
        $guard = $this->configuredGuard(new ControllerFailingAuditGuard(
            $this->database->connection(),
            static fn (): int => self::NOW,
            $this->backupDirectory
        ));
        $controller = new TestableAdminMikroTikDryRunController(
            $read,
            $this->gateway($client, $guard)
        );
        $this->prepareExecution('um_disable_user', 'DISABLE');

        $this->invokeExecuteSetDisabled($controller);

        self::assertTrue($controller->redirected);
        self::assertTrue($_SESSION['mikrotik_dry_run_result']['real_result']['success']);
        self::assertSame(0, $this->auditCount());
        self::assertCount(1, $client->calls);
    }

    public function testControllerArchitectureAndPortalOnlyBaselineRemainCompatible(): void
    {
        $reflection = new ReflectionClass(AdminMikroTikDryRunController::class);
        $source = file_get_contents((string) $reflection->getFileName());
        self::assertIsString($source);
        self::assertStringNotContainsString('new RouterOSApiClient', $source);
        self::assertStringNotContainsString('->comm(', $source);
        self::assertStringNotContainsString('assertRealWriteAllowed(', $source);
        self::assertStringNotContainsString('recordRealAttempt(', $source);
        self::assertStringContainsString('RouterOSReadGatewayInterface', $source);
        self::assertStringContainsString('GuardedRouterOSWriteGatewayInterface', $source);

        $baseline = $this->methodSource('executeCreateBaseline');
        self::assertStringContainsString('GreenNetUsageBaselineService', $baseline);
        self::assertStringNotContainsString('writeGateway()', $baseline);
        self::assertStringNotContainsString('RouterOSWriteCommand', $baseline);
    }

    public function testInjectedFakesAndControllerConstructionHaveNoSocketStateOrCalls(): void
    {
        $read = new FakeRouterOSReadGateway();
        $client = new FakeRouterOSClient();
        $controller = $this->controller($read, $client);

        self::assertInstanceOf(AdminMikroTikDryRunController::class, $controller);
        self::assertFalse((new ReflectionClass($read))->hasProperty('socket'));
        self::assertFalse((new ReflectionClass($client))->hasProperty('socket'));
        self::assertSame([], $read->calls);
        self::assertSame([], $client->calls);
    }

    public function testNoArgumentControllerConstructionIsLazyAndFactorySharesOneClient(): void
    {
        $controller = new AdminMikroTikDryRunController();
        $reflection = new ReflectionClass($controller);
        self::assertNull($reflection->getProperty('readGateway')->getValue($controller));
        self::assertNull($reflection->getProperty('writeGateway')->getValue($controller));

        $factory = new ReflectionClass(RouterOSGatewayBundleFactory::class);
        $source = file_get_contents((string) $factory->getFileName());
        self::assertIsString($source);
        self::assertSame(1, substr_count($source, 'new RouterOSApiClient('));
        self::assertStringContainsString('new RealRouterOSReadGateway($client)', $source);
        self::assertStringContainsString("new GuardedRouterOSWriteGateway(\n                \$client,", $source);
        self::assertStringNotContainsString('FakeRouterOS', $source);
    }

    private function controller(
        FakeRouterOSReadGateway $read,
        FakeRouterOSClient $client,
        bool $writeEnabled = true
    ): TestableAdminMikroTikDryRunController {
        $guard = $this->configuredGuard(new WriteSafetyGuard(
            $this->database->connection(),
            static fn (): int => self::NOW,
            $this->backupDirectory
        ), $writeEnabled);

        return new TestableAdminMikroTikDryRunController($read, $this->gateway($client, $guard));
    }

    private function gateway(
        FakeRouterOSClient $client,
        WriteSafetyGuard $guard
    ): GuardedRouterOSWriteGateway {
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

    private function prepareExecution(string $action, string $confirmation): void
    {
        $_POST = ['action' => $action, 'confirm_execute' => $confirmation];
        $_SESSION['mikrotik_dry_run_result'] = $this->storedPlan($action, true);
    }

    private function storedPlan(string $action, bool $canExecute): array
    {
        return [
            'username' => 'synthetic-user',
            'action' => $action,
            'can_execute_later' => $canExecute,
            'executed' => false,
            'audit_id' => 42,
            'user_manager_id' => '*synthetic-user',
        ];
    }

    private function userRow(string $disabled): array
    {
        return [
            '.id' => '*synthetic-user',
            'name' => 'synthetic-user',
            'disabled' => $disabled,
        ];
    }

    private function invokeExecuteSetDisabled(AdminMikroTikDryRunController $controller): void
    {
        $method = (new ReflectionClass(AdminMikroTikDryRunController::class))
            ->getMethod('executeSetDisabled');
        $method->invoke($controller, 'synthetic-user');
    }

    private function captureGuardedFailure(
        AdminMikroTikDryRunController $controller
    ): GuardedWriteExecutionException {
        try {
            $this->invokeExecuteSetDisabled($controller);
            self::fail('Expected guarded write failure.');
        } catch (GuardedWriteExecutionException $exception) {
            return $exception;
        }
    }

    private function auditCount(): int
    {
        return (int) $this->database->connection()
            ->query('SELECT COUNT(*) FROM api_audit_logs')
            ->fetchColumn();
    }

    private function latestAudit(): array
    {
        $row = $this->database->connection()
            ->query('SELECT * FROM api_audit_logs ORDER BY id DESC LIMIT 1')
            ->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    private function methodSource(string $methodName): string
    {
        $reflection = (new ReflectionClass(AdminMikroTikDryRunController::class))->getMethod($methodName);
        $lines = file((string) $reflection->getFileName());
        self::assertIsArray($lines);

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));
    }
}

final class TestableAdminMikroTikDryRunController extends AdminMikroTikDryRunController
{
    public bool $redirected = false;

    protected function redirectAfterSetDisabled(): void
    {
        $this->redirected = true;
    }
}

final class ControllerFailingAuditGuard extends WriteSafetyGuard
{
    public function recordRealAttempt(array $data): int
    {
        throw new RuntimeException('Synthetic audit failure.');
    }
}
