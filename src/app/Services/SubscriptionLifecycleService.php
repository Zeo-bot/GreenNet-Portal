<?php

declare(strict_types=1);

namespace GreenNet\Services;

use DateTimeImmutable;
use DateTimeZone;
use GreenNet\Core\Config;
use GreenNet\Core\Database;
use GreenNet\Models\CustomerLocal;
use GreenNet\Models\Payment;
use GreenNet\Models\ServicePackage;
use PDO;
use Throwable;

final class SubscriptionLifecycleService
{
    public function evaluate(string $username, ?int $usedBytes = null, bool $persist = true): array
    {
        $this->ensureTable();
        $customer = CustomerLocal::findByUsername(trim($username));
        if ($customer === null) {
            return $this->state('inactive', 'المشترك غير موجود محلياً.', false);
        }

        $package = (int) ($customer['package_id'] ?? 0) > 0
            ? ServicePackage::find((int) $customer['package_id'])
            : null;
        $renewal = Payment::latestRenewalForUser($username);
        $serviceStatus = strtolower((string) ($customer['service_status'] ?? 'active'));
        $pendingRenewal = $this->hasPendingRenewal($username);
        $previous = $this->latest($username);
        if ($usedBytes === null && ($previous['usage_state'] ?? '') === 'available' && $previous['used_bytes'] !== null) {
            $usedBytes = (int) $previous['used_bytes'];
        }

        $state = 'active';
        $reason = 'الاشتراك فعال محلياً.';
        $requiresEnforcement = false;
        $expiresAt = (string) ($renewal['expires_at'] ?? '');
        $quotaBytes = $package === null ? 0 : (int) round((float) ($package['quota_gb'] ?? 0) * 1000 * 1000 * 1000);
        $quotaState = $quotaBytes > 0 ? ($usedBytes === null ? 'unavailable' : 'available') : 'not_applicable';

        if (in_array((string) ($previous['effective_state'] ?? ''), ['expired_time', 'quota_exhausted'], true)
            && ($previous['enforcement_state'] ?? '') === 'enforced') {
            $state = (string) $previous['effective_state'];
            $reason = $state === 'expired_time' ? 'انتهت مدة الاشتراك وتم تنفيذ الإيقاف.' : 'استهلكت الحصة وتم تنفيذ الإيقاف.';
            $requiresEnforcement = true;
        } elseif (in_array($serviceStatus, ['suspended', 'disabled'], true)) {
            [$state, $reason, $requiresEnforcement] = ['suspended', 'الخدمة موقوفة محلياً.', true];
        } elseif ($package === null || $renewal === null) {
            [$state, $reason] = ['inactive', 'لا توجد باقة أو دورة اشتراك مكتملة.'];
        } elseif ($expiresAt !== '' && $this->expired($expiresAt)) {
            [$state, $reason, $requiresEnforcement] = ['expired_time', 'انتهت مدة الاشتراك.', true];
        } elseif ($quotaBytes > 0 && $usedBytes !== null && $usedBytes >= $quotaBytes) {
            [$state, $reason, $requiresEnforcement] = ['quota_exhausted', 'تم استهلاك كامل حصة الباقة.', true];
        } elseif ($pendingRenewal) {
            [$state, $reason] = ['pending_renewal', 'يوجد طلب تجديد بانتظار المعالجة.'];
        } elseif ($expiresAt !== '' && $this->daysLeft($expiresAt) <= 7) {
            [$state, $reason] = ['expiring_soon', 'سينتهي الاشتراك خلال سبعة أيام.'];
        }

        $enforcementState = $requiresEnforcement
            ? (string) ($previous['enforcement_state'] ?? 'pending')
            : (in_array($serviceStatus, ['sync_pending', 'pending'], true) ? 'renewal_sync_pending' : 'not_required');
        if ($requiresEnforcement && !in_array($enforcementState, ['enforced', 'failed', 'router_unavailable', 'remote_missing'], true)) {
            $enforcementState = 'pending';
        }

        $result = [
            'username' => $username,
            'state' => $state,
            'label' => $this->label($state),
            'reason' => $reason,
            'requires_enforcement' => $requiresEnforcement,
            'enforcement_state' => $enforcementState,
            'backend' => (string) ($customer['service_backend'] ?? 'user-manager'),
            'router_id' => (int) ($customer['router_id'] ?? 0),
            'package_id' => (int) ($customer['package_id'] ?? 0),
            'package_name' => (string) ($package['name'] ?? ''),
            'expires_at' => $expiresAt,
            'days_left' => $expiresAt !== '' && !$this->expired($expiresAt) ? $this->daysLeft($expiresAt) : 0,
            'quota_bytes' => $quotaBytes,
            'used_bytes' => $usedBytes,
            'usage_state' => $quotaState,
            'evaluated_at' => date('Y-m-d H:i:s'),
        ];

        if ($persist) {
            $this->persist($result);
        }
        return $result;
    }

    public function previewAll(): array
    {
        return array_map(
            fn (array $customer): array => $this->evaluate((string) $customer['username'], null, false),
            CustomerLocal::all()
        );
    }

