<?php

declare(strict_types=1);

namespace GreenNet\Tests\Unit;

use GreenNet\Services\WriteSafetyGuard;
use GreenNet\Tests\Support\TempDatabase;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WriteSafetyGuardTest extends TestCase
{
    private const NOW = 1_700_000_000;

    private TempDatabase $database;
    private string $backupDirectory;
    private WriteSafetyGuard $guard;

    protected function setUp(): void
    {
        $this->database = new TempDatabase();
        $this->backupDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'greennet-backups-'
            . bin2hex(random_bytes(12));

        self::assertTrue(mkdir($this->backupDirectory, 0700, true));

        $this->guard = new WriteSafetyGuard(
            $this->database->connection(),
            static fn (): int => self::NOW,
            $this->backupDirectory
        );
        $this->guard->ensureTables();
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

        unset($this->guard);
        $this->database->cleanup();
    }

    public function testDefaultsDenyRealWritesAndAllowDryRuns(): void
    {
        self::assertTrue($this->guard->dryRunAllowed());
        self::assertFalse($this->guard->realWriteAllowed());
        self::assertSame([
            'mikrotik_write_enabled' => 'false',
            'greennet_safe_mode' => 'true',
            'backup_guard_enabled' => 'true',
            'dry_run_required' => 'true',
            'confirm_required' => 'true',
            'transaction_queue_enabled' => 'true',
        ], $this->guard->settings());
    }

    public function testOperationalAuditStoresStructuredMetadataAndCorrelationWithoutSecrets(): void
    {
        $_SESSION['admin_username'] = 'synthetic-admin';
        $_SERVER['REMOTE_ADDR'] = '192.0.2.44';

        $id = $this->guard->recordRealAttempt([
            'action' => 'reset_counters',
            'dataset' => 'native-hotspot',
            'username' => 'synthetic-user',
            'command' => '/ip/hotspot/user/reset-counters',
            'params' => [
                'router_id' => 7,
                'router_identity' => 'synthetic-router',
                'backend' => 'native-hotspot',
                'target_type' => 'hotspot-user',
                'local_record_id' => 19,
                'routeros_id' => '*A',
                'before' => ['bytes-in' => '10', 'password' => 'never-store-this'],
            ],
            'after_state' => ['bytes-in' => '0'],
            'executed' => 1,
            'success' => 1,
            'reconciliation_status' => 'verified',
        ]);

        $stmt = $this->database->connection()->prepare('SELECT * FROM api_audit_logs WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($row);
        self::assertMatchesRegularExpression('/^[a-f0-9]{24}$/', (string) $row['correlation_id']);
        self::assertSame('7', (string) $row['router_id']);
        self::assertSame('synthetic-router', $row['router_identity']);
        self::assertSame('native-hotspot', $row['backend']);
        self::assertSame('hotspot-user', $row['target_type']);
        self::assertSame('19', (string) $row['local_record_id']);
        self::assertSame('*A', $row['routeros_record_id']);
        self::assertSame('verified', $row['reconciliation_status']);
        self::assertStringNotContainsString('never-store-this', serialize($row));
    }

    public function testDryRunRequirementCanAllowOrDenyWithStableMessage(): void
    {
        $this->setSetting('dry_run_required', 'false');

        self::assertFalse($this->guard->dryRunAllowed());

        try {
            $this->guard->assertDryRunAllowed();
            self::fail('Expected dry-run denial.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Dry Run is disabled. Enable Dry Run Required from Write Safety.',
                $exception->getMessage()
            );
        }

        $this->setSetting('dry_run_required', 'true');
        $this->guard->assertDryRunAllowed();
        self::assertTrue($this->guard->dryRunAllowed());
    }

    #[DataProvider('realWriteDenialProvider')]
    public function testRealWriteDenialsKeepTheirCurrentOrderAndMessages(
        array $settings,
        array $options,
        ?int $backupAgeSeconds,
        string $expectedMessage
    ): void {
        $this->setSettings($settings);

        if ($backupAgeSeconds !== null) {
            $this->createBackup(self::NOW - $backupAgeSeconds);
        }

        try {
            $this->guard->assertRealWriteAllowed($options);
            self::fail('Expected real-write denial.');
        } catch (RuntimeException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());
        }
    }

    public static function realWriteDenialProvider(): array
    {
        return [
            'write disabled is checked first' => [
                [],
                [],
                null,
                'Real MikroTik write is blocked because MikroTik Write Enabled is OFF.',
            ],
            'safe mode blocks without explicit bypass' => [
                ['mikrotik_write_enabled' => 'true'],
                [],
                null,
                'Real MikroTik write is blocked because Safe Mode is ON.',
            ],
            'missing backup blocks after safe mode' => [
                ['mikrotik_write_enabled' => 'true', 'greennet_safe_mode' => 'false'],
                [],
                null,
                'Real MikroTik write is blocked because no fresh backup was found in the last 24 hours.',
            ],
            'stale backup uses the same backup denial' => [
                ['mikrotik_write_enabled' => 'true', 'greennet_safe_mode' => 'false'],
                [],
                (24 * 3600) + 1,
                'Real MikroTik write is blocked because no fresh backup was found in the last 24 hours.',
            ],
            'confirmation is checked after a fresh backup' => [
                ['mikrotik_write_enabled' => 'true', 'greennet_safe_mode' => 'false'],
                [],
                60,
                'Real MikroTik write requires explicit confirmation.',
            ],
        ];
    }

    public function testSafeModeCanBeExplicitlyAllowedByExistingOption(): void
    {
        $this->setSettings([
            'mikrotik_write_enabled' => 'true',
            'backup_guard_enabled' => 'false',
            'confirm_required' => 'false',
        ]);

        $this->guard->assertRealWriteAllowed(['allow_safe_mode' => true]);
        self::assertFalse($this->guard->realWriteAllowed());
    }

    public function testDisabledBackupGuardAllowsWriteWithoutBackup(): void
    {
        $this->setSettings([
            'mikrotik_write_enabled' => 'true',
            'greennet_safe_mode' => 'false',
            'backup_guard_enabled' => 'false',
            'confirm_required' => 'true',
        ]);

        self::assertFalse($this->guard->hasFreshBackup());
        $this->guard->assertRealWriteAllowed(['confirmed' => true]);
        self::assertTrue($this->guard->realWriteAllowed());
    }

    public function testMissingOldBoundaryAndFreshBackupsUseInjectedClock(): void
    {
        self::assertFalse($this->guard->hasFreshBackup());

        $this->createBackup(self::NOW - (24 * 3600) - 1);
        self::assertFalse($this->guard->hasFreshBackup());

        $this->createBackup(self::NOW - (24 * 3600), 'boundary.dat');
        self::assertTrue($this->guard->hasFreshBackup());

        $latestPath = $this->createBackup(self::NOW - 10, 'fresh.dat');
        self::assertTrue($this->guard->hasFreshBackup());
        self::assertSame($latestPath, $this->guard->latestBackup()['path']);
    }

    public function testMissingBackupDirectoryReturnsNoLatestOrFreshBackup(): void
    {
        $missingDirectory = $this->backupDirectory . DIRECTORY_SEPARATOR . 'missing';
        $guard = new WriteSafetyGuard(
            $this->pdo(),
            static fn (): int => self::NOW,
            $missingDirectory
        );

        self::assertNull($guard->latestBackup());
        self::assertFalse($guard->hasFreshBackup());
    }

    public function testAllConditionsAllowTheFinalWriteDecision(): void
    {
        $this->setSettings([
            'mikrotik_write_enabled' => 'true',
            'greennet_safe_mode' => 'false',
            'backup_guard_enabled' => 'true',
            'confirm_required' => 'true',
        ]);
        $this->createBackup(self::NOW - 60);

        $this->guard->assertRealWriteAllowed(['confirmed' => true]);

        self::assertTrue($this->guard->realWriteAllowed());
        self::assertTrue($this->guard->hasFreshBackup());
        self::assertTrue($this->guard->preflight()['ready_for_real_write']);
    }

    public function testRealWriteAllowedOnlyChecksWriteAndConfirmationSettings(): void
    {
        $this->setSettings([
            'mikrotik_write_enabled' => 'true',
            'greennet_safe_mode' => 'true',
            'backup_guard_enabled' => 'true',
            'confirm_required' => 'true',
        ]);

        self::assertTrue($this->guard->realWriteAllowed());
        self::assertFalse($this->guard->preflight()['ready_for_real_write']);
    }

    public function testPreflightStillRequiresFreshBackupWhenBackupGuardIsDisabled(): void
    {
        $this->setSettings([
            'mikrotik_write_enabled' => 'true',
            'greennet_safe_mode' => 'false',
            'backup_guard_enabled' => 'false',
            'confirm_required' => 'true',
        ]);

        $this->guard->assertRealWriteAllowed(['confirmed' => true]);
        $preflight = $this->guard->preflight();

        self::assertFalse($preflight['fresh_backup']);
        self::assertFalse($preflight['ready_for_real_write']);
    }

    public function testAllPersistenceBoundariesRecursivelyRedactSecretsAndRepeatedValues(): void
    {
        $secret = 'synthetic-persistence-value-' . bin2hex(random_bytes(8));
        $safe = ['.id' => '*synthetic', 'name' => 'synthetic-user', 'disabled' => 'false'];
        $nested = $safe + [
            'credentials' => ['API_Key' => $secret],
            'repeated' => 'Router response repeated ' . $secret,
        ];

        $this->guard->recordDryRun([
            'action' => 'synthetic_dry_run',
            'params' => $nested,
            'router_response' => json_encode($nested, JSON_UNESCAPED_SLASHES),
        ]);
        $this->guard->recordRealAttempt([
            'action' => 'synthetic_real_attempt',
            'params' => ['private-key' => $secret, 'safe' => $safe],
            'router_response' => 'Synthetic response ' . $secret,
        ]);
        $this->guard->queue([
            'action' => 'synthetic_queue',
            'payload' => ['Authorization' => $secret, 'safe' => $safe, 'copy' => $secret],
        ]);

        $auditRows = $this->pdo()->query('SELECT params, router_response FROM api_audit_logs')
            ->fetchAll(PDO::FETCH_ASSOC);
        $queueRows = $this->pdo()->query('SELECT payload FROM mikrotik_transaction_queue')
            ->fetchAll(PDO::FETCH_ASSOC);
        $serialized = json_encode([$auditRows, $queueRows], JSON_UNESCAPED_SLASHES);

        self::assertIsString($serialized);
        self::assertStringNotContainsString($secret, $serialized);
        self::assertStringContainsString('<hidden>', $serialized);
        self::assertStringContainsString('*synthetic', $serialized);
        self::assertStringContainsString('synthetic-user', $serialized);
        self::assertStringNotContainsString($secret, (string) file_get_contents($this->database->path()));
    }

    private function setSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->setSetting((string) $key, (string) $value);
        }
    }

    private function setSetting(string $key, string $value): void
    {
        $statement = $this->pdo()->prepare("
            UPDATE greennet_write_safety_settings
            SET setting_value = :value
            WHERE setting_key = :key
        ");
        $statement->execute(['key' => $key, 'value' => $value]);
    }

    private function createBackup(int $timestamp, string $name = 'synthetic-backup.dat'): string
    {
        $path = $this->backupDirectory . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, 'synthetic test backup marker');
        touch($path, $timestamp);
        clearstatcache(true, $path);

        return $path;
    }

    private function pdo(): PDO
    {
        return $this->database->connection();
    }
}
