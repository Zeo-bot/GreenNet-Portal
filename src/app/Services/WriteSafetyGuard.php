<?php

declare(strict_types=1);

namespace GreenNet\Services;

use Closure;
use GreenNet\Core\Database;
use GreenNet\Models\AppLog;
use GreenNet\Services\RouterOS\RouterOSSensitiveDataRedactor;
use PDO;
use RuntimeException;
use Throwable;

class WriteSafetyGuard
{
    private string $settingsTable = 'greennet_write_safety_settings';
    private string $queueTable = 'mikrotik_transaction_queue';
    private string $auditTable = 'api_audit_logs';
    private ?PDO $databaseConnection;
    private Closure $clock;
    private ?string $backupDirectory;
    private RouterOSSensitiveDataRedactor $redactor;

    public function __construct(
        ?PDO $database = null,
        ?callable $clock = null,
        ?string $backupDirectory = null,
        ?RouterOSSensitiveDataRedactor $redactor = null
    ) {
        $this->databaseConnection = $database;
        $this->clock = $clock !== null
            ? Closure::fromCallable($clock)
            : static fn (): int => time();
        $this->backupDirectory = $backupDirectory;
        $this->redactor = $redactor ?? new RouterOSSensitiveDataRedactor();
    }

    public function ensureTables(): void
    {
        $this->database()->exec("
            CREATE TABLE IF NOT EXISTS {$this->settingsTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT NOT NULL UNIQUE,
                setting_value TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->database()->exec("
            CREATE TABLE IF NOT EXISTS {$this->queueTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action TEXT DEFAULT '',
                username TEXT DEFAULT '',
                payload TEXT DEFAULT '',
                status TEXT DEFAULT 'pending',
                attempts INTEGER DEFAULT 0,
                last_error TEXT DEFAULT '',
                created_by TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->database()->exec("
            CREATE TABLE IF NOT EXISTS {$this->auditTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                admin_username TEXT DEFAULT '',
                action TEXT DEFAULT '',
                dataset TEXT DEFAULT '',
                username TEXT DEFAULT '',
                command TEXT DEFAULT '',
                params TEXT DEFAULT '',
                dry_run INTEGER DEFAULT 1,
                executed INTEGER DEFAULT 0,
                success INTEGER DEFAULT 0,
                router_response TEXT DEFAULT '',
                ip_address TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        foreach ($this->defaults() as $key => $value) {
            if ($this->get($key, null) === null) {
                $this->set($key, $value);
            }
        }

        try {
            $this->database()->exec("CREATE INDEX IF NOT EXISTS idx_api_audit_logs_created ON {$this->auditTable}(created_at)");
            $this->database()->exec("CREATE INDEX IF NOT EXISTS idx_mikrotik_queue_status ON {$this->queueTable}(status)");
        } catch (Throwable) {
            // ignore
        }
    }

    public function settings(): array
    {
        $this->ensureTables();

        $settings = $this->defaults();

        try {
            $stmt = $this->database()->query("
                SELECT setting_key, setting_value
                FROM {$this->settingsTable}
            ");

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $key = (string) ($row['setting_key'] ?? '');

                if ($key !== '') {
                    $settings[$key] = (string) ($row['setting_value'] ?? '');
                }
            }
        } catch (Throwable) {
            return $settings;
        }

        return $settings;
    }

    public function dryRunAllowed(): bool
    {
        $settings = $this->settings();

        return ($settings['dry_run_required'] ?? 'true') === 'true';
    }

    public function realWriteAllowed(): bool
    {
        $settings = $this->settings();

        if (($settings['mikrotik_write_enabled'] ?? 'false') !== 'true') {
            return false;
        }

        if (($settings['confirm_required'] ?? 'true') !== 'true') {
            return false;
        }

        return true;
    }

    public function assertDryRunAllowed(): void
    {
        if (!$this->dryRunAllowed()) {
            throw new RuntimeException('Dry Run is disabled. Enable Dry Run Required from Write Safety.');
        }
    }

    public function assertRealWriteAllowed(array $options = []): void
    {
        $settings = $this->settings();

        if (($settings['mikrotik_write_enabled'] ?? 'false') !== 'true') {
            throw new RuntimeException('Real MikroTik write is blocked because MikroTik Write Enabled is OFF.');
        }

        if (($settings['greennet_safe_mode'] ?? 'true') === 'true' && (($options['allow_safe_mode'] ?? false) !== true)) {
            throw new RuntimeException('Real MikroTik write is blocked because Safe Mode is ON.');
        }

        if (($settings['backup_guard_enabled'] ?? 'true') === 'true' && !$this->hasFreshBackup()) {
            throw new RuntimeException('Real MikroTik write is blocked because no fresh backup was found in the last 24 hours.');
        }

        if (($settings['confirm_required'] ?? 'true') === 'true' && (($options['confirmed'] ?? false) !== true)) {
            throw new RuntimeException('Real MikroTik write requires explicit confirmation.');
        }
    }

    public function hasFreshBackup(int $hours = 24): bool
    {
        $latest = $this->latestBackup();

        if (!$latest) {
            return false;
        }

        return ($this->now() - (int) $latest['time']) <= ($hours * 3600);
    }

    public function latestBackup(): ?array
    {
        $dir = $this->backupDirectory
            ?? (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/backups';

        if (!is_dir($dir)) {
            return null;
        }

        $files = [];

        foreach ((glob($dir . '/*') ?: []) as $file) {
            if (is_file($file)) {
                $files[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'time' => filemtime($file) ?: 0,
                    'size' => filesize($file) ?: 0,
                ];
            }
        }

        if (count($files) === 0) {
            return null;
        }

        usort($files, static fn (array $a, array $b): int => (int) $b['time'] <=> (int) $a['time']);

        return $files[0];
    }

    public function recordDryRun(array $data): int
    {
        $this->ensureTables();
        $data = $this->redactor->redact($data);

        return $this->recordAudit([
            'admin_username' => (string) ($_SESSION['admin_username'] ?? 'admin'),
            'action' => (string) ($data['action'] ?? ''),
            'dataset' => (string) ($data['dataset'] ?? 'mikrotik'),
            'username' => (string) ($data['username'] ?? ''),
            'command' => (string) ($data['command'] ?? ''),
            'params' => $this->json($data['params'] ?? []),
            'dry_run' => 1,
            'executed' => 0,
            'success' => 1,
            'router_response' => (string) ($data['router_response'] ?? 'Dry run only. No command executed.'),
            'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    }

    public function recordRealAttempt(array $data): int
    {
        $this->ensureTables();
        $data = $this->redactor->redact($data);

        return $this->recordAudit([
            'admin_username' => (string) ($_SESSION['admin_username'] ?? 'admin'),
            'action' => (string) ($data['action'] ?? ''),
            'dataset' => (string) ($data['dataset'] ?? 'mikrotik'),
            'username' => (string) ($data['username'] ?? ''),
            'command' => (string) ($data['command'] ?? ''),
            'params' => $this->json($data['params'] ?? []),
            'dry_run' => 0,
            'executed' => (int) ($data['executed'] ?? 0),
            'success' => (int) ($data['success'] ?? 0),
            'router_response' => (string) ($data['router_response'] ?? ''),
            'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    }

    public function queue(array $data): int
    {
        $this->ensureTables();
        $data = $this->redactor->redact($data);

        $stmt = $this->database()->prepare("
            INSERT INTO {$this->queueTable} (
                action,
                username,
                payload,
                status,
                attempts,
                last_error,
                created_by,
                created_at,
                updated_at
            )
            VALUES (
                :action,
                :username,
                :payload,
                :status,
                0,
                '',
                :created_by,
                :created_at,
                :updated_at
            )
        ");

        $now = date('Y-m-d H:i:s', $this->now());

        $stmt->execute([
            'action' => (string) ($data['action'] ?? ''),
            'username' => (string) ($data['username'] ?? ''),
            'payload' => $this->json($data['payload'] ?? []),
            'status' => (string) ($data['status'] ?? 'pending'),
            'created_by' => (string) ($_SESSION['admin_username'] ?? 'admin'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->database()->lastInsertId();
    }

    public function preflight(): array
    {
        $settings = $this->settings();
        $latestBackup = $this->latestBackup();

        return [
            'settings' => $settings,
            'safe_mode' => ($settings['greennet_safe_mode'] ?? 'true') === 'true',
            'write_enabled' => ($settings['mikrotik_write_enabled'] ?? 'false') === 'true',
            'dry_run_required' => ($settings['dry_run_required'] ?? 'true') === 'true',
            'backup_guard_enabled' => ($settings['backup_guard_enabled'] ?? 'true') === 'true',
            'confirm_required' => ($settings['confirm_required'] ?? 'true') === 'true',
            'transaction_queue_enabled' => ($settings['transaction_queue_enabled'] ?? 'true') === 'true',
            'latest_backup' => $latestBackup,
            'fresh_backup' => $this->hasFreshBackup(),
            'ready_for_dry_run' => ($settings['dry_run_required'] ?? 'true') === 'true',
            'ready_for_real_write' => $this->realWriteAllowed() && $this->hasFreshBackup(),
        ];
    }

    private function recordAudit(array $row): int
    {
        $stmt = $this->database()->prepare("
            INSERT INTO {$this->auditTable} (
                admin_username,
                action,
                dataset,
                username,
                command,
                params,
                dry_run,
                executed,
                success,
                router_response,
                ip_address,
                created_at
            )
            VALUES (
                :admin_username,
                :action,
                :dataset,
                :username,
                :command,
                :params,
                :dry_run,
                :executed,
                :success,
                :router_response,
                :ip_address,
                :created_at
            )
        ");

        $stmt->execute([
            'admin_username' => (string) ($row['admin_username'] ?? 'admin'),
            'action' => (string) ($row['action'] ?? ''),
            'dataset' => (string) ($row['dataset'] ?? 'mikrotik'),
            'username' => (string) ($row['username'] ?? ''),
            'command' => (string) ($row['command'] ?? ''),
            'params' => (string) ($row['params'] ?? ''),
            'dry_run' => (int) ($row['dry_run'] ?? 1),
            'executed' => (int) ($row['executed'] ?? 0),
            'success' => (int) ($row['success'] ?? 0),
            'router_response' => (string) ($row['router_response'] ?? ''),
            'ip_address' => (string) ($row['ip_address'] ?? ''),
            'created_at' => date('Y-m-d H:i:s', $this->now()),
        ]);

        return (int) $this->database()->lastInsertId();
    }

    private function get(string $key, ?string $default = ''): ?string
    {
        try {
            $stmt = $this->database()->prepare("
                SELECT setting_value
                FROM {$this->settingsTable}
                WHERE setting_key = :key
                LIMIT 1
            ");

            $stmt->execute([
                'key' => $key,
            ]);

            $value = $stmt->fetchColumn();

            return $value === false ? $default : (string) $value;
        } catch (Throwable) {
            return $default;
        }
    }

    private function set(string $key, string $value): void
    {
        $now = date('Y-m-d H:i:s', $this->now());

        $stmt = $this->database()->prepare("
            INSERT INTO {$this->settingsTable} (
                setting_key,
                setting_value,
                created_at,
                updated_at
            )
            VALUES (
                :key,
                :value,
                :created_at,
                :updated_at
            )
            ON CONFLICT(setting_key) DO UPDATE SET
                setting_value = excluded.setting_value,
                updated_at = excluded.updated_at
        ");

        $stmt->execute([
            'key' => $key,
            'value' => $value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function defaults(): array
    {
        return [
            'mikrotik_write_enabled' => 'false',
            'greennet_safe_mode' => 'true',
            'backup_guard_enabled' => 'true',
            'dry_run_required' => 'true',
            'confirm_required' => 'true',
            'transaction_queue_enabled' => 'true',
        ];
    }

    private function json(mixed $value): string
    {
        return $this->redactor->json($value);
    }

    private function database(): PDO
    {
        return $this->databaseConnection ?? Database::connection();
    }

    private function now(): int
    {
        return (int) ($this->clock)();
    }
}
