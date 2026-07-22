<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Contracts\AuthorizedRouterOSWriterInterface;
use GreenNet\DTO\RouterOS\RouterOSWriteCommand;
use GreenNet\DTO\RouterOS\WriteExecutionRequest;
use GreenNet\Exceptions\GuardedWriteExecutionException;
use GreenNet\Services\RouterOS\GuardedRouterOSWriteGateway;
use GreenNet\Services\RouterOS\RouterOSWriteRedactor;
use GreenNet\Services\RouterOS\WriteCommandPolicy;
use GreenNet\Services\WriteSafetyGuard;
use GreenNet\Tests\Support\FakeRouterOSClient;
use GreenNet\Tests\Support\TempDatabase;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

final class GuardedRouterOSWriteGatewayTest extends TestCase
{
    private const NOW = 1_700_000_000;

    private TempDatabase $database;
    private string $backupDirectory;

    protected function setUp(): void
    {
        $this->database = new TempDatabase();
        $this->backupDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'greennet-write-boundary-'
            . bin2hex(random_bytes(12));

        self::assertTrue(mkdir($this->backupDirectory, 0700, true));
    }

    protected function tearDown(): void
    {
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

    public function testWriteDisabledDeniesBeforeClientAndWithoutAudit(): void
    {
        [$gateway, $client, $guard] = $this->gateway();
        $this->setSetting('mikrotik_write_enabled', 'false');

        try {
            $gateway->execute($this->request(), static fn (): null => null);
            self::fail('Expected write-disabled denial.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Write Enabled is OFF', $exception->getMessage());
        }

        self::assertSame([], $client->calls);
        self::assertSame(0, $this->auditCount());
        self::assertFalse($guard->realWriteAllowed());
    }

    public function testSafeModeDeniesBeforeClient(): void
    {
        [$gateway, $client] = $this->gateway();
        $this->setSetting('greennet_safe_mode', 'true');

        $this->expectGuardDenial($gateway, 'Safe Mode is ON');
        self::assertSame([], $client->calls);
    }

    public function testMissingConfirmationDeniesBeforeClient(): void
    {
        [$gateway, $client] = $this->gateway();

        $this->expectGuardDenial($gateway, 'requires explicit confirmation', $this->request(false));
        self::assertSame([], $client->calls);
    }

    public function testMissingAndOldBackupsDenyBeforeClient(): void
    {
        [$gateway, $client] = $this->gateway(false);
        $this->expectGuardDenial($gateway, 'no fresh backup');
        self::assertSame([], $client->calls);

        $backup = $this->backupDirectory . '/old.sqlite';
        file_put_contents($backup, 'synthetic-backup');
        touch($backup, self::NOW - 86_401);

        $this->expectGuardDenial($gateway, 'no fresh backup');
        self::assertSame([], $client->calls);
    }

    public function testFreshBackupAllowsSingleCommandAndRecordsOneAudit(): void
    {
        [$gateway, $client] = $this->gateway();
        $client->queueResponse([['status' => 'synthetic-ok']]);

        $result = $gateway->execute(
            $this->request(),
            static fn (AuthorizedRouterOSWriterInterface $writer): array => $writer->execute(
                new RouterOSWriteCommand('/user-manager/user/set', [
                    'numbers' => '*1',
                    'disabled' => 'yes',
                ])
            )
        );

        self::assertTrue($result->ok);
        self::assertSame(1, $result->attemptedCallCount);
        self::assertSame(1, $result->successfulCallCount);
        self::assertFalse($result->partialFailure);
        self::assertTrue($result->auditRecorded);
        self::assertGreaterThan(0, $result->auditId);
        self::assertSame(1, $this->auditCount());
    }

    public function testConstructionAndFakeClientDoNotOpenSocket(): void
    {
        [$gateway, $client] = $this->gateway();

        self::assertInstanceOf(GuardedRouterOSWriteGateway::class, $gateway);
        self::assertFalse((new ReflectionClass($client))->hasProperty('socket'));
        self::assertSame([], $client->calls);
    }

    public function testPolicyRejectionOccursBeforeClientAndIsAuditedOnce(): void
    {
        [$gateway, $client] = $this->gateway();

        $exception = $this->captureExecutionFailure($gateway, static function (AuthorizedRouterOSWriterInterface $writer): void {
            $writer->execute(new RouterOSWriteCommand('/user-manager/user/print'));
        });

        self::assertSame([], $client->calls);
        self::assertSame(0, $exception->result()->attemptedCallCount);
        self::assertSame(1, $this->auditCount());
    }

    public function testSequencingUsesFirstResponseToBuildSecondCommand(): void
    {
        [$gateway, $client] = $this->gateway();
        $client->queueResponse([['.id' => '*created']]);
        $client->queueResponse([]);

        $result = $gateway->execute($this->request(), static function (AuthorizedRouterOSWriterInterface $writer): string {
            $created = $writer->execute(new RouterOSWriteCommand('/user-manager/user/add', [
                'name' => 'synthetic-user',
                'password' => 'synthetic-password',
            ]));
            $writer->execute(new RouterOSWriteCommand('/user-manager/user/remove', [
                'numbers' => (string) ($created[0]['.id'] ?? ''),
            ]));

            return 'done';
        });

        self::assertTrue($result->ok);
        self::assertSame('done', $result->value);
        self::assertSame(2, $result->successfulCallCount);
        self::assertSame('*created', $client->calls[1]['params']['numbers']);
        self::assertSame([
            '/user-manager/user/add',
            '/user-manager/user/remove',
        ], array_column($client->calls, 'command'));
    }

    public function testZeroCommandOperationSucceedsAndAuditsWithoutExecution(): void
    {
        [$gateway, $client] = $this->gateway();

        $result = $gateway->execute($this->request(), static fn (): string => 'no-op');

        self::assertTrue($result->ok);
        self::assertSame('no-op', $result->value);
        self::assertSame(0, $result->attemptedCallCount);
        self::assertSame(0, $result->successfulCallCount);
        self::assertSame([], $client->calls);
        self::assertSame(1, $this->auditCount());
        self::assertSame(0, (int) $this->latestAudit()['executed']);
    }

    public function testFirstCommandFailureIsNotPartialAndStopsOperation(): void
    {
        [$gateway, $client] = $this->gateway();
        $client->queueFailure(new RuntimeException('Synthetic failure at lab-router.internal:8728.'));

        $exception = $this->captureExecutionFailure($gateway, static function (AuthorizedRouterOSWriterInterface $writer): void {
            $writer->execute(new RouterOSWriteCommand('/user-manager/user/remove', ['numbers' => '*1']));
            $writer->execute(new RouterOSWriteCommand('/user-manager/user/remove', ['numbers' => '*2']));
        });

        self::assertFalse($exception->result()->partialFailure);
        self::assertSame(1, $exception->result()->attemptedCallCount);
        self::assertSame(0, $exception->result()->successfulCallCount);
        self::assertCount(1, $client->calls);
        self::assertStringNotContainsString('lab-router.internal', $exception->getMessage());
        self::assertStringNotContainsString(':8728', $exception->getMessage());
        self::assertSame(1, $this->auditCount());
    }

    public function testSecondCommandFailureIsPartialAndStopsLaterCommands(): void
    {
        [$gateway, $client] = $this->gateway();
        $client->queueResponse([]);
        $client->queueFailure(new RuntimeException('Synthetic second failure.'));

        $exception = $this->captureExecutionFailure($gateway, static function (AuthorizedRouterOSWriterInterface $writer): void {
            $writer->execute(new RouterOSWriteCommand('/user-manager/session/remove', ['numbers' => '*1']));
            $writer->execute(new RouterOSWriteCommand('/user-manager/user-profile/remove', ['numbers' => '*2']));
            $writer->execute(new RouterOSWriteCommand('/user-manager/user/remove', ['numbers' => '*3']));
        });

        self::assertTrue($exception->result()->partialFailure);
        self::assertSame(2, $exception->result()->attemptedCallCount);
        self::assertSame(1, $exception->result()->successfulCallCount);
        self::assertCount(2, $client->calls);
        self::assertSame(1, $this->auditCount());
        self::assertStringContainsString('"partial_failure": true', (string) $this->latestAudit()['router_response']);
    }

    public function testCallbackFailuresBeforeAndAfterACommandAreAudited(): void
    {
        [$beforeGateway, $beforeClient] = $this->gateway();
        $before = $this->captureExecutionFailure(
            $beforeGateway,
            static fn (): never => throw new RuntimeException('Synthetic callback failure.')
        );

        self::assertFalse($before->result()->partialFailure);
        self::assertSame(0, $before->result()->attemptedCallCount);
        self::assertSame([], $beforeClient->calls);
        self::assertSame(1, $this->auditCount());

        [$afterGateway, $afterClient] = $this->gateway();
        $afterClient->queueResponse([]);
        $after = $this->captureExecutionFailure($afterGateway, static function (AuthorizedRouterOSWriterInterface $writer): never {
            $writer->execute(new RouterOSWriteCommand('/user-manager/user/remove', ['numbers' => '*1']));
            throw new RuntimeException('Synthetic callback failure after command.');
        });

        self::assertTrue($after->result()->partialFailure);
        self::assertSame(1, $after->result()->successfulCallCount);
        self::assertSame(2, $this->auditCount());
    }

    public function testWriterCannotBeUsedAfterCallback(): void
    {
        [$gateway, $client] = $this->gateway();
        $captured = null;

        $gateway->execute($this->request(), static function (AuthorizedRouterOSWriterInterface $writer) use (&$captured): void {
            $captured = $writer;
        });

        self::assertInstanceOf(AuthorizedRouterOSWriterInterface::class, $captured);
        self::assertTrue((new ReflectionClass($captured))->isAnonymous());
        try {
            $captured->execute(new RouterOSWriteCommand('/user-manager/user/remove', ['numbers' => '*1']));
            self::fail('Expected expired writer rejection.');
        } catch (RuntimeException $exception) {
            self::assertSame('Authorized RouterOS writer is no longer active.', $exception->getMessage());
        }
        self::assertSame([], $client->calls);
    }

    public function testReentrantExecuteIsRejectedAndOuterOperationAuditedOnce(): void
    {
        [$gateway, $client] = $this->gateway();

        $exception = $this->captureExecutionFailure($gateway, function () use ($gateway): void {
            $gateway->execute($this->request(), static fn (): null => null);
        });

        self::assertStringContainsString('Nested guarded', $exception->getMessage());
        self::assertSame([], $client->calls);
        self::assertSame(1, $this->auditCount());
    }

    public function testAuditFailureDoesNotTurnRouterSuccessIntoFailure(): void
    {
        $client = new FakeRouterOSClient();
        $guard = $this->configuredGuard(new FailingAuditWriteSafetyGuard(
            $this->database->connection(),
            static fn (): int => self::NOW,
            $this->backupDirectory
        ));
        $gateway = $this->makeGateway($client, $guard);

        $result = $gateway->execute($this->request(), static fn (): string => 'success');

        self::assertTrue($result->ok);
        self::assertFalse($result->auditRecorded);
        self::assertSame(0, $result->auditId);
        self::assertSame('RouterOS operation audit could not be recorded.', $result->auditWarning);
    }

    public function testOperationAndAuditFailuresRemainDistinguishable(): void
    {
        $client = new FakeRouterOSClient();
        $client->queueFailure(new RuntimeException('Synthetic operation failure.'));
        $guard = $this->configuredGuard(new FailingAuditWriteSafetyGuard(
            $this->database->connection(),
            static fn (): int => self::NOW,
            $this->backupDirectory
        ));
        $gateway = $this->makeGateway($client, $guard);

        $exception = $this->captureExecutionFailure($gateway, static function (AuthorizedRouterOSWriterInterface $writer): void {
            $writer->execute(new RouterOSWriteCommand('/user-manager/user/remove', ['numbers' => '*1']));
        });

        self::assertFalse($exception->result()->ok);
        self::assertFalse($exception->result()->auditRecorded);
        self::assertSame('Synthetic operation failure.', $exception->result()->safeError);
        self::assertSame('RouterOS operation audit could not be recorded.', $exception->result()->auditWarning);
        self::assertStringContainsString('audit could not be recorded', $exception->getMessage());
    }

    public function testPasswordIsAbsentFromResultAuditAndException(): void
    {
        [$gateway, $client] = $this->gateway();
        $password = 'SYNTHETIC-SECRET-PASSWORD';
        $client->queueResponse([['password' => $password, 'message' => 'accepted ' . $password]]);

        $result = $gateway->execute(
            $this->request(true, ['password' => $password, 'note' => 'contains ' . $password]),
            static function (AuthorizedRouterOSWriterInterface $writer) use ($password): array {
                $response = $writer->execute(new RouterOSWriteCommand('/user-manager/user/set', [
                    'numbers' => '*1',
                    'password' => $password,
                ]));

                return ['response' => $response, 'echo' => $password];
            }
        );

        $serializedResult = json_encode($result->toArray());
        $serializedAudit = json_encode($this->latestAudit());
        self::assertIsString($serializedResult);
        self::assertIsString($serializedAudit);
        self::assertStringNotContainsString($password, $serializedResult);
        self::assertStringNotContainsString($password, $serializedAudit);
        self::assertSame('<hidden>', $result->calls[0]->redactedParams['password']);
        self::assertSame('<hidden>', $result->calls[0]->response[0]['password']);
        self::assertStringNotContainsString($password, (string) $result->value['echo']);

        [$failureGateway, $failureClient] = $this->gateway();
        $failureClient->queueFailure(new RuntimeException('Rejected password ' . $password));
        $exception = $this->captureExecutionFailure(
            $failureGateway,
            static function (AuthorizedRouterOSWriterInterface $writer) use ($password): void {
                $writer->execute(new RouterOSWriteCommand('/user-manager/user/set', [
                    'numbers' => '*1',
                    'password' => $password,
                ]));
            }
        );
        self::assertStringNotContainsString($password, $exception->getMessage());
        self::assertStringNotContainsString($password, json_encode($exception->result()->toArray()) ?: '');
        self::assertNull($exception->getPrevious());
    }

    public function testCallbackResultRedactsSecretAndTokenFields(): void
    {
        [$gateway] = $this->gateway();
        $secret = 'SYNTHETIC-CALLBACK-SECRET';
        $token = 'SYNTHETIC-CALLBACK-TOKEN';

        $result = $gateway->execute(
            $this->request(),
            static fn (): array => [
                'nested' => [
                    'secret' => $secret,
                    'access_token' => $token,
                ],
            ]
        );

        $serializedResult = json_encode($result->toArray()) ?: '';
        $serializedAudit = json_encode($this->latestAudit()) ?: '';
        self::assertStringNotContainsString($secret, $serializedResult);
        self::assertStringNotContainsString($token, $serializedResult);
        self::assertStringNotContainsString($secret, $serializedAudit);
        self::assertStringNotContainsString($token, $serializedAudit);
        self::assertSame('<hidden>', $result->value['nested']['secret']);
        self::assertSame('<hidden>', $result->value['nested']['access_token']);
    }

    public function testNoFakeOrWriteFactoryExistsInProductionSource(): void
    {
        $references = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(GRENNET_TEST_ROOT . '/src')
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents !== false && (
                str_contains($contents, 'GreenNet\\Tests')
                || str_contains($contents, 'FakeRouterOS')
                || str_contains($contents, 'GuardedRouterOSWriteGatewayFactory')
            )) {
                $references[] = $file->getPathname();
            }
        }