    public function markEnforcement(string $username, string $state, string $message = ''): void
    {
        if (!in_array($state, ['pending', 'enforced', 'failed', 'router_unavailable', 'remote_missing', 'renewal_sync_pending'], true)) {
            return;
        }
        $current = $this->evaluate($username, null, false);
        $current['enforcement_state'] = $state;
        $current['enforcement_message'] = $message;
        $this->persist($current);
    }

    public function resetAfterRenewal(string $username): void
    {
        $state = $this->evaluate($username, 0, false);
        $state['state'] = 'active';
        $state['label'] = $this->label('active');
        $state['requires_enforcement'] = false;
        $state['enforcement_state'] = 'renewal_sync_pending';
        $state['reason'] = 'تم التجديد محلياً وتنتظر مزامنة حساب الموجّه.';
        $this->persist($state);
    }

    public function counts(): array
    {
        $counts = ['expired_pending' => 0, 'quota_exhausted' => 0, 'enforcement_failed' => 0, 'renewal_sync_pending' => 0];
        foreach ($this->previewAll() as $row) {
            if (($row['state'] ?? '') === 'expired_time' && ($row['enforcement_state'] ?? '') !== 'enforced') {
                $counts['expired_pending']++;
            }
            if (($row['state'] ?? '') === 'quota_exhausted') {
                $counts['quota_exhausted']++;
            }
        }
        try {
            $counts['enforcement_failed'] = (int) Database::connection()->query(
                "SELECT COUNT(*) FROM subscription_lifecycle_states WHERE enforcement_state IN ('failed','router_unavailable','remote_missing')"
            )->fetchColumn();
            $counts['renewal_sync_pending'] = (int) Database::connection()->query(
                "SELECT COUNT(*) FROM subscription_lifecycle_states WHERE enforcement_state = 'renewal_sync_pending'"
            )->fetchColumn();
        } catch (Throwable) {
        }
        return $counts;
    }

    private function persist(array $state): void
    {
        $stmt = Database::connection()->prepare("
            INSERT INTO subscription_lifecycle_states
                (username, effective_state, enforcement_state, enforcement_message, usage_state,
                 used_bytes, quota_bytes, expires_at, evaluated_at, updated_at)
            VALUES
                (:username, :effective_state, :enforcement_state, :enforcement_message, :usage_state,
                 :used_bytes, :quota_bytes, :expires_at, :evaluated_at, CURRENT_TIMESTAMP)
            ON CONFLICT(username) DO UPDATE SET
                effective_state = excluded.effective_state,
                enforcement_state = excluded.enforcement_state,
                enforcement_message = excluded.enforcement_message,
                usage_state = excluded.usage_state,
                used_bytes = excluded.used_bytes,
                quota_bytes = excluded.quota_bytes,
                expires_at = excluded.expires_at,
                evaluated_at = excluded.evaluated_at,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            'username' => $state['username'],
            'effective_state' => $state['state'],
            'enforcement_state' => $state['enforcement_state'],
            'enforcement_message' => (string) ($state['enforcement_message'] ?? ''),
            'usage_state' => $state['usage_state'],
            'used_bytes' => $state['used_bytes'],
            'quota_bytes' => $state['quota_bytes'],
            'expires_at' => $state['expires_at'],
            'evaluated_at' => $state['evaluated_at'],
        ]);
    }

    private function latest(string $username): array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM subscription_lifecycle_states WHERE username = :username");
        $stmt->execute(['username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function ensureTable(): void
    {
        Database::connection()->exec("
            CREATE TABLE IF NOT EXISTS subscription_lifecycle_states (
                username TEXT PRIMARY KEY,
                effective_state TEXT NOT NULL,
                enforcement_state TEXT NOT NULL DEFAULT 'not_required',
                enforcement_message TEXT DEFAULT '',
                usage_state TEXT DEFAULT 'unavailable',
                used_bytes INTEGER,
                quota_bytes INTEGER DEFAULT 0,
                expires_at TEXT,
                evaluated_at TEXT NOT NULL,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function hasPendingRenewal(string $username): bool
    {
        try {
            $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM renewal_requests WHERE lower(username) = lower(:username) AND lower(status) IN ('pending','processing','review','in_review')");
            $stmt->execute(['username' => $username]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function expired(string $expiresAt): bool
    {
        return new DateTimeImmutable($expiresAt, $this->timezone()) <= new DateTimeImmutable('now', $this->timezone());
    }

    private function daysLeft(string $expiresAt): int
    {
        return (int) (new DateTimeImmutable('now', $this->timezone()))->diff(new DateTimeImmutable($expiresAt, $this->timezone()))->format('%a');
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone((string) Config::get('TZ', 'Asia/Damascus'));
    }

    private function label(string $state): string
    {
        return match ($state) {
            'active' => 'فعال',
            'expiring_soon' => 'قريب الانتهاء',
            'expired_time' => 'منتهي بالمدة',
            'quota_exhausted' => 'الحصة مستهلكة',
            'suspended' => 'موقوف',
            'pending_renewal' => 'بانتظار التجديد',
            default => 'غير مفعّل',
        };
    }

    private function state(string $state, string $reason, bool $enforce): array
    {
        return ['state' => $state, 'label' => $this->label($state), 'reason' => $reason, 'requires_enforcement' => $enforce];
    }
}
