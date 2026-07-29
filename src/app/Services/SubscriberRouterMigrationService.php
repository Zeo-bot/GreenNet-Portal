<?php

declare(strict_types=1);

namespace GreenNet\Services;

use Closure;
use GreenNet\Contracts\AuthorizedRouterOSWriterInterface;
use GreenNet\Core\Database;
use GreenNet\DTO\RouterOS\RouterOSWriteCommand;
use GreenNet\DTO\RouterOS\WriteExecutionRequest;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Router;
use GreenNet\Models\RouterPackageProfile;
use GreenNet\Models\SubscriberRouterMigration;
use GreenNet\Services\RouterOS\NativeSubscriberRecordResolver;
use GreenNet\Services\RouterOS\RouterConnectionResolver;
use GreenNet\Services\RouterOS\RouterOSGatewayBundle;
use PDO;
use RuntimeException;
use Throwable;

final class SubscriberRouterMigrationService
{
    private Closure $bundleForRouter;

    public function __construct(?callable $bundleForRouter = null)
    {
        $this->bundleForRouter = $bundleForRouter !== null
            ? Closure::fromCallable($bundleForRouter)
            : static fn (int $routerId): RouterOSGatewayBundle => RouterConnectionResolver::gatewayBundleForRouter(
                $routerId,
                ['timeout' => 6]
            );
    }

    public function usableTargets(int $sourceRouterId): array
    {
        $targets = [];
        foreach (Router::allWithCustomerCounts() as $router) {
            if (empty($router['enabled']) || (int) ($router['id'] ?? 0) === $sourceRouterId) {
                continue;
            }
            $roles = (new RouterOnboardingService())->roles($router);
            $backends = [];
            if (in_array('user-manager', $roles, true)) {
                $backends[] = 'user-manager';
            }
            if (in_array('native-hotspot', $roles, true)) {
                $backends[] = 'native-hotspot';
            }
            if (in_array('native-pppoe', $roles, true)) {
                $backends[] = 'native-pppoe';
            }
            if ($backends !== []) {
                $router['migration_backends'] = $backends;
                $targets[] = $router;
            }
        }
        return $targets;
    }