        self::assertSame([], $references);
    }

    public function testWriterImplementationIsGatewayScopedAndGatewayHasNoRawWriteApi(): void
    {
        $sourceRoot = GRENNET_TEST_ROOT . '/src/app';
        self::assertFileDoesNotExist(
            $sourceRoot . '/Services/RouterOS/AuthorizedRouterOSWriter.php'
        );

        $violations = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            if (!str_ends_with($path, '/Services/RouterOS/GuardedRouterOSWriteGateway.php')
                && preg_match('/new\s+AuthorizedRouterOSWriter\b/', $contents) === 1
            ) {
                $violations[] = $path;
            }
        }

        self::assertSame([], $violations);

        $gateway = new ReflectionClass(GuardedRouterOSWriteGateway::class);
        self::assertFalse($gateway->hasMethod('comm'));
        self::assertFalse($gateway->hasMethod('write'));
        $publicMethods = array_values(array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $gateway->getMethods(\ReflectionMethod::IS_PUBLIC)
        ));
        sort($publicMethods);
        self::assertSame(['__construct', 'execute'], $publicMethods);
    }

    private function gateway(bool $freshBackup = true): array
    {
        $client = new FakeRouterOSClient();
        $guard = $this->configuredGuard(new WriteSafetyGuard(
            $this->database->connection(),
            static fn (): int => self::NOW,
            $this->backupDirectory
        ), $freshBackup);

        return [$this->makeGateway($client, $guard), $client, $guard];
    }

    private function configuredGuard(WriteSafetyGuard $guard, bool $freshBackup = true): WriteSafetyGuard
    {
        $guard->ensureTables();
        $this->setSetting('mikrotik_write_enabled', 'true');
        $this->setSetting('greennet_safe_mode', 'false');
        $this->setSetting('backup_guard_enabled', 'true');
        $this->setSetting('confirm_required', 'true');

        if ($freshBackup) {
            $backup = $this->backupDirectory . '/fresh.sqlite';
            file_put_contents($backup, 'synthetic-backup');
            touch($backup, self::NOW);
        }

        return $guard;
    }

    private function makeGateway(FakeRouterOSClient $client, WriteSafetyGuard $guard): GuardedRouterOSWriteGateway
    {
        return new GuardedRouterOSWriteGateway(
            $client,
            $guard,
            new WriteCommandPolicy(),
            new RouterOSWriteRedactor()
        );
    }

    private function request(bool $confirmed = true, array $auditParams = []): WriteExecutionRequest
    {
        return new WriteExecutionRequest(
            'synthetic_write_test',
            'synthetic_dataset',
            'synthetic-user',
            'synthetic command group',
            $auditParams,
            42,
            $confirmed
        );
    }

    private function setSetting(string $key, string $value): void
    {
        $statement = $this->database->connection()->prepare("
            INSERT INTO greennet_write_safety_settings (setting_key, setting_value)
            VALUES (:key, :value)
            ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value
        ");
        $statement->execute(['key' => $key, 'value' => $value]);
    }

    private function auditCount(): int
    {
        return (int) $this->database->connection()->query('SELECT COUNT(*) FROM api_audit_logs')->fetchColumn();
    }

    private function latestAudit(): array
    {
        $row = $this->database->connection()->query(
            'SELECT * FROM api_audit_logs ORDER BY id DESC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    private function expectGuardDenial(
        GuardedRouterOSWriteGateway $gateway,
        string $message,
        ?WriteExecutionRequest $request = null
    ): void {
        try {
            $gateway->execute($request ?? $this->request(), static fn (): null => null);
            self::fail('Expected guard denial.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
    }

    private function captureExecutionFailure(
        GuardedRouterOSWriteGateway $gateway,
        callable $operation
    ): GuardedWriteExecutionException {
        try {
            $gateway->execute($this->request(), $operation);
            self::fail('Expected guarded execution failure.');
        } catch (GuardedWriteExecutionException $exception) {
            return $exception;
        }
    }
}

final class FailingAuditWriteSafetyGuard extends WriteSafetyGuard
{
    public function recordRealAttempt(array $data): int
    {
        throw new RuntimeException('Synthetic audit failure with private database path.');
    }
}
