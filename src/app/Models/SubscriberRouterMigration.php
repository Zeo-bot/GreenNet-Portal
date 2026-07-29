<?php

declare(strict_types=1);

namespace GreenNet\Models;

use PDO;

final class SubscriberRouterMigration extends Model
{
    public static function create(array $data): int
    {
        $stmt = self::db()->prepare("
            INSERT INTO subscriber_router_migrations (
                customer_id, username, source_router_id, source_backend,
                target_router_id, target_backend, package_id, target_profile_name,
                source_record_id, status, source_cleanup_action, source_cleanup_state,
                usage_decision, usage_snapshot_json, failure_reason
            ) VALUES (
                :customer_id, :username, :source_router_id, :source_backend,
                :target_router_id, :target_backend, :package_id, :target_profile_name,
                :source_record_id, :status, :source_cleanup_action, :source_cleanup_state,
                :usage_decision, :usage_snapshot_json, :failure_reason
            )
        ");
        $stmt->execute([
            'customer_id' => (int) ($data['customer_id'] ?? 0),
            'username' => (string) ($data['username'] ?? ''),
            'source_router_id' => (int) ($data['source_router_id'] ?? 0),
            'source_backend' => (string) ($data['source_backend'] ?? ''),
            'target_router_id' => (int) ($data['target_router_id'] ?? 0),
            'target_backend' => (string) ($data['target_backend'] ?? ''),
            'package_id' => (int) ($data['package_id'] ?? 0),
            'target_profile_name' => (string) ($data['target_profile_name'] ?? ''),
            'source_record_id' => (string) ($data['source_record_id'] ?? ''),
            'status' => (string) ($data['status'] ?? 'ready'),
            'source_cleanup_action' => (string) ($data['source_cleanup_action'] ?? 'leave'),
            'source_cleanup_state' => (string) ($data['source_cleanup_state'] ?? 'not_requested'),
            'usage_decision' => (string) ($data['usage_decision'] ?? ''),
            'usage_snapshot_json' => json_encode($data['usage_snapshot'] ?? [], JSON_UNESCAPED_SLASHES),
            'failure_reason' => (string) ($data['failure_reason'] ?? ''),
        ]);

        return (int) self::db()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare("SELECT * FROM subscriber_router_migrations WHERE id=:id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public static function latestForCustomer(int $customerId): ?array
    {
        $stmt = self::db()->prepare("
            SELECT m.*, sr.name AS source_router_name, tr.name AS target_router_name
            FROM subscriber_router_migrations m
            LEFT JOIN routers sr ON sr.id=m.source_router_id
            LEFT JOIN routers tr ON tr.id=m.target_router_id
            WHERE m.customer_id=:customer_id ORDER BY m.id DESC LIMIT 1
        ");
        $stmt->execute(['customer_id' => $customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public static function update(int $id, array $changes): void
    {
        $allowed = [
            'target_record_id', 'status', 'source_cleanup_action',
            'source_cleanup_state', 'failure_reason', 'completed_at',
        ];
        $sets = [];
        $params = ['id' => $id];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $changes)) {
                $sets[] = "{$field}=:{$field}";
                $params[$field] = $changes[$field];
            }
        }
        if ($sets === []) {
            return;
        }
        $sets[] = 'updated_at=CURRENT_TIMESTAMP';
        $stmt = self::db()->prepare('UPDATE subscriber_router_migrations SET ' . implode(',', $sets) . ' WHERE id=:id');
        $stmt->execute($params);
    }
}