    public function readiness(string $username, int $targetRouterId, string $targetBackend): array
    {
        Database::migrate();
        $customer = CustomerLocal::findByUsername($username);
        if ($customer === null) {
            throw new RuntimeException('المشترك غير موجود.');
        }
        $sourceRouterId = (int) ($customer['router_id'] ?? 0);
        $sourceBackend = (string) ($customer['service_backend'] ?? 'user-manager');
        $target = Router::find($targetRouterId);
        $source = Router::find($sourceRouterId);
        $packageId = (int) ($customer['package_id'] ?? 0);
        $blocks = [];
        $warnings = [];

        if ($target === null || empty($target['enabled']) || $targetRouterId === $sourceRouterId) {
            $blocks[] = 'اختر راوتراً هدفاً مختلفاً ومفعّلاً.';
        }
        if (!in_array($targetBackend, ['user-manager', 'native-hotspot', 'native-pppoe'], true)) {
            $blocks[] = 'نظام الخدمة الهدف غير مدعوم.';
        }
        $allowedBackends = $target !== null
            ? ($this->targetBackends($target) ?: ['user-manager', 'native-hotspot', 'native-pppoe'])
            : [];
        if ($target !== null && !in_array($targetBackend, $allowedBackends, true)) {
            $blocks[] = 'نظام الخدمة الهدف غير مفعّل ضمن جاهزية الراوتر.';
        }
        if ($packageId <= 0) {
            $blocks[] = 'لا توجد باقة GreenNet مرتبطة بالمشترك.';
        }
        $profile = RouterPackageProfile::profileName($targetRouterId, $packageId, '', $targetBackend);
        if ($profile === '') {
            $blocks[] = 'ربط الباقة بالـ Profile على الراوتر الهدف مفقود.';
        }

        $sourceRecord = null;
        $targetRecord = null;
        $sourceSessions = [];
        $targetReadError = '';
        try {
            if ($sourceRouterId > 0) {
                $sourceBundle = ($this->bundleForRouter)($sourceRouterId);
                $sourceRecord = $this->remoteRecord($sourceBundle, $username, $sourceBackend);
                $sourceSessions = $this->activeSessions($sourceBundle, $username, $sourceBackend);
            }
        } catch (Throwable $e) {
            $warnings[] = 'تعذر تأكيد حالة الحساب أو الجلسة على الراوتر المصدر.';
        }
        if ($target !== null) {
            try {
                $targetBundle = ($this->bundleForRouter)($targetRouterId);
                $targetRecord = $this->remoteRecord($targetBundle, $username, $targetBackend);
            } catch (Throwable $e) {
                $targetReadError = $e->getMessage();
                $blocks[] = 'تعذر الاتصال بالراوتر الهدف أو قراءة نظام الخدمة.';
            }
        }

        $latest = SubscriberRouterMigration::latestForCustomer((int) ($customer['id'] ?? 0));
        $resumeSafe = $targetRecord !== null
            && is_array($latest)
            && (int) ($latest['target_router_id'] ?? 0) === $targetRouterId
            && (string) ($latest['target_backend'] ?? '') === $targetBackend
            && (string) ($latest['target_record_id'] ?? '') !== ''
            && hash_equals((string) $latest['target_record_id'], (string) ($targetRecord['.id'] ?? ''))
            && in_array((string) ($latest['status'] ?? ''), ['target_created', 'cutover_failed'], true);
        if ($targetRecord !== null && !$resumeSafe) {
            $blocks[] = 'يوجد حساب بالاسم نفسه على الراوتر الهدف ولا يمكن إثبات ارتباطه بهذا النقل.';
        } elseif ($resumeSafe) {
            $warnings[] = 'الحساب الهدف أُنشئ في محاولة سابقة ويمكن استكمال القطع المحلي بأمان.';
        }
        $targetProfileMatches = false;
        if ($targetRecord !== null && $profile !== '' && $target !== null) {
            try {
                $targetProfileMatches = $this->targetProfileMatches(
                    $targetBundle,
                    $targetRecord,
                    $targetBackend,
                    $profile
                );
                if ($resumeSafe && !$targetProfileMatches) {
                    $warnings[] = 'الحساب الهدف معروف للنقل السابق لكن Profile يحتاج إعادة مزامنة قبل القطع.';
                }
            } catch (Throwable) {
                $blocks[] = 'تعذر التحقق من Profile الحساب الهدف.';
            }
        }

        if ($sourceRouterId <= 0 || $source === null) {
            $blocks[] = 'الراوتر المصدر غير معيّن في GreenNet.';
        }
        if ($sourceRecord === null) {
            $warnings[] = 'الحساب المصدر غير ظاهر؛ لن يتم تنظيف أي سجل مصدر تلقائياً.';
        }

        $usage = $this->usageSnapshot($username);
        if ($sourceRouterId !== $targetRouterId || $sourceBackend !== $targetBackend) {
            $warnings[] = 'عدادات RouterOS لا تنتقل بين الأنظمة؛ سيُحفظ استهلاك دورة GreenNet الحالية ويتطلب النقل قرار استمرارية صريحاً.';
        }

        return [
            'state' => $blocks !== [] ? 'blocked' : ($warnings !== [] ? 'warning' : 'ready'),
            'blocks' => array_values(array_unique($blocks)),
            'warnings' => array_values(array_unique($warnings)),
            'customer' => $customer,
            'source_router' => $source ?? [],
            'source_backend' => $sourceBackend,
            'source_record' => $this->recordProjection($sourceRecord),
            'source_sessions' => $sourceSessions,
            'target_router' => $target ?? [],
            'target_backend' => $targetBackend,
            'target_profile_name' => $profile,
            'target_record' => $this->recordProjection($targetRecord),
            'target_profile_matches' => $targetProfileMatches,
            'target_read_error' => $targetReadError !== '' ? 'Target read unavailable.' : '',
            'resume_migration_id' => $resumeSafe ? (int) ($latest['id'] ?? 0) : 0,
            'usage' => $usage,
        ];
    }

