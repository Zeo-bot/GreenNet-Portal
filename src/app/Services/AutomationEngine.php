<?php

declare(strict_types=1);

namespace GreenNet\Services;

use GreenNet\Contracts\AuthorizedRouterOSWriterInterface;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\DTO\RouterOS\RouterOSWriteCommand;
use GreenNet\DTO\RouterOS\WriteExecutionRequest;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Router;
use GreenNet\Models\RouterPackageProfile;
use GreenNet\Services\RouterOS\NativeSubscriberRecordResolver;
use GreenNet\Services\RouterOS\RouterConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

final class AutomationEngine
{
    /** @var array<string, callable(): array> */
    private array $jobs;

    public function __construct(?array $jobs = null)
    {
        $this->ensureTables();
        $this->jobs = $jobs ?? [
            'lifecycle:evaluate' => fn (): array => $this->evaluateLifecycle(),
            'lifecycle:enforce' => fn (): array => $this->enforceLifecycle(),
            'renewal:resync' => fn (): array => $this->resyncRenewals(),
            'routers:refresh' => fn (): array => $this->refreshRouters(),
            'usage:refresh' => fn (): array => $this->refreshUsage(),
            'sessions:refresh' => fn (): array => $this->refreshSessions(),
        ];
    }

    public function registry(): array
    {
        return [
            'lifecycle:evaluate' => ['label' => 'تقييم دورة الاشتراكات', 'schedule' => 'كل 15 دقيقة'],
            'lifecycle:enforce' => ['label' => 'تنفيذ الانتهاء والحصة', 'schedule' => 'كل 15 دقيقة'],
            'renewal:resync' => ['label' => 'إعادة مزامنة التجديدات', 'schedule' => 'كل 15 دقيقة'],
            'routers:refresh' => ['label' => 'تحديث حالة الموجّهات', 'schedule' => 'كل 5 دقائق'],
            'usage:refresh' => ['label' => 'تحديث الاستهلاك', 'schedule' => 'كل 15 دقيقة'],
            'sessions:refresh' => ['label' => 'تحديث ملخص الجلسات', 'schedule' => 'كل 5 دقائق'],
        ];
    }