    public function migrate(
        string $username,
        int $targetRouterId,
        string $targetBackend,
        string $password,
        string $usageDecision,
        string $cleanupAction = 'leave'
    ): array {
        $ready = $this->readiness($username, $targetRouterId, $targetBackend);
        if ($ready['state'] === 'blocked') {
            throw new RuntimeException(implode(' ', $ready['blocks']));
        }
        if (!in_array($usageDecision, ['preserve_recorded_usage', 'accept_backend_limitations'], true)) {
            throw new RuntimeException('اختر قراراً واضحاً لاستمرارية الاستهلاك.');
        }
        if ($password === '' && (int) ($ready['resume_migration_id'] ?? 0) <= 0) {
            throw new RuntimeException('كلمة مرور الحساب الهدف مطلوبة ولا يمكن استعادتها من GreenNet.');
        }
        if (preg_match('/[\r\n\t]/', $password)) {
            throw new RuntimeException('كلمة المرور تحتوي محارف غير مدعومة.');
        }

        $customer = $ready['customer'];
        $migrationId = (int) ($ready['resume_migration_id'] ?? 0);
        if ($migrationId <= 0) {
            $migrationId = SubscriberRouterMigration::create([
                'customer_id' => (int) $customer['id'],
                'username' => $username,
                'source_router_id' => (int) $customer['router_id'],
                'source_backend' => (string) $customer['service_backend'],
                'target_router_id' => $targetRouterId,
                'target_backend' => $targetBackend,
                'package_id' => (int) $customer['package_id'],
                'target_profile_name' => (string) $ready['target_profile_name'],
                'source_record_id' => (string) ($ready['source_record']['id'] ?? ''),
                'status' => 'creating_target',
                'source_cleanup_action' => $cleanupAction,
                'source_cleanup_state' => $cleanupAction === 'leave' ? 'left_active' : 'pending',
                'usage_decision' => $usageDecision,
                'usage_snapshot' => $ready['usage'],
            ]);
        }

        try {
            $targetRecordId = (string) ($ready['target_record']['id'] ?? '');
            if ($targetRecordId === '') {
                $targetRecordId = $this->createTargetAccount(
                    $migrationId,
                    $username,
                    $targetRouterId,
                    $targetBackend,
                    (string) $ready['target_profile_name'],
                    $password
                );
                SubscriberRouterMigration::update($migrationId, [
                    'target_record_id' => $targetRecordId,
                    'status' => 'target_created',
                ]);
            } elseif (empty($ready['target_profile_matches'])) {
                $this->assignTargetProfile(
                    $migrationId,
                    $username,
                    $targetRouterId,
                    $targetBackend,
                    $targetRecordId,
                    (string) $ready['target_profile_name']
                );
            }

            $this->cutoverAfterVerifiedTarget($migrationId, $targetRecordId);
        } catch (Throwable $e) {
            $migration = SubscriberRouterMigration::find($migrationId) ?? [];
            $status = (string) ($migration['target_record_id'] ?? '') !== '' ? 'cutover_failed' : 'target_failed';
            SubscriberRouterMigration::update($migrationId, [
                'status' => $status,
                'failure_reason' => $this->safeFailure($e->getMessage()),
            ]);
            throw $e;
        }

        if ($cleanupAction !== 'leave') {
            $this->cleanupSource($migrationId, $cleanupAction);
        } else {
            SubscriberRouterMigration::update($migrationId, [
                'status' => 'completed',
                'source_cleanup_state' => 'left_active',
            ]);
        }

        return SubscriberRouterMigration::find($migrationId) ?? [];
    }

    public function cutoverAfterVerifiedTarget(int $migrationId, string $verifiedTargetRecordId): void
    {
        $migration = SubscriberRouterMigration::find($migrationId);
        if ($migration === null || $verifiedTargetRecordId === '') {
            throw new RuntimeException('سجل النقل أو إثبات الحساب الهدف غير متاح.');
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE customers_local
                SET router_id=:target_router_id, service_backend=:target_backend,
                    service_status='active', updated_at=CURRENT_TIMESTAMP
                WHERE id=:customer_id AND router_id=:source_router_id AND service_backend=:source_backend
            ");
            $stmt->execute([
                'target_router_id' => (int) $migration['target_router_id'],
                'target_backend' => (string) $migration['target_backend'],
                'customer_id' => (int) $migration['customer_id'],
                'source_router_id' => (int) $migration['source_router_id'],
                'source_backend' => (string) $migration['source_backend'],
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('تغيّر تعيين المشترك أثناء النقل؛ لم يتم تنفيذ القطع المحلي.');
            }
            SubscriberRouterMigration::update($migrationId, [
                'target_record_id' => $verifiedTargetRecordId,
                'status' => 'cutover_complete',
                'failure_reason' => '',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function cleanupSource(int $migrationId, string $action): void
    {
        if (!in_array($action, ['disable', 'disconnect', 'delete'], true)) {
            return;
        }
        $migration = SubscriberRouterMigration::find($migrationId);
        if ($migration === null || !in_array((string) $migration['status'], ['cutover_complete', 'cleanup_pending'], true)) {
            throw new RuntimeException('يجب إكمال القطع إلى الراوتر الهدف قبل تنظيف المصدر.');
        }
        SubscriberRouterMigration::update($migrationId, [
            'source_cleanup_action' => $action,
            'source_cleanup_state' => 'running',
            'status' => 'cleanup_pending',
        ]);
        try {
            $bundle = ($this->bundleForRouter)((int) $migration['source_router_id']);
            $username = (string) $migration['username'];
            $backend = (string) $migration['source_backend'];
            $record = $this->remoteRecord($bundle, $username, $backend);
            if ($action === 'disconnect') {
                $this->disconnectSessions($migrationId, $bundle, $username, $backend);
            } elseif ($record === null) {
                // Already absent is an idempotent cleanup result.
            } else {
                $this->mutateSourceRecord($migrationId, $bundle, $username, $backend, $record, $action);
            }
            SubscriberRouterMigration::update($migrationId, [
                'status' => 'completed',
                'source_cleanup_state' => $action === 'delete' ? 'deleted' : ($action === 'disable' ? 'disabled' : 'disconnected'),
                'failure_reason' => '',
            ]);
        } catch (Throwable $e) {
            SubscriberRouterMigration::update($migrationId, [
                'status' => 'cleanup_pending',
                'source_cleanup_state' => 'failed',
                'failure_reason' => $this->safeFailure($e->getMessage()),
            ]);
            throw $e;
        }
    }

    private function createTargetAccount(
        int $migrationId,
        string $username,
        int $routerId,
        string $backend,
        string $profile,
        string $password
    ): string {
        $bundle = ($this->bundleForRouter)($routerId);
        $auditId = $this->internalAudit($username, 'subscriber_router_migration_target', $backend, $profile);
        $result = $bundle->write->execute(
            new WriteExecutionRequest(
                'subscriber_router_migration_target',
                $backend,
                $username,
                'backend account create and profile assignment',
                ['migration_id' => $migrationId, 'profile_name' => $profile],
                $auditId,
                true
            ),
            function (AuthorizedRouterOSWriterInterface $writer) use ($bundle, $username, $backend, $profile, $password, $migrationId): array {
                if ($backend === 'user-manager') {
                    $writer->execute(new RouterOSWriteCommand('/user-manager/user/add', [
                        'name' => $username,
                        'password' => $password,
                    ]));
                    $record = $this->remoteRecord($bundle, $username, $backend);
                    $id = (string) ($record['.id'] ?? '');
                    if ($id === '') {
                        throw new RuntimeException('تعذر التحقق من إنشاء حساب User Manager.');
                    }
                    SubscriberRouterMigration::update($migrationId, [
                        'target_record_id' => $id,
                        'status' => 'target_created',
                    ]);
                    $writer->execute(new RouterOSWriteCommand('/user-manager/user-profile/add', [
                        'user' => $id,
                        'profile' => $profile,
                    ]));
                    $relations = $bundle->read->read('/user-manager/user-profile/print', ['?user' => $id]);
                    $matched = array_filter($relations, static fn (array $row): bool =>
                        (string) ($row['user'] ?? '') === $id
                        && (string) ($row['profile'] ?? '') === $profile
                    );
                    if (count($matched) !== 1) {
                        throw new RuntimeException('تعذر التحقق من ربط Profile بالحساب الهدف.');
                    }
                } else {
                    $command = $backend === 'native-hotspot' ? '/ip/hotspot/user/add' : '/ppp/secret/add';
                    $params = ['name' => $username, 'password' => $password, 'profile' => $profile];
                    if ($backend === 'native-pppoe') {
                        $params['service'] = 'pppoe';
                    }
                    $writer->execute(new RouterOSWriteCommand($command, $params));
                }
                $record = $this->remoteRecord($bundle, $username, $backend);
                $id = (string) ($record['.id'] ?? '');
                if ($id === '') {
                    throw new RuntimeException('تعذر التحقق من الحساب الهدف بعد الإنشاء.');
                }
                SubscriberRouterMigration::update($migrationId, [
                    'target_record_id' => $id,
                    'status' => 'target_created',
                ]);
                return ['verified' => true, 'record_id' => $id];
            }
        );
        if (!$result->ok) {
            throw new RuntimeException($result->safeError !== '' ? $result->safeError : 'فشل إنشاء الحساب الهدف.');
        }
        $record = $this->remoteRecord($bundle, $username, $backend);
        return (string) ($record['.id'] ?? '');
    }

    private function assignTargetProfile(
        int $migrationId,
        string $username,
        int $routerId,
        string $backend,
        string $recordId,
        string $profile
    ): void {
        $bundle = ($this->bundleForRouter)($routerId);
        $command = match ($backend) {
            'user-manager' => '/user-manager/user-profile/add',
            'native-hotspot' => '/ip/hotspot/user/set',
            'native-pppoe' => '/ppp/secret/set',
            default => throw new RuntimeException('نظام الهدف غير مدعوم.'),
        };
        $params = $backend === 'user-manager'
            ? ['user' => $recordId, 'profile' => $profile]
            : ['numbers' => $recordId, 'profile' => $profile];
        $auditId = $this->internalAudit($username, 'subscriber_router_migration_target_profile', $backend, $profile);
        $result = $bundle->write->execute(
            new WriteExecutionRequest(
                'subscriber_router_migration_target_profile',
                $backend,
                $username,
                $command,
                ['migration_id' => $migrationId, 'record_id' => $recordId, 'profile_name' => $profile],
                $auditId,
                true
            ),
            function (AuthorizedRouterOSWriterInterface $writer) use ($command, $params, $bundle, $username, $backend, $profile): array {
                $writer->execute(new RouterOSWriteCommand($command, $params));
                $record = $this->remoteRecord($bundle, $username, $backend);
                if ($record === null || !$this->targetProfileMatches($bundle, $record, $backend, $profile)) {
                    throw new RuntimeException('تعذر التحقق من Profile الحساب الهدف.');
                }
                return ['verified' => true];
            }
        );
        if (!$result->ok) {
            throw new RuntimeException($result->safeError !== '' ? $result->safeError : 'فشل مزامنة Profile الهدف.');
        }
    }

    private function mutateSourceRecord(
        int $migrationId,
        RouterOSGatewayBundle $bundle,
        string $username,
        string $backend,
        array $record,
        string $action
    ): void {
        $id = (string) ($record['.id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('معرّف الحساب المصدر غير متاح.');
        }
        $command = match ($backend) {
            'user-manager' => '/user-manager/user/' . ($action === 'delete' ? 'remove' : 'set'),
            'native-hotspot' => '/ip/hotspot/user/' . ($action === 'delete' ? 'remove' : 'set'),
            'native-pppoe' => '/ppp/secret/' . ($action === 'delete' ? 'remove' : 'set'),
            default => throw new RuntimeException('نظام المصدر غير مدعوم.'),
        };
        $params = ['numbers' => $id];
        if ($action === 'disable') {
            $params['disabled'] = 'yes';
        }
        $dependentRemovals = [];
        if ($backend === 'user-manager' && $action === 'delete') {
            foreach ($this->activeSessions($bundle, $username, $backend) as $session) {
                $dependentRemovals[] = ['/user-manager/session/remove', (string) $session['id']];
            }
            $relations = $bundle->read->read('/user-manager/user-profile/print', ['?user' => $id]);
            foreach ($relations as $relation) {
                $relationId = (string) ($relation['.id'] ?? '');
                if ($relationId !== '' && (string) ($relation['user'] ?? '') === $id) {
                    $dependentRemovals[] = ['/user-manager/user-profile/remove', $relationId];
                }
            }
        }
        $auditId = $this->internalAudit($username, 'subscriber_router_migration_cleanup', $backend, '');
        $result = $bundle->write->execute(
            new WriteExecutionRequest(
                'subscriber_router_migration_cleanup',
                $backend,
                $username,
                $command,
                ['migration_id' => $migrationId, 'numbers' => $id, 'cleanup' => $action],
                $auditId,
                true
            ),
            function (AuthorizedRouterOSWriterInterface $writer) use ($command, $params, $bundle, $username, $backend, $action, $dependentRemovals): array {
                foreach ($dependentRemovals as [$dependentCommand, $dependentId]) {
                    $writer->execute(new RouterOSWriteCommand($dependentCommand, ['numbers' => $dependentId]));
                }
                $writer->execute(new RouterOSWriteCommand($command, $params));
                $after = $this->remoteRecord($bundle, $username, $backend);
                if ($action === 'delete' && $after !== null) {
                    throw new RuntimeException('الحساب المصدر ما زال موجوداً بعد الحذف.');
                }
                if ($action === 'disable' && !in_array(strtolower((string) ($after['disabled'] ?? '')), ['yes', 'true'], true)) {
                    throw new RuntimeException('لم يتم التحقق من تعطيل الحساب المصدر.');
                }
                return ['verified' => true];
            }
        );
        if (!$result->ok) {
            throw new RuntimeException($result->safeError !== '' ? $result->safeError : 'فشل تنظيف الحساب المصدر.');
        }
    }

    private function disconnectSessions(
        int $migrationId,
        RouterOSGatewayBundle $bundle,
        string $username,
        string $backend
    ): void {
        $sessions = $this->activeSessions($bundle, $username, $backend);
        if ($sessions === []) {
            return;
        }
        $command = match ($backend) {
            'user-manager' => '/user-manager/session/remove',
            'native-hotspot' => '/ip/hotspot/active/remove',
            'native-pppoe' => '/ppp/active/remove',
            default => throw new RuntimeException('نظام المصدر غير مدعوم.'),
        };
        $auditId = $this->internalAudit($username, 'subscriber_router_migration_disconnect', $backend, '');
        $result = $bundle->write->execute(
            new WriteExecutionRequest(
                'subscriber_router_migration_disconnect',
                $backend,
                $username,
                $command,
                ['migration_id' => $migrationId, 'session_ids' => array_column($sessions, 'id')],
                $auditId,
                true
            ),
            function (AuthorizedRouterOSWriterInterface $writer) use ($command, $sessions, $bundle, $username, $backend): array {
                foreach ($sessions as $session) {
                    $writer->execute(new RouterOSWriteCommand($command, ['numbers' => (string) $session['id']]));
                }
                $remainingIds = array_column($this->activeSessions($bundle, $username, $backend), 'id');
                foreach ($sessions as $session) {
                    if (in_array((string) $session['id'], $remainingIds, true)) {
                        throw new RuntimeException('الجلسة المصدر ما زالت موجودة بعد أمر الفصل.');
                    }
                }
                return ['verified' => true, 'removed' => count($sessions)];
            }
        );
        if (!$result->ok) {
            throw new RuntimeException($result->safeError !== '' ? $result->safeError : 'فشل فصل الجلسة المصدر.');
        }
    }

    private function remoteRecord(RouterOSGatewayBundle $bundle, string $username, string $backend): ?array
    {
        if ($backend !== 'user-manager') {
            return (new NativeSubscriberRecordResolver())->resolve($bundle->read, $username, $backend);
        }
        $rows = $bundle->read->read('/user-manager/user/print', ['?name' => $username]);
        $matches = array_values(array_filter($rows, static fn (array $row): bool => (string) ($row['name'] ?? '') === $username));
        if (count($matches) > 1) {
            throw new RuntimeException('وُجد أكثر من حساب مطابق؛ أُوقف النقل.');
        }
        return $matches[0] ?? null;
    }

    private function activeSessions(RouterOSGatewayBundle $bundle, string $username, string $backend): array
    {
        $command = match ($backend) {
            'user-manager' => '/user-manager/session/print',
            'native-hotspot' => '/ip/hotspot/active/print',
            'native-pppoe' => '/ppp/active/print',
            default => '',
        };
        if ($command === '') {
            return [];
        }
        $rows = $bundle->read->read($command, ['?user' => $username]);
        $sessions = [];
        foreach ($rows as $row) {
            $rowUser = (string) ($row['user'] ?? $row['name'] ?? $row['username'] ?? '');
            $id = (string) ($row['.id'] ?? '');
            if ($rowUser === $username && $id !== '') {
                $sessions[] = ['id' => $id];
            }
        }
        return $sessions;
    }

    private function usageSnapshot(string $username): array
    {
        try {
            $data = (new CustomerDashboardService())->getDashboardData($username);
            return [
                'used_bytes' => (int) ($data['used_bytes'] ?? 0),
                'remaining_bytes' => (int) ($data['remaining_bytes'] ?? 0),
                'used_percent' => (int) ($data['used_percent'] ?? 0),
                'baseline_id' => (int) ($data['greennet_baseline_id'] ?? 0),
                'captured_at' => date('Y-m-d H:i:s'),
            ];
        } catch (Throwable) {
            return ['known' => false, 'captured_at' => date('Y-m-d H:i:s')];
        }
    }

    private function internalAudit(string $username, string $action, string $backend, string $profile): int
    {
        $guard = new WriteSafetyGuard();
        $guard->ensureTables();
        $guard->assertDryRunAllowed();
        return $guard->recordDryRun([
            'action' => $action,
            'dataset' => $backend,
            'username' => $username,
            'command' => 'guarded backend dispatch',
            'params' => ['backend' => $backend, 'profile_name' => $profile],
            'router_response' => 'Internal production preflight completed.',
        ]);
    }

    private function targetBackends(array $router): array
    {
        $roles = (new RouterOnboardingService())->roles($router);
        return array_values(array_filter([
            in_array('user-manager', $roles, true) ? 'user-manager' : null,
            in_array('native-hotspot', $roles, true) ? 'native-hotspot' : null,
            in_array('native-pppoe', $roles, true) ? 'native-pppoe' : null,
        ]));
    }

    private function targetProfileMatches(
        RouterOSGatewayBundle $bundle,
        array $record,
        string $backend,
        string $profile
    ): bool {
        if ($backend !== 'user-manager') {
            return (string) ($record['profile'] ?? '') === $profile;
        }
        $id = (string) ($record['.id'] ?? '');
        if ($id === '') {
            return false;
        }
        $relations = $bundle->read->read('/user-manager/user-profile/print', ['?user' => $id]);
        foreach ($relations as $relation) {
            if ((string) ($relation['user'] ?? '') === $id
                && (string) ($relation['profile'] ?? '') === $profile) {
                return true;
            }
        }
        return false;
    }

    private function recordProjection(?array $record): array
    {
        if ($record === null) {
            return ['found' => false, 'id' => '', 'disabled' => false];
        }
        return [
            'found' => true,
            'id' => (string) ($record['.id'] ?? ''),
            'disabled' => in_array(strtolower((string) ($record['disabled'] ?? '')), ['yes', 'true'], true),
        ];
    }

    private function safeFailure(string $message): string
    {
        $safe = preg_replace('/[^\P{C}\r\n\t]/u', '', trim($message)) ?: 'Migration failed.';
        return function_exists('mb_substr') ? mb_substr($safe, 0, 500) : substr($safe, 0, 500);
    }
}