    public function run(?string $only = null): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'locked' => false, 'message' => 'Automation is disabled.', 'jobs' => []];
        }
        if (!$this->acquireLock()) {
            return ['ok' => false, 'locked' => true, 'message' => 'Another automation run is active.', 'jobs' => []];
        }

        $results = [];
        try {
            $selected = $only !== null ? array_intersect_key($this->jobs, [$only => true]) : $this->jobs;
            if ($selected === []) {
                throw new RuntimeException('Unknown automation job.');
            }
            foreach ($selected as $name => $job) {
                $results[$name] = $this->runJob($name, $job);
            }
        } finally {
            $this->releaseLock();
        }

        return [
            'ok' => !array_filter($results, static fn (array $row): bool => ($row['status'] ?? '') === 'failed'),
            'locked' => false,
            'jobs' => $results,
        ];
    }

    public function history(): array
    {
        return Database::connection()->query("
            SELECT h.* FROM automation_job_runs h
            JOIN (SELECT job_name, MAX(id) AS id FROM automation_job_runs GROUP BY job_name) latest ON latest.id = h.id
            ORDER BY h.job_name
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function health(): array
    {
        $failed = (int) Database::connection()->query("SELECT COUNT(*) FROM automation_job_runs WHERE status = 'failed' AND id IN (SELECT MAX(id) FROM automation_job_runs GROUP BY job_name)")->fetchColumn();
        $last = Database::connection()->query("SELECT finished_at FROM automation_job_runs ORDER BY id DESC LIMIT 1")->fetchColumn();
        $staleRouters = (int) Database::connection()->query("
            SELECT COUNT(*) FROM routers WHERE enabled = 1
            AND (last_checked_at IS NULL OR datetime(last_checked_at) < datetime('now', '-15 minutes'))
        ")->fetchColumn();
        return ['failed_jobs' => $failed, 'last_run' => is_string($last) ? $last : '', 'stale_routers' => $staleRouters];
    }

    public function enabled(): bool
    {
        return !in_array(strtolower((string) Config::get('AUTOMATION_ENABLED', 'true')), ['0', 'false', 'off', 'no'], true);
    }

    private function runJob(string $name, callable $job): array
    {
        $started = date('Y-m-d H:i:s');
        try {
            $result = $job();
            $status = (int) ($result['failed'] ?? 0) > 0 ? 'partial' : 'success';
            $error = implode('; ', array_slice((array) ($result['errors'] ?? []), 0, 5));
        } catch (Throwable $e) {
            $result = ['processed' => 0, 'succeeded' => 0, 'failed' => 1];
            $status = 'failed';
            $error = $e->getMessage();
        }
        $finished = date('Y-m-d H:i:s');
        $stmt = Database::connection()->prepare("
            INSERT INTO automation_job_runs
                (job_name, started_at, finished_at, status, processed_count, success_count, failure_count, error_summary)
            VALUES (:job, :started, :finished, :status, :processed, :success, :failure, :error)
        ");
        $stmt->execute([
            'job' => $name, 'started' => $started, 'finished' => $finished, 'status' => $status,
            'processed' => (int) ($result['processed'] ?? 0), 'success' => (int) ($result['succeeded'] ?? 0),
            'failure' => (int) ($result['failed'] ?? 0), 'error' => substr($error, 0, 1000),
        ]);
        return $result + ['status' => $status, 'error_summary' => $error];
    }

    private function evaluateLifecycle(): array
    {
        $service = new SubscriptionLifecycleService();
        return $this->each(CustomerLocal::all(), function (array $customer) use ($service): void {
            $service->evaluate((string) $customer['username'], null, true);
        });
    }

    private function enforceLifecycle(): array
    {
        $service = new SubscriptionLifecycleService();
        $rows = array_filter($service->previewAll(), fn (array $row): bool =>
            !empty($row['requires_enforcement'])
            && in_array($row['enforcement_state'] ?? 'pending', ['pending', 'failed', 'router_unavailable'], true)
            && $this->retryEligible((string) ($row['username'] ?? ''), 'enforce')
        );
        return $this->each($rows, function (array $row) use ($service): void {
            $username = (string) $row['username'];
            try {
                $this->disableExactAccount($username, (string) $row['backend']);
                $service->markEnforcement($username, 'enforced', 'Automated disable completed and verified.');
                $this->clearRetry($username, 'enforce');
            } catch (Throwable $e) {
                $service->markEnforcement($username, 'failed', $e->getMessage());
                $this->recordRetry($username, 'enforce', $e->getMessage());
                throw $e;
            }
        });
    }

    private function resyncRenewals(): array
    {
        $rows = Database::connection()->query("
            SELECT c.* FROM customers_local c
            JOIN subscription_lifecycle_states s ON lower(s.username) = lower(c.username)
            WHERE s.enforcement_state = 'renewal_sync_pending'
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $rows = array_values(array_filter($rows, fn (array $row): bool => $this->retryEligible((string) ($row['username'] ?? ''), 'resync')));
        return $this->each($rows, function (array $customer): void {
            $username = (string) $customer['username'];
            try {
                $this->applyMappedProfile($customer);
                Database::connection()->prepare("UPDATE customers_local SET service_status = 'active', updated_at = CURRENT_TIMESTAMP WHERE username = :username")->execute(['username' => $username]);
                (new SubscriptionLifecycleService())->markEnforcement($username, 'enforced', 'Renewal profile synchronized.');
                $this->clearRetry($username, 'resync');
            } catch (Throwable $e) {
                $this->recordRetry($username, 'resync', $e->getMessage());
                throw $e;
            }
        });
    }

    private function refreshRouters(): array
    {
        return $this->each(Router::allWithCustomerCounts(), function (array $router): void {
            if (empty($router['enabled'])) {
                return;
            }
            try {
                $read = RouterConnectionResolver::gatewayBundleForRouter((int) $router['id'], ['timeout' => 5])->read;
                $identity = $read->read('/system/identity/print');
                $resource = $read->read('/system/resource/print');
                Router::updateStatus((int) $router['id'], [
                    'ok' => true,
                    'identity' => (string) ($identity[0]['name'] ?? $identity['name'] ?? ''),
                    'routeros_version' => (string) ($resource[0]['version'] ?? $resource['version'] ?? ''),
                ]);
            } catch (Throwable $e) {
                Router::updateStatus((int) $router['id'], ['ok' => false, 'message' => $e->getMessage()]);
                throw $e;
            }
        });
    }

    private function refreshUsage(): array
    {
        return $this->each(CustomerLocal::all(), function (array $customer): void {
            $username = (string) $customer['username'];
            $data = (new CustomerDashboardService())->getDashboardData($username);
            $known = !empty($data['routeros_found']) && array_key_exists('used_bytes', $data);
            (new SubscriptionLifecycleService())->evaluate($username, $known ? (int) $data['used_bytes'] : null, true);
        });
    }

    private function refreshSessions(): array
    {
        return $this->each(Router::allWithCustomerCounts(), function (array $router): void {
            if (empty($router['enabled'])) {
                return;
            }
            $read = RouterConnectionResolver::gatewayBundleForRouter((int) $router['id'], ['timeout' => 5])->read;
            $hotspot = count($read->read('/ip/hotspot/active/print'));
            $pppoe = count($read->read('/ppp/active/print'));
            $stmt = Database::connection()->prepare("
                INSERT INTO router_session_snapshots (router_id, hotspot_count, pppoe_count, captured_at)
                VALUES (:router, :hotspot, :pppoe, CURRENT_TIMESTAMP)
                ON CONFLICT(router_id) DO UPDATE SET hotspot_count=excluded.hotspot_count, pppoe_count=excluded.pppoe_count, captured_at=CURRENT_TIMESTAMP
            ");
            $stmt->execute(['router' => (int) $router['id'], 'hotspot' => $hotspot, 'pppoe' => $pppoe]);
        });
    }

    private function disableExactAccount(string $username, string $backend): void
    {
        $bundle = RouterConnectionResolver::gatewayBundleForCustomer($username, ['timeout' => 6]);
        if ($backend === 'user-manager') {
            $rows = $bundle->read->read('/user-manager/user/print', ['?name' => $username]);
            $matches = array_values(array_filter($rows, static fn ($row): bool => is_array($row) && (string) ($row['name'] ?? '') === $username));
            if (count($matches) !== 1 || trim((string) ($matches[0]['.id'] ?? '')) === '') {
                throw new RuntimeException(count($matches) === 0 ? 'Remote account missing.' : 'User Manager exact record is ambiguous.');
            }
            $id = (string) $matches[0]['.id'];
            $command = '/user-manager/user/set';
        } else {
            $record = (new NativeSubscriberRecordResolver())->resolve($bundle->read, $username, $backend);
            if ($record === null || trim((string) ($record['.id'] ?? '')) === '') {
                throw new RuntimeException('Remote account missing.');
            }
            $id = (string) $record['.id'];
            $command = $backend === 'native-hotspot' ? '/ip/hotspot/user/set' : '/ppp/secret/set';
        }
        $result = $bundle->write->execute(
            new WriteExecutionRequest('automated_lifecycle_enforcement', $backend, $username, $command, ['numbers' => $id, 'disabled' => 'yes'], 0, true),
            function (AuthorizedRouterOSWriterInterface $writer) use ($command, $id, $bundle, $backend, $username): array {
                $writer->execute(new RouterOSWriteCommand($command, ['numbers' => $id, 'disabled' => 'yes']));
                if ($backend === 'user-manager') {
                    $rows = $bundle->read->read('/user-manager/user/print', ['?name' => $username]);
                    $matches = array_values(array_filter($rows, static fn ($row): bool =>
                        is_array($row) && (string) ($row['.id'] ?? '') === $id && (string) ($row['name'] ?? '') === $username
                    ));
                    $after = $matches[0] ?? null;
                } else {
                    $after = (new NativeSubscriberRecordResolver())->resolve($bundle->read, $username, $backend);
                }
                if (!is_array($after)
                    || !in_array(strtolower((string) ($after['disabled'] ?? '')), ['true', 'yes'], true)) {
                    throw new RuntimeException('Automated disable verification failed.');
                }
                return ['verified' => true, 'record_id' => $id];
            }
        );
        if (!$result->ok) {
            throw new RuntimeException($result->safeError ?: 'Guarded enforcement failed.');
        }
    }

    private function applyMappedProfile(array $customer): void
    {
        $username = (string) $customer['username'];
        $backend = (string) ($customer['service_backend'] ?? 'user-manager');
        $profile = RouterPackageProfile::profileName((int) ($customer['router_id'] ?? 0), (int) ($customer['package_id'] ?? 0), '', $backend);
        if ($profile === '') {
            throw new RuntimeException('Backend package mapping is missing.');
        }
        $bundle = RouterConnectionResolver::gatewayBundleForCustomer($username, ['timeout' => 6]);
        if ($backend === 'user-manager') {
            $users = $bundle->read->read('/user-manager/user/print', ['?name' => $username]);
            $user = array_values(array_filter($users, static fn ($row): bool => is_array($row) && (string) ($row['name'] ?? '') === $username));
            if (count($user) !== 1) {
                throw new RuntimeException('User Manager exact account is missing or ambiguous.');
            }
            $userId = (string) ($user[0]['.id'] ?? '');
            $relations = array_values(array_filter(
                $bundle->read->read('/user-manager/user-profile/print'),
                static fn ($row): bool => is_array($row) && in_array((string) ($row['user'] ?? ''), [$username, $userId], true)
            ));
            $result = $bundle->write->execute(
                new WriteExecutionRequest('automated_renewal_resync', $backend, $username, 'replace profile relations', ['profile' => $profile], 0, true),
                function (AuthorizedRouterOSWriterInterface $writer) use ($relations, $username, $profile): array {
                    foreach ($relations as $relation) {
                        $id = trim((string) ($relation['.id'] ?? ''));
                        if ($id !== '') {
                            $writer->execute(new RouterOSWriteCommand('/user-manager/user-profile/remove', ['numbers' => $id]));
                        }
                    }
                    return $writer->execute(new RouterOSWriteCommand('/user-manager/user-profile/add', ['user' => $username, 'profile' => $profile]));
                }
            );
            if (!$result->ok) {
                throw new RuntimeException($result->safeError ?: 'User Manager renewal synchronization failed.');
            }
            $afterRelations = array_values(array_filter(
                $bundle->read->read('/user-manager/user-profile/print'),
                static fn ($row): bool => is_array($row)
                    && in_array((string) ($row['user'] ?? ''), [$username, $userId], true)
                    && (string) ($row['profile'] ?? '') === $profile
            ));
            if (count($afterRelations) !== 1) {
                throw new RuntimeException('User Manager renewal synchronization verification failed.');
            }
            return;
        }
        $record = (new NativeSubscriberRecordResolver())->resolve($bundle->read, $username, $backend);
        if ($record === null || trim((string) ($record['.id'] ?? '')) === '') {
            throw new RuntimeException('Native exact account is missing.');
        }
        $command = $backend === 'native-hotspot' ? '/ip/hotspot/user/set' : '/ppp/secret/set';
        $result = $bundle->write->execute(
            new WriteExecutionRequest('automated_renewal_resync', $backend, $username, $command, ['profile' => $profile], 0, true),
            fn (AuthorizedRouterOSWriterInterface $writer): array => $writer->execute(new RouterOSWriteCommand($command, ['numbers' => (string) $record['.id'], 'profile' => $profile]))
        );
        if (!$result->ok) {
            throw new RuntimeException($result->safeError ?: 'Native renewal synchronization failed.');
        }
        $after = (new NativeSubscriberRecordResolver())->resolve($bundle->read, $username, $backend);
        if (!is_array($after) || (string) ($after['profile'] ?? '') !== $profile) {
            throw new RuntimeException('Native renewal synchronization verification failed.');
        }
    }

    private function each(iterable $items, callable $operation): array
    {
        $processed = $succeeded = $failed = 0;
        $errors = [];
        foreach ($items as $item) {
            $processed++;
            try {
                $operation($item);
                $succeeded++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }
        return compact('processed', 'succeeded', 'failed', 'errors');
    }

    private function retryEligible(string $key, string $job): bool
    {
        $stmt = Database::connection()->prepare("SELECT next_retry_at FROM automation_retries WHERE item_key=:key AND job_name=:job");
        $stmt->execute(['key' => $key, 'job' => $job]);
        $next = $stmt->fetchColumn();
        return !is_string($next) || $next === '' || strtotime($next) <= time();
    }

    private function recordRetry(string $key, string $job, string $error): void
    {
        $delay = max(60, (int) Config::get('AUTOMATION_RETRY_DELAY_SECONDS', 900));
        $stmt = Database::connection()->prepare("
            INSERT INTO automation_retries (item_key,job_name,attempt_count,last_attempt_at,next_retry_at,last_error)
            VALUES (:key,:job,1,CURRENT_TIMESTAMP,:next,:error)
            ON CONFLICT(item_key,job_name) DO UPDATE SET attempt_count=attempt_count+1,last_attempt_at=CURRENT_TIMESTAMP,next_retry_at=excluded.next_retry_at,last_error=excluded.last_error
        ");
        $stmt->execute(['key' => $key, 'job' => $job, 'next' => date('Y-m-d H:i:s', time() + $delay), 'error' => substr($error, 0, 1000)]);
    }

    private function clearRetry(string $key, string $job): void
    {
        $stmt = Database::connection()->prepare("DELETE FROM automation_retries WHERE item_key=:key AND job_name=:job");
        $stmt->execute(['key' => $key, 'job' => $job]);
    }

    private function acquireLock(): bool
    {
        $stale = max(60, (int) Config::get('AUTOMATION_STALE_LOCK_SECONDS', 1800));
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $row = $pdo->query("SELECT acquired_at FROM automation_lock WHERE id=1")->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && strtotime((string) $row['acquired_at']) > time() - $stale) {
                $pdo->rollBack();
                return false;
            }
            $pdo->exec("INSERT INTO automation_lock (id,acquired_at,owner) VALUES (1,CURRENT_TIMESTAMP,'greennet-jobs') ON CONFLICT(id) DO UPDATE SET acquired_at=CURRENT_TIMESTAMP,owner='greennet-jobs'");
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function releaseLock(): void
    {
        Database::connection()->exec("DELETE FROM automation_lock WHERE id=1");
    }

    private function ensureTables(): void
    {
        $pdo = Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS automation_lock (id INTEGER PRIMARY KEY, acquired_at TEXT NOT NULL, owner TEXT DEFAULT '')");
        $pdo->exec("CREATE TABLE IF NOT EXISTS automation_job_runs (id INTEGER PRIMARY KEY AUTOINCREMENT,job_name TEXT NOT NULL,started_at TEXT NOT NULL,finished_at TEXT,status TEXT NOT NULL,processed_count INTEGER DEFAULT 0,success_count INTEGER DEFAULT 0,failure_count INTEGER DEFAULT 0,error_summary TEXT DEFAULT '')");
        $pdo->exec("CREATE TABLE IF NOT EXISTS automation_retries (item_key TEXT NOT NULL,job_name TEXT NOT NULL,attempt_count INTEGER DEFAULT 0,last_attempt_at TEXT,next_retry_at TEXT,last_error TEXT DEFAULT '',PRIMARY KEY(item_key,job_name))");
        $pdo->exec("CREATE TABLE IF NOT EXISTS router_session_snapshots (router_id INTEGER PRIMARY KEY,hotspot_count INTEGER DEFAULT 0,pppoe_count INTEGER DEFAULT 0,captured_at TEXT NOT NULL)");
    }
}
